'use strict';

const fs = require('fs');
const assert = require('assert');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

const config = read('application/config/config.php');
const hooks = read('application/config/hooks.php');
const routes = read('application/config/routes.php');
const controller = read('application/controllers/AuditLog.php');
const hook = read('application/hooks/AuditLogHook.php');
const schema = read('db_setup.sql');

assert(/enable_hooks'\]\s*=\s*TRUE/.test(config), 'CodeIgniter hooks must be enabled');
assert(hooks.includes("'AuditLogHook.php'"), 'the audit hook must be registered');
assert(routes.includes("$route['audit-log']"), 'the admin audit route must exist');
assert(controller.includes("$this->role !== (string) ROLE_ADMIN"), 'the controller must enforce the administrator role');
assert(hook.includes("register_shutdown_function"), 'redirecting operations must still be captured');
assert(hook.includes("'[REDACTED]'"), 'sensitive request fields must be redacted');
assert(hook.includes('context.?value'), 'encrypted context values must be redacted');
assert(schema.includes('CREATE TABLE IF NOT EXISTS `audit_log`'), 'fresh installs must create the audit table');
assert(!/function\s+(delete|remove|truncate)/i.test(controller), 'the audit controller must not expose destructive actions');

console.log('Audit log regression checks passed (9 assertions).');
