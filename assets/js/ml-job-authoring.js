/* Drives application/views/includes/mlJobAuthoring.php (v2).
 *
 *   MlJobAuthoring.mount('#mlJobAuthoring', { bootstrap: {...}, editJobId?: N })
 *   MlJobAuthoring.mount('#mlJobAuthoring', { optionsUrl: baseURL + 'jobCreation/mlJobOptions' })
 */
(function (window, $) {
  'use strict';
  var ML = window.MlCommon, UI = window.MlUi;

  var DEFAULT_MAIN =
    '"""JobSeeker ML job."""\n\nimport jobseeker_ml as ml\n\n\n' +
    'def main() -> None:\n    # df = ml.datasets.training.read()\n' +
    '    ml.log_metric("placeholder", 1.0)\n    print("replace me")\n\n\n' +
    'if __name__ == "__main__":\n    main()\n';

  function ep(boot) {
    var b = (boot && boot.endpoints) || {};
    var base = ML.base + 'machine-learning/';
    return {
      introspect: b.introspect || base + 'jobs/introspect',
      save: b.save || base + 'jobs/save',
      run: b.run || base + 'jobs/run',
      status: b.status || base + 'jobs/status/',
      logs: b.logs || base + 'jobs/logs/',
      develop: b.develop || base + 'jobs/develop',
      buildImage: b.buildImage || base + 'jobs/build-image',
      imageStatus: b.imageStatus || base + 'jobs/image-status/',
      pick: b.pick || base + 'datasets/pick',
      workspace: base + 'jobs/workspace/'
    };
  }

  function roleFor(runType) {
    return ({ train: 'training', batch_infer: 'inference_input', evaluate: 'evaluation',
      preprocess: 'raw', tune: 'training' })[runType] || 'input';
  }

  function mount(selector, opts) {
    opts = opts || {};
    var $root = $(selector);
    if (!$root.length) { return; }

    function boot(data) {
      var $form = $root.find('.ml-authoring-form');
      var endpoints = ep(data);
      var runtimes = data.runtimes || [];
      var bindings = {};
      var files = { 'main.py': DEFAULT_MAIN, 'Dockerfile': '', 'requirements.txt': '', 'pyproject.toml': '' };
      var readOnly = {};
      var active = 'main.py';
      var hydrating = false;
      var console = null;

      fillSelect($form.find('[name=environment]'), data.environments || []);
      fillSelect($form.find('[name=runtime_key]'), runtimes.map(function (r) { return { value: r.runtime_key, label: r.display_name }; }));

      // --- sample gallery ---
      var $gallery = $root.find('.js-samples').empty();
      (data.samples || []).forEach(function (s) {
        $('<div class="ml-sample-card">')
          .append($('<h5>').text(s.name).append(' <span class="ml-badge ' + s.run_type + '">' + s.run_type + '</span>'))
          .append($('<p>').text(s.description || s.category))
          .on('click', function () {
            $form.find('[name=sample_key]').val(s.sample_key);
            $form.find('[name=runtime_key]').val(s.runtime_key);
            files['main.py'] = s.code || DEFAULT_MAIN;
            active = 'main.py';
            if (!$form.find('[name=name]').val()) { $form.find('[name=name]').val(s.name); }
            loadEditor(); introspect();
            ML.toast('info', 'Loaded sample "' + s.name + '"');
          }).appendTo($gallery);
      });

      // --- file tabs / editor ---
      var $tabs = $root.find('.js-tabs');
      var $code = $root.find('.js-code');

      function depTabName() {
        var m = $form.find('[name=dependency_mode]').val();
        return m === 'pyproject' ? 'pyproject.toml' : (m === 'requirements' ? 'requirements.txt' : null);
      }
      function tabList() {
        var list = ['main.py'];
        var dep = depTabName();
        if (dep) { list.push(dep); }
        list.push('Dockerfile');
        Object.keys(readOnly).forEach(function (k) { list.push(k); });
        Object.keys(files).forEach(function (k) {
          if (list.indexOf(k) === -1 && k !== 'requirements.txt' && k !== 'pyproject.toml') { list.push(k); }
        });
        return list;
      }
      function renderTabs() {
        $tabs.empty();
        tabList().forEach(function (name) {
          $('<div class="ml-editor-tab">').addClass(name === active ? 'active' : '')
            .addClass(readOnly[name] ? 'readonly' : '')
            .text(name).on('click', function () { showFile(name); }).appendTo($tabs);
        });
      }
      function showFile(name) {
        if (name !== active) { stash(); }
        active = name;
        hydrating = true;
        var val = readOnly[name] != null ? readOnly[name] : (files[name] || '');
        $code.val(val).prop('readonly', !!readOnly[name]);
        hydrating = false;
        renderTabs();
      }
      function stash() {
        if (hydrating || readOnly[active] != null) { return; }
        files[active] = $code.val();
      }
      // Load the current file(s) into the editor without stashing (used after
      // a sample pick / hydrate that has already set `files`).
      function loadEditor() {
        hydrating = true;
        var val = readOnly[active] != null ? readOnly[active] : (files[active] || '');
        $code.val(val).prop('readonly', !!readOnly[active]);
        hydrating = false;
        renderTabs();
      }
      $code.on('input', function () {
        if (readOnly[active]) { return; }
        files[active] = $code.val();
        if (active === 'main.py') { introspect(); }
      });
      $form.find('[name=dependency_mode]').on('change', function () { if (active !== 'main.py') { showFile('main.py'); } else { renderTabs(); } });

      // --- introspection ---
      var t = null;
      function introspect() {
        clearTimeout(t);
        t = setTimeout(function () {
          ML.post(endpoints.introspect, {
            code: files['main.py'], application_args: $form.find('[name=application_args]').val(),
            sample_key: $form.find('[name=sample_key]').val()
          }).done(function (r) {
            if (!r || !r.ok) { return; }
            var type = $root.find('.js-run-type-override').val() || r.run_type;
            $root.find('.js-detected-type').attr('class', 'ml-badge js-detected-type ' + type).text(type);
            $root.find('.js-detected-note').text((r.signals || []).slice(0, 3).join(' · '));
            $root.find('.js-confidence').css('width', Math.round((r.confidence || 0) * 100) + '%');
          });
        }, 400);
      }
      $root.find('.js-run-type-override').on('change', introspect);
      $form.find('[name=application_args]').on('input', introspect);

      // --- dataset picker ---
      function renderBound() {
        var $b = $root.find('.js-bound').empty();
        var keys = Object.keys(bindings);
        if (!keys.length) { $b.append('<span class="ml-muted">Nothing bound.</span>'); }
        keys.forEach(function (role) {
          var bd = bindings[role];
          $('<div class="ml-bound-item">')
            .html('<span class="ml-bound-role">' + ML.escape(role) + '</span> &larr; ' +
              ML.escape(bd.dataset_key) + ' <span class="ml-muted">v' + ML.escape(bd.version) + '</span> ' +
              '<a href="#" class="text-red js-unbind" data-role="' + ML.escape(role) + '" style="float:right">remove</a>')
            .appendTo($b);
        });
        $b.find('.js-unbind').on('click', function (e) {
          e.preventDefault();
          delete bindings[$(this).data('role')]; renderBound();
        });
      }
      function loadPicker() {
        ML.get(endpoints.pick).done(function (r) {
          var $p = $root.find('.js-ds-picker').empty();
          (r && r.datasets || []).forEach(function (ds) {
            var cols = (ds.schema || []).map(function (c) { return c.name; }).slice(0, 6).join(', ');
            $('<div class="ml-ds-row">')
              .html('<span><span class="ml-ds-name">' + ML.escape(ds.name) + '</span>' +
                '<div class="ml-ds-meta">' + ML.escape(ds.key) + ' &middot; v' + ds.latest_version +
                ' &middot; ' + (ds.rows != null ? ds.rows + ' rows' : '?') + (cols ? ' &middot; ' + ML.escape(cols) : '') + '</div></span>' +
                '<button type="button" class="btn btn-xs btn-default js-bind">Bind</button>')
              .find('.js-bind').on('click', function () { bindDataset(ds); }).end()
              .appendTo($p);
          });
          if (!$p.children().length) { $p.html('<div class="ml-muted" style="padding:10px">No datasets. Register one under ML Datasets.</div>'); }
        });
      }
      function bindDataset(ds) {
        var currentType = $root.find('.js-detected-type').text() || 'train';
        var role = window.prompt('Bind "' + ds.name + '" as which role?', roleFor(currentType));
        if (!role) { return; }
        role = role.replace(/[^a-z0-9_]/gi, '_').toLowerCase();
        var ver = window.prompt('Version (number, or "latest")', 'latest') || 'latest';
        bindings[role] = { dataset_key: ds.key, version: ver, direction: 'input' };
        renderBound();
        var cols = (ds.schema || []).map(function (c) { return c.name + '(' + (c.type || '?') + ')'; }).slice(0, 8).join(', ');
        var snippet = '    ' + role + ' = ml.datasets.' + role + '.read()   # ' + ds.key + ' v' + ds.latest_version +
          (ds.rows != null ? ' · ' + ds.rows + ' rows' : '') + (cols ? ' · ' + cols : '') + '\n';
        files['main.py'] = insertAfterDef(files['main.py'], snippet);
        if (active === 'main.py') { loadEditor(); }
        introspect();
      }

      // --- image build ---
      function setImagePill(state) {
        state = state || 'none';
        $root.find('.js-image-pill').attr('class', 'ml-image-pill js-image-pill ' + state).text('image: ' + state);
      }
      $root.find('.js-build').on('click', function () {
        var id = $form.find('[name=id]').val();
        if (!id) { ML.toast('error', 'Save the job first.'); return; }
        openConsole().setStatus('BUILDING'); setImagePill('building');
        ML.post(endpoints.buildImage, { id: id }).done(function (r) {
          console.setLog(r && r.log || '');
          console.setStatus(r && r.ok ? 'READY' : 'FAILED');
          setImagePill(r && r.ok ? 'ready' : 'failed');
          ML.toast(r && r.ok ? 'success' : 'error', r && r.message);
        });
      });

      // --- open editor ---
      $root.find('.js-open-editor').on('click', function () {
        var id = $form.find('[name=id]').val();
        if (!id) { ML.toast('error', 'Save the job first.'); return; }
        var $n = $root.find('.js-editor-note').text('Starting editor…');
        ML.post(endpoints.develop, { id: id }).done(function (r) {
          if (r && r.ok) {
            $n.text(r.ready ? '' : 'Editor is starting; the tab will load shortly.');
            window.open(r.url, '_blank', 'noopener');
          } else { $n.text((r && r.message) || 'Could not open the editor.'); }
        });
      });

      // --- collect + save + run ---
      function collect() {
        stash();
        var p = {};
        $form.serializeArray().forEach(function (f) { p[f.name] = f.value; });
        p.run_type = $root.find('.js-run-type-override').val() || '';
        p.main_py = files['main.py'];
        p.dockerfile = files['Dockerfile'] || '';
        p.requirements_txt = files['requirements.txt'] || '';
        p.pyproject_text = files['pyproject.toml'] || '';
        p.dataset_bindings_json = JSON.stringify(bindings);
        return p;
      }
      function openConsole() {
        $root.find('.js-run-panel').show();
        if (!console) { console = UI.console($root.find('.js-run-console')); }
        return console;
      }
      function save(afterRedirectOk) {
        var $s = $root.find('.js-save-status').text('Saving…');
        var wasNew = !$form.find('[name=id]').val();
        return ML.post(endpoints.save, collect()).done(function (r) {
          if (r && r.ok) {
            $form.find('[name=id]').val(r.id);
            $s.text(r.message || 'Saved.');
            setImagePill(r.image_state || 'none');
            if (r.run_type) {
              $root.find('.js-detected-type').attr('class', 'ml-badge js-detected-type ' + r.run_type).text(r.run_type);
            }
            ML.toast('success', 'Job saved.');
            // On the dedicated ML Jobs screen a fresh job should appear in the
            // list; reload into edit mode. (Not when embedded in Create Job.)
            if (afterRedirectOk && wasNew && !opts.editJobId &&
                /\/machine-learning\/jobs$/.test(location.pathname)) {
              setTimeout(function () { location.href = ML.base + 'machine-learning/jobs?id=' + r.id; }, 500);
            }
          } else {
            $s.text((r && r.message) || 'Save failed.');
            ML.toast('error', (r && r.message) || 'Save failed.');
          }
        }).fail(function (xhr) {
          $s.text('Save failed.');
          ML.toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Save failed.');
        });
      }
      function testRun() {
        var id = $form.find('[name=id]').val();
        if (!id) { return; }
        var c = openConsole();
        c.clear(); c.setStatus('QUEUED'); c.setLog('Starting test run…');
        ML.post(endpoints.run, { id: id, preview: '1' }).done(function (r) {
          if (!r || !r.ok || !r.run) { c.setLog((r && r.message) || 'Run failed to start.'); c.setStatus('FAILED'); return; }
          var runId = r.run.id;
          ML.poll(function () { return ML.get(endpoints.status + runId); }, {
            interval: 2500,
            done: function (st) {
              if (st && st.ok) {
                c.setStatus(st.run.status);
                c.setMetrics(st.metrics || {});
              }
              ML.get(endpoints.logs + runId).done(function (lg) { if (lg && lg.ok) { c.setLog(lg.logs); } });
              return st && st.run && st.run.terminal;
            }
          });
        });
      }

      $form.on('submit', function (e) { e.preventDefault(); save(true); });
      $root.find('.js-save-run').on('click', function () { $.when(save(false)).done(testRun); });

      // --- hydrate ---
      loadEditor(); loadPicker(); renderBound();

      if (opts.editJobId) {
        ML.get(ML.base + 'machine-learning/jobs/get/' + opts.editJobId).done(function (r) {
          if (!r || !r.ok) { return; }
          var j = r.job, ws = r.workspace || {};
          ['name', 'job_key', 'environment', 'runtime_key', 'group_name', 'entrypoint', 'dependency_mode',
           'cpu_limit', 'memory_limit_mb', 'timeout_seconds', 'schedule_cron', 'application_args',
           'params_json', 'env_json'].forEach(function (k) {
            if (j[k] != null) { $form.find('[name=' + k + ']').val(j[k]); }
          });
          $form.find('[name=id]').val(j.id);
          $form.find('[name=sample_key]').val(j.sample_key || '');
          files['main.py'] = ws.main_py || j.inline_code || DEFAULT_MAIN;
          files['requirements.txt'] = ws.requirements_txt || j.requirements_txt || '';
          files['pyproject.toml'] = ws.pyproject_text || j.pyproject_text || '';
          files['Dockerfile'] = ws.dockerfile || j.dockerfile || '';
          try { bindings = JSON.parse(j.dataset_bindings_json || '{}') || {}; } catch (e) { bindings = {}; }
          setImagePill(j.image_state);
          active = 'main.py'; loadEditor(); renderBound(); introspect();
        });
      }

      function fillSelect($sel, values) {
        $sel.empty();
        (values || []).forEach(function (v) {
          var val = typeof v === 'object' ? v.value : v;
          var label = typeof v === 'object' ? v.label : v;
          $sel.append($('<option>').attr('value', val).text(label));
        });
      }
    }

    if (opts.bootstrap) {
      boot(opts.bootstrap);
    } else if (opts.optionsUrl) {
      ML.get(opts.optionsUrl).done(function (r) {
        if (r && r.ok) { boot(r); } else { $root.html('<p class="ml-muted">ML platform options unavailable.</p>'); }
      });
    }
  }

  function insertAfterDef(code, snippet) {
    var lines = code.split('\n');
    for (var i = 0; i < lines.length; i++) {
      if (/^def main\(/.test(lines[i])) {
        lines.splice(i + 1, 0, snippet.replace(/\n$/, ''));
        return lines.join('\n');
      }
    }
    return snippet + code;
  }

  window.MlJobAuthoring = { mount: mount };
})(window, jQuery);
