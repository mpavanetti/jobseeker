const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const controller = read('application/controllers/JobCreation.php');
const editor = read('application/views/jobCreation.php');
const jobList = read('application/views/jobList.php');
const styles = read('assets/dist/css/job-edit.css');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

// A job can be opened directly from Job List or a bookmarked edit URL.
assert(jobList.includes("base_url() . 'jobCreation?edit='"),
  'Job List must expose a direct edit URL.');
assert(controller.includes("$this->input->get('edit', TRUE)"),
  'Job Creation must accept a requested edit job.');
assert(controller.includes("'requested_edit_job' => $requestedEditJob"),
  'The validated edit target must be passed to the view.');
assert(editor.includes('loadJobForEdit(requestedEditJob)'),
  'A direct edit URL must hydrate the Jenkins configuration automatically.');

// Edit and create are intentionally distinct operations.
assert(editor.includes('name="original_job_name"'),
  'Edit submissions must carry the original Jenkins identity.');
assert(editor.includes("$('#job_name').val(jobName).prop('readonly', true)"),
  'The job identity must be locked after Jenkins configuration hydration.');
assert(controller.includes("$job_name !== $originalJobName"),
  'The server must reject an edit submission whose identity changed.');
assert(editor.includes("editingOriginalJob !== '' ? 'Update Job'"),
  'Edit mode must label its primary action as an update.');

// Loading, dirty, failure, cancellation, and navigation are visible states.
for (const state of ['is-loading', 'is-dirty', 'is-error']) {
  assert(styles.includes(`.${state}`), `Missing edit workspace state style: ${state}`);
}
assert(editor.includes('function editHasUnsavedChanges()'),
  'The editor must detect unsaved changes.');
assert(editor.includes("$(window).on('beforeunload'"),
  'Navigating away from an edited job must be guarded.');
assert(editor.includes("$('#clearEditJob, #newJobFromHeader').click(startNewJob)"),
  'Both edit exits must use the guarded new-job transition.');

// Intentional inline replacements keep their loaded optimistic-lock token,
// while validation/conflict redirects keep the operator in the same job.
assert(/\$\('#pythonDockerfileText'\)\.val\(sample\.dockerfile \|\| ''\);[\s\S]{0,700}if \(editingOriginalJob === ''\) \{\s*\$\('#pythonWorkspaceSignature'\)\.val\(''\);/.test(editor),
  'Loading a sample while editing must preserve the workspace baseline signature.');
assert(!controller.includes("redirect('JobCreation');"),
  'Edit validation failures must return to the same edit URL.');

console.log('Job edit workspace checks passed.');
