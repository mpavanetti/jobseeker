const fs = require('fs');
const vm = require('vm');

const storage = {};
const document = {};

function jquery(argument) {
  if (typeof argument === 'function') {
    argument();
    return undefined;
  }

  return {
    length: 0,
    html() { return this; },
    on() { return this; },
    text() { return this; },
    trigger() { return this; }
  };
}

jquery.trim = (value) => String(value).trim();
jquery.extend = (deep, target, source) => JSON.parse(JSON.stringify(source));

const localStorage = {
  getItem(key) {
    return Object.prototype.hasOwnProperty.call(storage, key) ? storage[key] : null;
  },
  removeItem(key) {
    delete storage[key];
  },
  setItem(key, value) {
    storage[key] = String(value);
  }
};

const window = {
  baseURL: '/app/',
  jobseekerUserId: 7,
  jQuery: jquery,
  localStorage,
  location: {
    href: 'http://localhost/app/jobCreation?draft=requested-draft',
    search: '?draft=requested-draft'
  }
};

vm.runInNewContext(fs.readFileSync('assets/js/job-draft-cache.js', 'utf8'), {
  Array,
  Date,
  JSON,
  Math,
  String,
  URL,
  decodeURIComponent,
  document,
  encodeURIComponent,
  window
});

const cache = window.JobSeekerDraftCache;
const drafts = [
  {job_name: 'daily-etl', environment: 'DEV'},
  {job_name: 'report-refresh', environment: 'QA'}
];
const saved = cache.save(drafts, 1);

if (! saved.ok) {
  throw new Error('Expected draft cache save to succeed.');
}
if (! drafts[0]._cacheId || ! drafts[1]._cacheId) {
  throw new Error('Expected cached drafts to receive stable IDs.');
}
if (cache.read().drafts[1].job_name !== 'report-refresh') {
  throw new Error('Expected cached draft data to round-trip.');
}
if (cache.read().activeDraftId !== drafts[1]._cacheId) {
  throw new Error('Expected the active draft ID to be retained.');
}
if (cache.targetDraftId() !== 'requested-draft') {
  throw new Error('Expected the requested sidebar draft ID to be read from the URL.');
}

cache.removeByIds([drafts[0]._cacheId]);
if (cache.read().drafts.length !== 1 || cache.read().drafts[0].job_name !== 'report-refresh') {
  throw new Error('Expected a cached draft to be removable by its stable ID.');
}

cache.save(drafts, 1);
cache.removeByNames(['daily-etl'], false);
if (cache.read().drafts.length !== 1 || cache.read().drafts[0].job_name !== 'report-refresh') {
  throw new Error('Expected created jobs to be removed from the draft cache.');
}

cache.removeByNames(['report-refresh'], false);
if (cache.read() !== null) {
  throw new Error('Expected the cache to clear after its final draft was created.');
}

cache.save(drafts, 0);
cache.clear();
if (cache.read() !== null) {
  throw new Error('Expected the full draft cache to clear on request.');
}

console.log('Job draft cache tests passed.');
