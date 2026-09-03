// Guards the response-delivery settings that keep JobSeeker light: server-side
// rendering is already fast, so transfer size is what users actually feel. The
// Job Creation page alone is ~470 KB of inline HTML.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const nginxConf = read('nginx/default.conf');
const nginxImage = read('docker/nginx_image');
const fpmPool = read('docker/php/www.conf');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

// --- Compression -----------------------------------------------------------
assert(/^\s*gzip\s+on;/m.test(nginxConf), 'nginx must compress responses; the app ships very large inline HTML.');
assert(/^\s*gzip_vary\s+on;/m.test(nginxConf), 'gzip_vary keeps shared caches from serving a compressed body to a client that cannot read it.');
assert(/^\s*gzip_min_length\s+\d+;/m.test(nginxConf), 'Very small responses must not pay the compression overhead.');

const gzipTypes = (nginxConf.match(/^\s*gzip_types([\s\S]*?);/m) || [])[1] || '';
['text/css', 'application/javascript', 'application/json', 'image/svg+xml'].forEach(type => {
  assert(gzipTypes.includes(type), `gzip_types must cover ${type}; the AdminLTE theme ships it uncompressed.`);
});
// text/html is always compressed by nginx when gzip is on and must not be listed
// again, but the BREACH trade-off it creates has to stay documented in the file.
assert(/BREACH/.test(nginxConf), 'The HTML-compression trade-off must stay documented next to the gzip block.');

// --- Static caching --------------------------------------------------------
assert(/location[^\n]*\/assets\/[\s\S]*?expires\s+max;/.test(nginxConf), 'Fingerprint-stable theme assets must keep far-future caching.');

// --- Kubernetes parity -----------------------------------------------------
// The image derives its Kubernetes config from the same file, so compression
// must survive that rewrite rather than being a Compose-only tuning.
assert(/default\.kubernetes\.conf/.test(nginxImage), 'The Kubernetes nginx config must still be generated from nginx/default.conf.');
const rewrittenAway = /-e\s+'\/gzip/.test(nginxImage);
assert(!rewrittenAway, 'The Kubernetes config rewrite must not strip the gzip settings.');

// --- PHP worker pool -------------------------------------------------------
const maxChildren = parseInt((fpmPool.match(/pm\.max_children\s*=\s*(\d+)/) || [])[1], 10);
assert(maxChildren >= 8, 'JobSeeker holds workers while polling Jenkins, so the pool must stay above the php image default of five.');
assert(/pm\.max_requests\s*=\s*\d+/.test(fpmPool), 'Long-lived workers must be recycled to bound memory growth.');

console.log('Response delivery performance checks passed.');
