const fs = require('fs');
const path = require('path');
const vm = require('vm');

const viewPath = path.join(__dirname, '..', 'application', 'views', 'jobCreation.php');
const view = fs.readFileSync(viewPath, 'utf8');
const controller = fs.readFileSync(path.join(__dirname, '..', 'application', 'controllers', 'JobCreation.php'), 'utf8');
const emailConcern = fs.readFileSync(path.join(__dirname, '..', 'application', 'controllers', 'concerns', 'JobCreationEmailTrait.php'), 'utf8');
const executionConcern = fs.readFileSync(path.join(__dirname, '..', 'application', 'controllers', 'concerns', 'JobCreationExecutionTrait.php'), 'utf8');
const compose = fs.readFileSync(path.join(__dirname, '..', 'docker-compose.yml'), 'utf8');
const start = view.indexOf('function handleCodeEditorTab');
const end = view.indexOf('function updatePythonInlineEditor', start);

if (start < 0 || end < 0) {
  throw new Error('Inline Python editor functions were not found.');
}

function jquery(target) {
  return {
    text() {},
    trigger(eventName) {
      if (target && eventName === 'input') {
        target.inputEvents = (target.inputEvents || 0) + 1;
      }
    }
  };
}

jquery.map = (items, callback) => items.map(callback);
const context = { $: jquery };
vm.createContext(context);
new vm.Script(view.slice(start, end), { filename: 'jobCreation.inline-editor.js' }).runInContext(context);

function textarea(value, cursor) {
  return {
    id: 'pythonInlineCode',
    value,
    inputEvents: 0,
    selectionStart: cursor,
    selectionEnd: cursor,
    focus() {},
    setSelectionRange(selectionStart, selectionEnd, direction) {
      this.selectionStart = selectionStart;
      this.selectionEnd = selectionEnd;
      this.direction = direction;
    }
  };
}

function key(name, options) {
  return Object.assign({
    key: name,
    shiftKey: false,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    preventDefault() { this.prevented = true; }
  }, options || {});
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

assert(controller.includes('use JobCreationEmailTrait;') && controller.includes('use JobCreationExecutionTrait;'), 'JobCreation must compose its focused implementation concerns.');
assert(!controller.includes('private function buildPythonExecutionCommand(') && executionConcern.includes('private function buildPythonExecutionCommand('), 'Execution command generation must remain isolated from the main controller.');
assert(!controller.includes('private function defaultFailureEmailBody(') && emailConcern.includes('private function defaultFailureEmailBody('), 'Email XML generation must remain isolated from the main controller.');
assert(compose.includes('repository-data-init:'), 'Compose must initialize the writable shared job repository before PHP and OpenVSCode start.');
assert(compose.includes('mkdir -p /repository/data-assets /repository/python/inline /repository/python/jobs'), 'Repository initialization must create the inline Python workspace root.');
assert(compose.includes("tar -C /bundled-sdk --exclude='__pycache__'"), 'Repository initialization must seed the SDK without copying transient Python caches or relying on Docker to create a root-owned bind path.');
assert((compose.match(/repository-data-init:\n\s+condition: service_completed_successfully/g) || []).length >= 3, 'PHP, Jenkins, and OpenVSCode must wait for repository initialization.');

let editor = textarea('if ready:', 9);
context.handlePythonCodeEditorKey(key('Enter'), editor);
assert(editor.value === 'if ready:\n    ', 'Enter should indent after a Python block opener.');
assert(editor.selectionStart === 14, 'Cursor should land at the new indentation.');
assert(editor.inputEvents === 1, 'Enter should emit one content-change event.');

editor = textarea('    print(value)', 16);
context.handlePythonCodeEditorKey(key('Enter'), editor);
assert(editor.value === '    print(value)\n    ', 'Enter should preserve current indentation.');

editor = textarea('values = []', 10);
context.handlePythonCodeEditorKey(key('Enter'), editor);
assert(editor.value === 'values = [\n    \n]', 'Enter should expand matching bracket pairs.');

editor = textarea('    result = 1', 10);
context.handlePythonCodeEditorKey(key('Home'), editor);
assert(editor.selectionStart === 4, 'Home should move to the first non-whitespace character.');
context.handlePythonCodeEditorKey(key('Home'), editor);
assert(editor.selectionStart === 0, 'A second Home should move to column zero.');
assert(editor.inputEvents === 0, 'Cursor-only Home navigation must not mark the draft as edited.');

editor = textarea('one\ntwo', 5);
context.handlePythonCodeEditorKey(key('End'), editor);
assert(editor.selectionStart === 7, 'End should move to the current line ending.');
assert(editor.inputEvents === 0, 'Cursor-only End navigation must not mark the draft as edited.');

editor = textarea('x', 1);
context.handlePythonCodeEditorKey(key('Tab'), editor);
assert(editor.value === 'x    ', 'Tab should insert four spaces.');

console.log('Inline Python editor keyboard tests passed.');
