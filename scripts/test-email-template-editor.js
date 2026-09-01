const assert = require('assert');
const fs = require('fs');

const addView = fs.readFileSync('application/views/addEmailSetting.php', 'utf8');
const editView = fs.readFileSync('application/views/editEmailSettings.php', 'utf8');
const editor = fs.readFileSync('assets/js/email-template-editor.js', 'utf8');
const controller = fs.readFileSync('application/controllers/EmailSettings.php', 'utf8');
const assetSync = fs.readFileSync('scripts/sync-frontend-assets.js', 'utf8');
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));

[addView, editView].forEach((view) => {
  assert(view.includes('assets/bower_components/ckeditor/ckeditor.js'), 'Email template forms must load CKEditor.');
  assert(view.includes('assets/js/email-template-editor.js'), 'Email template forms must load the shared editor initializer.');
  assert(!view.includes('CKEDITOR.replace'), 'Editor configuration must live in the shared script, not inline in the view.');
  assert(!view.includes('codemirror'), 'The CodeMirror source editor must be fully removed.');
  assert(!view.includes('beautify-html.js'), 'The CodeMirror formatter must be fully removed.');
  assert(!/config\.extraPlugins\s*=/.test(view), 'extraPlugins must be passed to CKEditor.replace(), not assigned after init.');
});

assert(addView.includes("html_escape(set_value('msg'))"), 'Failed add submissions must safely restore email HTML.');
assert(editView.includes('html_escape($fetch->msg)'), 'Stored email HTML must be safely rendered into the edit textarea.');
assert(editView.includes('html_escape($fetch->description)'), 'Editing must retain the stored email description.');
assert(editView.includes("$('#smtp').val(<?php echo json_encode($fetch->smtp); ?>)"), 'Editing must retain the stored SMTP provider.');
assert(editView.includes("(int) $fetch->enabled === 1 ? ' checked' : ''"), 'Editing must retain the stored enabled state.');

assert(/CKEDITOR\.replace\(\s*'msg'/.test(editor), 'The editor must attach CKEditor to the msg textarea.');
assert(/allowedContent:\s*true/.test(editor), 'The editor must disable content filtering so authored email HTML is preserved verbatim.');
assert(/fullPage:\s*looksLikeFullDocument/.test(editor), 'The editor must keep the <html>/<head>/<body> wrapper for full-document templates.');
assert(/extraPlugins:\s*'[^']*colorbutton[^']*'/.test(editor), 'The editor must load the colour buttons.');
assert(/extraPlugins:\s*'[^']*font[^']*'/.test(editor), 'The editor must load the font family/size controls.');
assert(/extraPlugins:\s*'[^']*justify[^']*'/.test(editor), 'The editor must load the alignment controls.');
assert(/protectedSource:\s*\[\s*JENKINS_TOKEN\s*\]/.test(editor), 'Jenkins ${...} tokens must be registered as protected source.');
assert(editor.includes("'Source'"), 'The toolbar must expose a raw HTML Source view.');
assert(editor.includes('editor.updateElement()'), 'Editor content must be mirrored back into the textarea for form submission.');
assert(/addEventListener\('submit'[\s\S]*editor\.updateElement\(\)/.test(editor), 'The form submit must flush the editor into the textarea.');

assert((controller.match(/\$msg = \$this->input->post\('msg'\);/g) || []).length === 2, 'Create and update must read email HTML without XSS rewriting.');
assert(!controller.includes("xss_clean($this->input->post('msg'))"), 'Email HTML must not be rewritten by the generic XSS cleaner.');

assert(assetSync.includes("copyDirectory(fromPackage('ckeditor')"), 'CKEditor must be restored into frontend assets.');
assert(!assetSync.includes("fromPackage('codemirror')"), 'The CodeMirror sync step must be removed.');
assert(!assetSync.includes("js-beautify"), 'The js-beautify sync step must be removed.');
assert.strictEqual(packageJson.dependencies.ckeditor, '4.12.1');
assert(!packageJson.dependencies.codemirror, 'codemirror must be removed from dependencies.');
assert(!packageJson.dependencies['js-beautify'], 'js-beautify must be removed from dependencies.');

console.log('Email template editor tests passed.');
