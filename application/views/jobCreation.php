<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.css">
<style>
  .dropzone {
    background: white;
    border-radius: 5px;
    border: 2px dashed rgb(0, 135, 247);
    border-image: none;
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
  }

  .checkbox input {

    transform: scale(1.5);
  }
  .checkbox label {

    font-size: 16px;
  }

  .job-creation-workspace {
    max-width: none;
    width: 100%;
  }

  .job-form-row {
    align-items: stretch;
    display: flex;
    flex-wrap: wrap;
  }

  .job-form-row:before,
  .job-form-row:after {
    display: none;
  }

  .job-form-row > [class*="col-"] {
    align-items: stretch;
    display: flex;
    float: none;
    margin-bottom: 20px;
  }

  .job-form-row > [class*="col-"] > .job-form-card,
  .job-form-row > [class*="col-"] > .job-config-canvas {
    flex: 1 1 auto;
    margin-bottom: 0;
    min-height: 100%;
  }

  .job-form-card {
    display: flex;
    flex-direction: column;
    width: 100%;
  }

  .job-form-card .box-body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
  }

  @media (min-width: 1200px) {
    .job-form-row > .job-input-column {
      flex: 0 0 clamp(390px, 30%, 470px);
      max-width: 470px;
      width: clamp(390px, 30%, 470px);
    }

    .job-form-row > .job-config-column {
      flex: 1 1 calc(100% - clamp(390px, 30%, 470px));
      max-width: calc(100% - clamp(390px, 30%, 470px));
      width: calc(100% - clamp(390px, 30%, 470px));
    }
  }

  @media (min-width: 1600px) {
    .job-form-row > .job-input-column {
      flex-basis: clamp(440px, 28%, 540px);
      max-width: 540px;
      width: clamp(440px, 28%, 540px);
    }

    .job-form-row > .job-config-column {
      flex-basis: calc(100% - clamp(440px, 28%, 540px));
      max-width: calc(100% - clamp(440px, 28%, 540px));
      width: calc(100% - clamp(440px, 28%, 540px));
    }
  }

  .job-option-grid {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr;
  }

  .job-config-options {
    border-bottom: 1px solid #edf1f5;
    margin-bottom: 14px;
    padding-bottom: 14px;
  }

  .job-config-options-header {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .job-config-options-header strong {
    color: #3c4b55;
    font-size: 15px;
  }

  .job-config-options .job-option-grid {
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  }

  .job-option-card {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    color: #444;
    cursor: pointer;
    display: block;
    margin: 0;
    min-height: 76px;
    padding: 10px 10px 10px 42px;
    position: relative;
    transition: background-color .12s ease, border-color .12s ease, box-shadow .12s ease;
  }

  .job-option-card:hover {
    background: #f9fafc;
    border-color: #9ab6c8;
  }

  .job-option-card.active {
    background: #f4fbff;
    border-color: #3c8dbc;
    box-shadow: inset 4px 0 0 #3c8dbc;
  }

  .job-option-card input {
    opacity: 0;
    position: absolute;
  }

  .job-option-icon {
    color: #3c8dbc;
    font-size: 19px;
    left: 12px;
    position: absolute;
    top: 11px;
  }

  .job-option-title {
    display: block;
    font-weight: 700;
    line-height: 1.25;
    padding-right: 66px;
  }

  .job-option-detail {
    color: #777;
    display: block;
    font-size: 12px;
    line-height: 1.35;
    margin-top: 5px;
  }

  .job-option-state {
    position: absolute;
    right: 10px;
    top: 10px;
  }

  .job-option-state .label {
    display: none;
  }

  .job-option-card.active .job-option-state .label {
    display: inline-block;
  }

  .linux-execution-mode-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-bottom: 16px;
  }

  .linux-execution-mode {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 5px;
    color: #3c4b55;
    min-height: 82px;
    padding: 12px 12px 12px 46px;
    position: relative;
    text-align: left;
    transition: background-color .12s ease, border-color .12s ease, box-shadow .12s ease;
    width: 100%;
  }

  .linux-execution-mode:hover,
  .linux-execution-mode:focus {
    background: #f9fafc;
    border-color: #9ab6c8;
    outline: 0;
  }

  .linux-execution-mode.active {
    background: #f4fbff;
    border-color: #3c8dbc;
    box-shadow: inset 4px 0 0 #3c8dbc;
  }

  .linux-execution-mode i {
    color: #3c8dbc;
    font-size: 20px;
    left: 14px;
    position: absolute;
    top: 14px;
  }

  .linux-execution-mode strong,
  .linux-execution-mode span {
    display: block;
  }

  .linux-execution-mode span {
    color: #777;
    font-size: 12px;
    line-height: 1.35;
    margin-top: 5px;
  }

  .linux-execution-section {
    background: #f9fafc;
    border: 1px solid #e2e7ee;
    border-radius: 5px;
    margin-bottom: 16px;
    padding: 12px;
  }

  .linux-execution-section-header {
    margin-bottom: 10px;
  }

  .linux-execution-section-header strong,
  .linux-execution-section-header span {
    display: block;
  }

  .linux-execution-section-header strong {
    color: #243b53;
  }

  .linux-execution-section-header span {
    color: #777;
    font-size: 12px;
    line-height: 1.35;
    margin-top: 3px;
  }

  .linux-execution-choice-grid {
    display: grid;
    gap: 9px;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
  }

  .linux-execution-choice {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    color: #3c4b55;
    min-height: 54px;
    padding: 9px 10px;
    text-align: left;
    transition: background-color .12s ease, border-color .12s ease, box-shadow .12s ease;
    width: 100%;
  }

  .linux-execution-choice:hover,
  .linux-execution-choice:focus {
    background: #fff;
    border-color: #9ab6c8;
    outline: 0;
  }

  .linux-execution-choice.active {
    border-color: #3c8dbc;
    box-shadow: inset 3px 0 0 #3c8dbc;
  }

  .linux-execution-choice i {
    color: #3c8dbc;
    margin-right: 6px;
  }

  .linux-execution-choice strong,
  .linux-execution-choice span {
    display: block;
  }

  .linux-execution-choice span {
    color: #777;
    font-size: 12px;
    line-height: 1.3;
    margin-top: 4px;
  }

  .linuxLegacyExecutionFields {
    display: none !important;
  }

  .job-config-action-menu {
    align-items: center;
    background: #f9fafc;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
    min-height: 38px;
    padding: 7px;
  }

  .job-config-action-menu-compact {
    background: transparent;
    border: 0;
    justify-content: flex-end;
    margin-top: 6px;
    min-height: 0;
    padding: 0;
  }

  .job-config-chip {
    border-color: #b8c7ce;
    color: #3c4b55;
    font-weight: 600;
  }

  .job-config-chip i {
    color: #3c8dbc;
    margin-right: 4px;
  }

  .job-config-chip:hover,
  .job-config-chip:focus,
  .job-config-chip.active {
    background: #ecf7ff;
    border-color: #3c8dbc;
    color: #2b536a;
  }

  .job-config-menu-empty {
    color: #777;
    font-size: 12px;
  }

  .job-form-actions {
    align-items: center;
    border-top: 1px solid #edf1f5;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
  }

  .job-bulk-drafts {
    border-top: 1px solid #edf1f5;
    margin-top: 14px;
    padding-top: 14px;
  }

  .job-bulk-drafts-summary {
    align-items: flex-start;
    display: flex;
    gap: 10px;
    justify-content: space-between;
  }

  .job-bulk-drafts-summary label {
    margin-bottom: 2px;
  }

  .job-bulk-drafts-summary .help-block {
    margin: 0;
  }

  .job-bulk-drafts-panel {
    background: #fbfcfd;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    display: none;
    margin-top: 10px;
    padding: 10px;
  }

  .job-bulk-drafts-panel textarea {
    resize: vertical;
  }

  .linux-code-editor {
    background: #1e1e1e;
    border: 1px solid #3c3c3c;
    border-radius: 4px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    overflow: hidden;
  }

  .linux-code-editor-bar {
    align-items: center;
    background: #252526;
    border-bottom: 1px solid #333;
    color: #cccccc;
    display: flex;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    justify-content: space-between;
    min-height: 32px;
  }

  .linux-code-editor-tab {
    align-items: center;
    background: #1e1e1e;
    border-right: 1px solid #333;
    display: inline-flex;
    gap: 6px;
    height: 32px;
    padding: 0 12px;
  }

  .linux-code-editor-tab i {
    color: #75beff;
  }

  .linux-code-editor-actions {
    align-items: center;
    display: inline-flex;
    gap: 6px;
    padding-right: 8px;
  }

  .linux-code-editor-actions .btn {
    background: transparent;
    border: 0;
    color: #cccccc;
    padding: 2px 5px;
  }

  .linux-code-editor-actions .btn:hover,
  .linux-code-editor-actions .btn:focus {
    background: #37373d;
    color: #fff;
  }

  .python-inline-workspace {
    background: #252526;
    display: flex;
    min-height: 320px;
    min-width: 0;
    width: 100%;
  }

  .python-inline-sidebar {
    border-right: 1px solid #333;
    color: #cccccc;
    flex: 0 0 220px;
    min-width: 0;
  }

  .python-inline-sidebar-header {
    align-items: center;
    border-bottom: 1px solid #333;
    display: flex;
    justify-content: space-between;
    min-height: 35px;
    padding: 0 8px 0 10px;
  }

  .python-inline-sidebar-title {
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .python-inline-sidebar-actions {
    display: flex;
    gap: 4px;
  }

  .python-inline-sidebar-actions .btn {
    background: transparent;
    border: 0;
    color: #cccccc;
    padding: 2px 5px;
  }

  .python-inline-sidebar-actions .btn:hover,
  .python-inline-sidebar-actions .btn:focus {
    background: #37373d;
    color: #fff;
  }

  .python-inline-file-list {
    height: 100%;
    max-height: 314px;
    overflow: auto;
    padding: 6px 0;
  }

  .python-inline-file-row {
    align-items: stretch;
    display: flex;
  }

  .python-inline-file,
  .python-inline-folder,
  .python-inline-file-remove {
    align-items: center;
    background: transparent;
    border: 0;
    color: #cccccc;
    display: flex;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    gap: 7px;
    min-height: 28px;
    padding: 0 10px;
    text-align: left;
  }

  .python-inline-file {
    flex: 1 1 auto;
    min-width: 0;
  }

  .python-inline-file span,
  .python-inline-folder span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .python-inline-file.active {
    background: #37373d;
    color: #fff;
  }

  .python-inline-file i,
  .python-inline-folder i {
    color: #75beff;
    flex: 0 0 auto;
  }

  .python-inline-folder i {
    color: #d7ba7d;
  }

  .python-inline-file-remove {
    color: #858585;
    flex: 0 0 28px;
    justify-content: center;
    padding: 0;
  }

  .python-inline-file-remove:hover,
  .python-inline-file-remove:focus {
    background: #4b2f32;
    color: #f48771;
  }

  .python-inline-main {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-width: 0;
  }

  .python-inline-tabs {
    border-bottom: 1px solid #333;
    border-right: 1px solid #333;
    display: flex;
    flex: 0 0 auto;
    min-height: 35px;
    overflow-x: auto;
  }

  .python-inline-tab {
    align-items: center;
    background: transparent;
    border: 0;
    border-right: 1px solid #333;
    color: #cccccc;
    display: flex;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    gap: 7px;
    min-height: 35px;
    padding: 0 8px 0 10px;
    text-align: left;
    white-space: nowrap;
  }

  .python-inline-tab.active {
    background: #1e1e1e;
    border-top: 2px solid #007acc;
    color: #fff;
    padding-top: 0;
  }

  .python-inline-tab i {
    color: #75beff;
  }

  .python-inline-tab-close {
    align-items: center;
    color: #858585;
    display: inline-flex;
    height: 18px;
    justify-content: center;
    margin-left: 4px;
    width: 18px;
  }

  .python-inline-tab-close:hover,
  .python-inline-tab-close:focus {
    background: #37373d;
    color: #fff;
  }

  .python-inline-empty-state {
    align-items: center;
    color: #858585;
    display: none;
    flex: 1 1 auto;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    justify-content: center;
    min-height: 286px;
  }

  .python-inline-empty-state.active {
    display: flex;
  }

  .python-inline-editor-panel {
    display: none;
    flex: 1 1 auto;
    min-width: 0;
  }

  .python-inline-editor-panel.active {
    display: flex;
    flex-direction: column;
  }

  .python-inline-editor-panel .linux-code-editor {
    border: 0;
    border-radius: 0;
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 100%;
  }

  .linux-code-editor-meta {
    color: #858585;
    padding: 0 12px;
  }

  .linux-code-editor-body {
    align-items: stretch;
    display: flex;
    flex: 1 1 auto;
    min-height: 240px;
    min-width: 0;
  }

  .linux-code-editor-source {
    display: flex;
    flex: 1 1 auto;
    min-height: 240px;
    min-width: 0;
    position: relative;
  }

  .python-syntax-highlight {
    background: #1e1e1e;
    bottom: 0;
    color: #d4d4d4;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 13px;
    left: 0;
    line-height: 20px;
    margin: 0;
    max-width: none;
    min-height: 240px;
    min-width: 100%;
    overflow: hidden;
    padding: 10px 12px;
    pointer-events: none;
    position: absolute;
    right: 0;
    top: 0;
    white-space: pre;
    word-break: normal;
    word-wrap: normal;
  }

  .python-syntax-highlight .py-keyword { color: #569cd6; }
  .python-syntax-highlight .py-builtin { color: #4ec9b0; }
  .python-syntax-highlight .py-string { color: #ce9178; }
  .python-syntax-highlight .py-comment { color: #6a9955; }
  .python-syntax-highlight .py-number { color: #b5cea8; }
  .python-syntax-highlight .py-decorator { color: #dcdcaa; }

  .linux-code-editor-lines {
    background: #1b1b1b;
    border-right: 1px solid #333;
    color: #858585;
    flex: 0 0 44px;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 13px;
    line-height: 20px;
    overflow: hidden;
    padding: 10px 8px;
    text-align: right;
    user-select: none;
  }

  .linux-code-editor textarea.form-control {
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    color: #d4d4d4;
    flex: 1 1 auto;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 13px;
    line-height: 20px;
    min-height: 240px;
    min-width: 100%;
    padding: 10px 12px;
    resize: vertical;
    white-space: pre;
    width: 100%;
  }

  .linux-code-editor textarea.python-highlighted-input {
    color: transparent;
    caret-color: #ffffff;
    overflow: auto;
    position: relative;
    resize: none;
    z-index: 1;
  }

  .linux-code-editor textarea.python-highlighted-input::selection {
    background: rgba(38, 79, 120, 0.78);
    color: transparent;
  }

  .linux-code-editor textarea.form-control:focus {
    box-shadow: inset 0 0 0 1px #007acc;
    outline: 0;
  }

  .linux-code-editor textarea.form-control::placeholder {
    color: #6a9955;
  }

  .linux-code-editor-status {
    align-items: center;
    background: #007acc;
    color: #fff;
    display: flex;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 11px;
    justify-content: flex-end;
    min-height: 22px;
    padding: 0 10px;
  }

  .linux-code-editor-status span {
    margin-left: 12px;
  }

  .python-lint-panel {
    background: #252526;
    border-top: 1px solid #333;
    color: #cccccc;
    display: none;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    max-height: 120px;
    overflow: auto;
    padding: 8px 12px;
  }

  .python-lint-panel.active {
    display: block;
  }

  .python-lint-panel strong {
    color: #dcdcaa;
  }

  .python-lint-panel ul {
    margin: 6px 0 0;
    padding-left: 18px;
  }

  .python-lint-panel li {
    margin-bottom: 3px;
  }

  .python-lint-panel .python-lint-warning { color: #d7ba7d; }
  .python-lint-panel .python-lint-error { color: #f48771; }

  .python-preview-panel {
    background: #1e1e1e;
    border-top: 1px solid #333;
    color: #cccccc;
    display: none;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    max-height: 220px;
    overflow: auto;
    padding: 10px 12px;
  }

  .python-preview-panel.active {
    display: block;
  }

  .python-preview-panel strong {
    color: #dcdcaa;
    display: block;
    margin-bottom: 6px;
  }

  .python-preview-panel.python-preview-success strong {
    color: #89d185;
  }

  .python-preview-panel.python-preview-failed strong {
    color: #f48771;
  }

  .python-preview-panel pre {
    background: transparent;
    border: 0;
    color: inherit;
    font: inherit;
    margin: 0;
    padding: 0;
    white-space: pre-wrap;
  }

  .job-batch-tools {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
  }

  .job-batch-preview {
    color: #777;
    margin-top: 8px;
  }

  .job-draft-workspace {
    border: 1px solid #d2d6de;
    border-radius: 4px;
    margin-top: 12px;
  }

  .job-draft-toolbar {
    align-items: center;
    background: #f9fafc;
    border-bottom: 1px solid #d2d6de;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px;
  }

  .job-draft-tabs {
    border-bottom: 1px solid #d2d6de;
    padding: 0 10px;
  }

  .job-draft-tabs > li > a {
    color: #444;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-draft-comparison {
    margin: 0;
  }

  .job-draft-comparison th,
  .job-draft-comparison td {
    vertical-align: top !important;
  }

  .job-draft-comparison .active-draft-row {
    background: #ecf7ff;
  }

  .job-creation-review {
    background: #f9fafc;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    margin-top: 16px;
    padding: 12px;
  }

  .job-creation-review h4 {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 8px;
  }

  .job-creation-review dl {
    margin-bottom: 0;
  }

  .job-creation-review dt {
    color: #777;
    float: left;
    width: 130px;
  }

  .job-creation-review dd {
    margin-left: 140px;
    margin-bottom: 6px;
    word-break: break-word;
  }

  .job-creation-review .label {
    margin-right: 4px;
  }

  .job-form-card .job-creation-review {
    background: transparent;
    border: 0;
    border-radius: 0;
    border-top: 1px solid #edf1f5;
    padding: 14px 0 0;
  }

  .job-config-canvas {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    margin: 0;
    min-height: 100%;
    overflow: visible;
    padding: 0 14px 14px;
    width: 100%;
  }

  .job-config-header {
    align-items: center;
    background: #fff;
    border-bottom: 1px solid #edf1f5;
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    justify-content: space-between;
    margin: 0 -14px 14px;
    padding: 14px;
  }

  .job-config-header-actions {
    flex: 0 0 auto;
    text-align: right;
  }

  .job-config-header h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
  }

  .job-config-header p {
    color: #777;
    margin: 4px 0 0;
  }

  .job-config-empty-state {
    border: 1px dashed #d2d6de;
    color: #777;
    padding: 28px 18px;
    text-align: center;
  }

  .job-config-workbench {
    align-items: stretch;
    display: grid;
    flex: 1 1 auto;
    gap: 12px;
    grid-template-columns: 175px minmax(0, 1fr);
    min-height: 420px;
  }

  .job-config-side {
    background: #f9fafc;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    padding: 10px;
  }

  .job-config-side-title {
    color: #777;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    margin-bottom: 8px;
    text-transform: uppercase;
  }

  .job-config-rail {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .job-config-rail .job-config-chip {
    border-radius: 4px;
    overflow: hidden;
    padding: 8px 9px;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
  }

  .job-config-panel-empty-note {
    border: 1px dashed #d2d6de;
    color: #777;
    display: none;
    padding: 28px 18px;
    text-align: center;
  }

  .job-config-workbench.is-empty {
    display: none;
  }

  .job-config-panel-stack > .row {
    margin-left: 0;
    margin-right: 0;
  }

  .job-config-panel-inactive {
    display: none !important;
  }

  .job-config-canvas .box {
    border-top-width: 3px;
    box-shadow: none;
    margin-bottom: 14px;
  }

  .job-config-canvas .box-header {
    background: #f9fafc;
  }

  .job-config-canvas .box-body {
    max-height: calc(100vh - 245px);
    overflow-y: auto;
    padding: 16px 18px !important;
  }

  .job-config-canvas .form-group {
    margin-bottom: 12px;
  }

  .job-config-canvas select.form-control,
  .job-config-canvas .select2-container {
    max-width: 100%;
    width: 100% !important;
  }

  .job-config-canvas .box.job-config-highlight {
    box-shadow: 0 0 0 2px rgba(60, 141, 188, .22);
  }

  .job-config-canvas .box-header .btn-box-tool[data-widget="collapse"] {
    display: none;
  }

  .job-config-canvas #runWinCommand > .col-lg-6,
  .job-config-canvas #runlinuxCommand > .col-lg-6,
  .job-config-canvas #build > .col-lg-6,
  .job-config-canvas #enableEmail > .col-lg-6,
  .job-config-canvas #abortIfStuck > .col-lg-6,
  .job-config-canvas #runJob > .col-lg-6,
  .job-config-canvas #editableEmail > .col-lg-6,
  .job-config-canvas #environmentBox > .col-lg-6 {
    float: none;
    width: 100%;
  }

  .job-config-canvas #runJob > .col-lg-6 {
    display: flex;
  }

  .job-config-canvas #runJob .box {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: clamp(620px, calc(100vh - 255px), 960px);
  }

  .job-config-canvas #runJob .box-body.job-flow-panel {
    flex: 1 1 auto;
    max-height: none;
  }

  @media (max-width: 600px) {
    .job-config-header-actions {
      text-align: left;
    }

    .job-config-action-menu-compact {
      justify-content: flex-start;
    }

    .job-config-workbench {
      grid-template-columns: 1fr;
      min-height: 0;
    }

    .job-config-rail {
      flex-direction: row;
      flex-wrap: wrap;
    }

    .job-config-rail .job-config-chip {
      width: auto;
    }

    .job-config-canvas .box-body {
      max-height: none;
    }

    .python-inline-workspace {
      flex-direction: column;
    }

    .python-inline-tabs {
      border-bottom: 1px solid #333;
      border-right: 0;
      display: flex;
      flex: 0 0 auto;
    }

    .python-inline-tab {
      border-bottom: 0;
      border-right: 1px solid #333;
      justify-content: center;
    }

    .job-creation-review dt {
      float: none;
      width: auto;
    }

    .job-creation-review dd {
      margin-left: 0;
    }

    .job-flow-layout {
      grid-template-columns: 1fr;
    }

    .job-flow-palette {
      border-bottom: 1px solid #d7dde5;
      border-right: 0;
    }

    .job-flow-palette-list {
      max-height: 260px;
    }

    .job-flow-canvas {
      grid-template-columns: 1fr;
    }

    .job-flow-canvas:before {
      bottom: 8%;
      height: auto;
      left: 50%;
      right: auto;
      top: 8%;
      width: 3px;
    }

    .linux-execution-mode-grid {
      grid-template-columns: 1fr;
    }
  }

  #build .box-body,
  #runWinCommand .box-body,
  #runlinuxCommand .box-body,
  #enableEmail .box-body,
  #abortIfStuck .box-body,
  #runJob .box-body,
  #environmentBox .box-body,
  #editableEmail .box-body {
    max-height: 540px;
    overflow-y: auto;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded > .col-lg-6 {
    display: flex;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .box {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: clamp(650px, calc(100vh - 255px), 980px);
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .box-body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    max-height: none;
    min-height: 0;
    overflow: hidden;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonSourceForm,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonRuntimeForm,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonInlineSourceForm {
    flex: 0 0 auto;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonInlineSourceForm {
    display: flex !important;
    flex: 1 1 auto;
    min-height: 0;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonInlineSourceForm > .col-md-12,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .pythonInlineSourceForm .form-group {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-inline-workspace {
    flex: 1 1 auto;
    min-height: clamp(430px, calc(100vh - 450px), 720px);
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-inline-file-list {
    flex: 1 1 auto;
    max-height: none;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-inline-main,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-inline-editor-panel,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor-body,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor-source,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor textarea.form-control,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-syntax-highlight {
    min-height: 0;
  }

  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor-source,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .linux-code-editor textarea.form-control,
  .job-config-canvas #runlinuxCommand.python-inline-expanded .python-syntax-highlight {
    height: 100%;
  }

  @media (max-width: 600px) {
    .job-config-canvas #runlinuxCommand.python-inline-expanded .box {
      min-height: 720px;
    }
  }

  #myTable tr.jobRecentlySaved > td {
    background-color: #fff8d9 !important;
  }

  #myTable tr.jobRecentlySaved > td:first-child {
    border-left: 4px solid #00a65a;
  }

  #myTable th.available-job-actions-cell,
  #myTable td.available-job-actions-cell {
    min-width: 230px;
    white-space: nowrap;
  }

  #myTable .available-job-actions {
    align-items: center;
    display: inline-flex;
    flex-wrap: nowrap;
    vertical-align: middle;
  }

  #myTable .available-job-actions > .btn {
    float: none;
  }

  .job-flow-panel {
    background: #f6f8fb;
    display: flex;
    min-height: clamp(560px, calc(100vh - 300px), 920px);
    overflow: hidden !important;
    padding: 0 !important;
  }

  .job-flow-builder {
    background: #fff;
    border: 1px solid #d7dde5;
    border-radius: 6px;
    box-shadow: 0 12px 28px rgba(34, 45, 50, .08);
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    width: 100%;
  }

  .job-flow-toolbar {
    align-items: center;
    background: linear-gradient(135deg, #16222d 0%, #253746 56%, #32506a 100%);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: space-between;
    padding: 14px 16px;
  }

  .job-flow-toolbar h4 {
    font-size: 16px;
    font-weight: 700;
    margin: 2px 0 0;
  }

  .job-flow-kicker {
    color: #9fd7c2;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .job-flow-toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .job-flow-toolbar-actions .btn {
    border-color: rgba(255, 255, 255, .38);
    color: #fff;
  }

  .job-flow-toolbar-actions .btn:hover,
  .job-flow-toolbar-actions .btn:focus {
    background: rgba(255, 255, 255, .14);
    color: #fff;
  }

  .job-flow-layout {
    display: grid;
    flex: 1 1 auto;
    grid-template-columns: 260px minmax(0, 1fr);
    min-height: 0;
  }

  .job-flow-palette {
    background: #fbfcfd;
    border-right: 1px solid #d7dde5;
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
  }

  .job-flow-palette-header {
    border-bottom: 1px solid #e2e7ed;
    padding: 12px;
  }

  .job-flow-palette-header label,
  .job-flow-lane-title,
  .job-flow-active-label {
    color: #51616f;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    margin-bottom: 7px;
    text-transform: uppercase;
  }

  .job-flow-palette-list {
    flex: 1 1 auto;
    max-height: none;
    overflow-y: auto;
    padding: 12px;
  }

  .job-flow-palette-section + .job-flow-palette-section {
    margin-top: 14px;
  }

  .job-flow-section-title {
    color: #7b8791;
    display: block;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
    text-transform: uppercase;
  }

  .job-flow-node {
    align-items: center;
    background: #fff;
    border: 1px solid #d7dde5;
    border-radius: 6px;
    cursor: grab;
    display: flex;
    gap: 9px;
    margin-bottom: 8px;
    min-height: 48px;
    min-width: 0;
    padding: 8px 10px;
    position: relative;
    text-align: left;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    width: 100%;
  }

  .job-flow-node:hover,
  .job-flow-node:focus,
  .job-flow-node.is-selected {
    border-color: #2f80a4;
    box-shadow: 0 8px 18px rgba(47, 128, 164, .16);
    outline: 0;
    transform: translateY(-1px);
  }

  .job-flow-node:active {
    cursor: grabbing;
  }

  .job-flow-node-icon {
    align-items: center;
    border-radius: 50%;
    color: #fff;
    display: inline-flex;
    flex: 0 0 30px;
    height: 30px;
    justify-content: center;
    width: 30px;
  }

  .job-flow-node-draft .job-flow-node-icon {
    background: #00a65a;
  }

  .job-flow-node-existing .job-flow-node-icon {
    background: #3c8dbc;
  }

  .job-flow-node-main {
    flex: 1 1 auto;
    min-width: 0;
  }

  .job-flow-node-title {
    color: #26323b;
    display: block;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-flow-node-meta {
    color: #7b8791;
    display: block;
    font-size: 11px;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-flow-canvas {
    background:
      linear-gradient(rgba(60, 141, 188, .08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(60, 141, 188, .08) 1px, transparent 1px),
      #f8fafc;
    background-size: 28px 28px;
    display: grid;
    gap: 14px;
    grid-template-columns: minmax(160px, 1fr) minmax(170px, .8fr) minmax(160px, 1fr);
    min-height: 0;
    min-width: 0;
    padding: 18px;
    position: relative;
  }

  .job-flow-canvas:before {
    background: linear-gradient(90deg, rgba(0, 166, 90, .55), rgba(60, 141, 188, .35), rgba(245, 105, 84, .55));
    content: '';
    height: 3px;
    left: 8%;
    position: absolute;
    right: 8%;
    top: 50%;
  }

  .job-flow-lane,
  .job-flow-active-column {
    position: relative;
    z-index: 1;
  }

  .job-flow-lane {
    background: rgba(255, 255, 255, .92);
    border: 1px dashed #b7c3cf;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    padding: 12px;
  }

  .job-flow-lane.is-drag-over {
    background: #ecf7ff;
    border-color: #3c8dbc;
    box-shadow: inset 0 0 0 2px rgba(60, 141, 188, .18);
  }

  .job-flow-lane-title {
    align-items: center;
    display: flex;
    gap: 6px;
  }

  .job-flow-drop-list {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }

  .job-flow-empty-lane,
  .job-flow-empty-palette {
    align-items: center;
    border: 1px dashed #d5dce3;
    border-radius: 6px;
    color: #7b8791;
    display: flex;
    font-size: 12px;
    justify-content: center;
    min-height: 74px;
    padding: 12px;
    text-align: center;
  }

  .job-flow-active-column {
    align-items: center;
    display: flex;
    justify-content: center;
    min-height: 0;
  }

  .job-flow-active-card {
    background: #fff;
    border: 2px solid #253746;
    border-radius: 8px;
    box-shadow: 0 14px 34px rgba(34, 45, 50, .16);
    max-width: 100%;
    padding: 16px;
    text-align: center;
    width: 100%;
  }

  .job-flow-active-node {
    cursor: pointer;
    justify-content: center;
    margin-bottom: 0;
  }

  .job-flow-edge-node {
    cursor: grab;
    padding-right: 36px;
  }

  .job-flow-remove-edge {
    background: transparent;
    border: 0;
    color: #9aa6b2;
    padding: 4px;
    position: absolute;
    right: 7px;
    top: 10px;
  }

  .job-flow-remove-edge:hover,
  .job-flow-remove-edge:focus {
    color: #dd4b39;
    outline: 0;
  }

  .job-flow-selected {
    background: #fdf7e7;
    border-top: 1px solid #ead6a0;
    color: #7a5a16;
    display: none;
    font-size: 12px;
    padding: 8px 12px;
  }

  .job-flow-selected.is-visible {
    display: block;
  }

  .job-flow-condition {
    background: #fff;
    border-top: 1px solid #e2e7ed;
    padding: 12px 16px 14px;
  }

  .job-log-output pre {
    max-height: 520px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
  }

</style>
<div class="content-wrapper">
  <section class="content-header">
    <h1>
      Job Creation
      <small>Run Jobs</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Job Management</a></li>
      <li class="active">Job Creation</li>
    </ol>
  </section>
  <section class="content">
    <div class="container-fluid job-creation-workspace">
      <div class="row" style="margin-top: 10px; margin-bottom: 10px;">
        <div class="col-xs-12 text-right">
          <a class="btn btn-default" href="<?php echo base_url(); ?>jobList"><i class="fa fa-list"></i> Job Build List</a>
        </div>
      </div>
<!--    <div class="row" style="margin-top: 10px; margin-bottom: 40px;">
     <div class="col-lg-12 col-md-12 col-xs-12">
      <div class="text-center">
        <h3>Statistic Content</h3>
      </div>
    </div>
  </div> -->

  <div class="row" style="margin-top: 10px; margin-bottom: 10px;">
   <div class="col-md-12">
    <?php
    $this->load->helper('form');
    $savedJobName = $this->session->flashdata('saved_job_name');
    if($savedJobName)
    {
      $this->session->unset_userdata('saved_job_name');
    }
    $savedJobNames = $this->session->flashdata('saved_job_names');
    if(! is_array($savedJobNames))
    {
      $savedJobNames = $savedJobName ? array($savedJobName) : array();
    }
    if($savedJobNames)
    {
      $this->session->unset_userdata('saved_job_names');
    }
    $savedJobCreatedAt = $this->session->flashdata('saved_job_created_at');
    if($savedJobCreatedAt)
    {
      $this->session->unset_userdata('saved_job_created_at');
    }
    $savedJobCreationDates = $this->session->flashdata('saved_job_creation_dates');
    if(! is_array($savedJobCreationDates))
    {
      $savedJobCreationDates = array();
    }
    if($savedJobCreationDates)
    {
      $this->session->unset_userdata('saved_job_creation_dates');
    }
    $jobCreationDates = isset($job_creation_dates) && is_array($job_creation_dates) ? $job_creation_dates : array();
    $error = $this->session->flashdata('error');
    if($error)
    {
      $this->session->unset_userdata('error');
    }
    if($error)
    {
      ?>
      <div class="alert alert-danger alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <?php echo $error; ?>
      </div>
    <?php } ?>
    <?php
    $success = $this->session->flashdata('success');
    if($success)
    {
      $this->session->unset_userdata('success');
    }
    if($success && $success !== 'Your XML File has been successfully created !')
    {
      ?>
      <div class="alert alert-success alert-dismissable destroy">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <?php echo $success; ?>
      </div>
    <?php } ?>

    <div class="row">
      <div class="col-md-12">
        <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>
      </div>
    </div>
  </div>
</div>

<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box" class="box box-primary collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body" style="width: 100%;">
        <table id="myTable" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
              <th>Build Situation</th>
            <th>Job Name</th>
            <th>Environment</th>
            <th>Created</th>
            <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Build Situation</th>
            <th>Job Name</th>
            <th>Environment</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- /.box-body -->
  </div>
  <!-- /.box -->
</div>
<!-- /.col -->
</div>
<!-- /.row -->

<div class="modal fade" id="jobCreationLogModal" style="display: none;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span></button>
        <h4 class="modal-title">Job Build Console Log</h4>
      </div>
      <div class="modal-body job-log-output" id="jobCreationLogContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php $this->load->helper("form"); ?>
<form role="form" id="InsertDbSettings" action="<?php echo base_url() ?>jobCreation/send" method="post">
<input type="checkbox" name="checkEnvironment" id="checkEnvironment" value="1" checked style="display: none;">
<input type="hidden" name="timestamp" id="timestamp" value="1">
<div class="alert alert-info editJobBanner" style="display: none;">
  <i class="fa fa-pencil"></i>
  Editing <b class="editJobName"></b>. Saving will update this Jenkins job unless you change the job name.
  <button type="button" id="clearEditJob" class="btn btn-default btn-xs pull-right"><i class="fa fa-plus"></i> New Job</button>
</div>
<div class="row job-form-row">
  <div class="col-lg-4 col-md-12 col-xs-12 job-input-column">
    <div class="box box-primary job-form-card" style="padding-bottom: 15px;">
      <div class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
      <div class="box-header with-border" style="padding-top: 15px;">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Job Input Fields</b></h3>
      </div>

      <!-- /.box-header -->
      <!-- input fields -->
        <div class="box-body" style="padding-top: 15px;">
          <div class="form-group">
            <label for="exampleInputEmail1">Job Name</label>
            <input type="text" name ="job_name" class="form-control" id="job_name" maxlength="50" placeholder="Auto-generated if empty" onkeypress="return event.charCode != 32">
            <p class="help-block">Primary job name. Leave empty to generate one.</p>
          </div>
          <div class="form-group job-bulk-drafts">
            <div class="job-bulk-drafts-summary">
              <div>
                <label>Bulk Job Drafts</label>
                <p class="help-block">Optional. Create multiple named jobs or manage separate draft tabs.</p>
              </div>
              <button type="button" class="btn btn-default btn-xs" id="toggleBulkDrafts"><i class="fa fa-clone"></i> <span class="bulk-drafts-toggle-label">Show Bulk Tools</span></button>
            </div>
            <div class="job-bulk-drafts-panel" id="bulkDraftsPanel">
              <label for="job_names">Draft Names</label>
              <textarea class="form-control" id="job_names" name="job_names" maxlength="5000" rows="3" placeholder="one-job-per-line&#10;another-job&#10;folder/nested-job"></textarea>
              <p class="help-block">Names become draft tabs. Each tab can keep its own configuration before save.</p>
              <div class="job-batch-tools">
                <button type="button" class="btn btn-default btn-xs" id="generateBatchNames"><i class="fa fa-magic"></i> Generate 3 Names</button>
                <button type="button" class="btn btn-default btn-xs" id="syncDraftNames"><i class="fa fa-columns"></i> Apply Names To Tabs</button>
                <span class="job-batch-preview" id="jobBatchPreview">1 job will be saved.</span>
              </div>
              <div class="job-draft-workspace">
                <div class="job-draft-toolbar">
                  <strong><i class="fa fa-clone"></i> Job Drafts</strong>
                  <button type="button" class="btn btn-default btn-xs" id="addJobDraft"><i class="fa fa-plus"></i> Add</button>
                  <button type="button" class="btn btn-default btn-xs" id="duplicateJobDraft"><i class="fa fa-copy"></i> Duplicate</button>
                  <button type="button" class="btn btn-danger btn-xs" id="removeJobDraft"><i class="fa fa-trash"></i> Remove</button>
                </div>
                <ul class="nav nav-tabs job-draft-tabs" id="jobDraftTabs"></ul>
                <div class="table-responsive">
                  <table class="table table-condensed table-striped job-draft-comparison" id="jobDraftComparison"></table>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group" style="padding-top: 5px;">
            <div class="form-group">
              <label for="description">Description</label>
              <textarea class="form-control" id="description" value="" name="description" maxlength="500" rows="5"></textarea>
            </div>
          </div>
          <div class="job-creation-review">
            <h4><i class="fa fa-clipboard"></i> Review Before Save</h4>
            <dl id="jobCreationReview"></dl>
          </div>
          <div class="form-group job-form-actions">
            <input type="hidden" name="trigger_after_save" id="trigger_after_save" value="0">
            <button type="submit" id="send" href="#" class="btn btn-success buildXmlBtn"><i class="fa fa-save"></i> Create Job</button>
            <button type="submit" id="saveAndTrigger" class="btn btn-primary buildXmlBtn"><i class="fa fa-play"></i> Create And Trigger</button>
            <span class="saveJobStatus text-muted" style="display: none; margin-left: 10px;"></span>
          </div>
        </div>
        <!-- /.box-body -->
      </div>
    </div>

    <div class="col-lg-8 col-md-12 col-xs-12 job-config-column">
      <div class="job-config-canvas">
    <div class="job-config-header">
      <div>
        <h3><i class="fa fa-sliders"></i> Configuration Canvas</h3>
        <p>Select options, then edit one enabled section at a time.</p>
      </div>
      <div class="job-config-header-actions">
        <span class="label label-default" id="jobOptionEnabledCount">0 enabled</span>
      </div>
    </div>
        <div class="job-config-options">
          <div class="job-config-options-header">
            <strong><i class="fa fa-list-ul"></i> Job Options</strong>
          </div>
          <div class="job-option-grid">
            <?php if($os == "windows") { ?>
              <label class="job-option-card" data-option-panel="#runWinCommand">
                <input type="checkbox" name="winCommand" id="winCommand" value="1">
                <i class="fa fa-terminal job-option-icon"></i>
                <span class="job-option-title">Windows Execution</span>
                <span class="job-option-detail">Command line or uploaded Windows script.</span>
                <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
              </label>
            <?php } else { ?>
              <label class="job-option-card" data-option-panel="#runlinuxCommand">
                <input type="checkbox" name="linuxCommand" id="linuxCommand" value="1">
                <i class="fa fa-terminal job-option-icon"></i>
                <span class="job-option-title">Linux Execution</span>
                <span class="job-option-detail">Choose Bash/Shell or Python in the execution panel.</span>
                <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
              </label>
            <?php }?>
            <label class="job-option-card" data-option-panel="#build">
              <input type="checkbox" name="checkBuild" id="checkBuild" value="1">
              <i class="fa fa-calendar job-option-icon"></i>
              <span class="job-option-title">Schedule</span>
              <span class="job-option-detail">Cron-style single, repetitive, or tag based timing.</span>
              <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
            </label>
            <label class="job-option-card" data-option-panel="#abortIfStuck">
              <input type="checkbox" name="abort" id="abort" value="1">
              <i class="fa fa-hourglass-half job-option-icon"></i>
              <span class="job-option-title">Timeout</span>
              <span class="job-option-detail">Abort jobs after no activity or an absolute limit.</span>
              <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
            </label>
            <label class="job-option-card" data-option-panel="#runJob">
              <input type="checkbox" name="runJobCheck" id="runJobCheck" value="1">
              <i class="fa fa-sitemap job-option-icon"></i>
              <span class="job-option-title">Pipeline Wiring</span>
              <span class="job-option-detail">Wire upstream and downstream Jenkins jobs.</span>
              <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
            </label>
            <label class="job-option-card" data-option-panel="#enableEmail">
              <input type="checkbox" name="emailCheck" id="emailCheck" value="1">
              <i class="fa fa-envelope-o job-option-icon"></i>
              <span class="job-option-title">Failure Email</span>
              <span class="job-option-detail">Send a simple recipient list on failed builds.</span>
              <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
            </label>
            <label class="job-option-card" data-option-panel="#editableEmail">
              <input type="checkbox" name="editableEmailCheck" id="editableEmailCheck" value="1">
              <i class="fa fa-envelope job-option-icon"></i>
              <span class="job-option-title">Email Templates</span>
              <span class="job-option-detail">Choose success, failure, and abort templates.</span>
              <span class="job-option-state"><span class="label label-primary">Enabled</span></span>
            </label>
          </div>
        </div>
    <div class="job-config-empty-state" id="jobOptionEmptyState">
      <i class="fa fa-mouse-pointer fa-2x"></i>
      <h4>No optional configuration enabled</h4>
      <p>Select an option card above to add schedule, execution, notification, timeout, or downstream settings.</p>
    </div>

    <div class="job-config-workbench is-empty" id="jobConfigWorkbench">
      <div class="job-config-side">
        <span class="job-config-side-title">Enabled Sections</span>
        <div class="job-config-rail" id="jobConfigSideNav">
          <span class="job-config-menu-empty">No configurable options enabled.</span>
        </div>
      </div>

    <div class="job-config-panel-stack">
      <div class="job-config-panel-empty-note" id="jobConfigPanelEmptyNote">
        Enable an option to edit its settings here.
      </div>

    <!-- Row and column for Schedule Job and Execute Windows / Linux Command, Script -->
    <div class="row">
      <div class="col-lg-12 col-md-12 col-xs-12">

        <!-- Run Windows Command,Script Area-->
        <div id="runWinCommand" style="display: none;">
          <div class="col-lg-6 col-md-6 col-xs-12">
            <div class="box box-primary">
              <div class="box-header with-border">
                <div class="box-tools pull-right">
                  <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
                <h3 class="box-title">
                  <b>Execute a Windows Command</b></h3>
                </div><div class="box-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="executionStrategy">Execution Strategy</label>
                        <select class="form-control" id="executionStrategy" name="executionStrategy">
                          <option value="0" selected>-- Select an action -- </option>
                          <option value="script">Script Execution</option>
                          <option value="command">Windows Command Execution</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6 scriptTypeForm" style="display: none;">
                      <div class="form-group">
                        <label for="scriptType">Script Type</label>
                        <select class="form-control" id="scriptType" name="scriptType"><option value="0" selected>-- Select a script type -- </option>
                          <option value="batch">Windows Batch Script</option>
                          <option value="talend">Talend Data Integration Script</option>
                          <option value="python">Python Script</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <hr>
                  <div class="row windowsCommandForm" style="display: none;">
                    <div class="col-md-12 ">
                      <div class="form-group">
                        <label for="windowsCommandLine">Windows Command Line</label>
                        <textarea class="form-control" id="windowsCommandLine" name="windowsCommandLine"  maxlength="5000" autocomplete="off" rows="5"></textarea>
                      </div>
                    </div>
                  </div>
                  <div class="row uploadScript" style="display: none;">
                    <div class="col-md-12 ">
                      <div class="form-group">
                        <div id="windowsColumn"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Close Run Windows Command,Script Area -->

          <!-- Run Linux Command,Script Area -->
          <div id="runlinuxCommand" style="display: none;">
            <div class="col-lg-6 col-md-6 col-xs-12">
              <div class="box box-primary">
                <div class="box-header with-border">
                  <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    <button id="hideLinuxCommand" type="button" class="btn btn-box-tool"><i class="fa fa-times"></i></button>
                  </div>
                  <h3 class="box-title">
                    <b>Linux Execution</b></h3>
                  </div><div class="box-body">
                    <div class="linux-execution-mode-grid">
                      <button type="button" class="linux-execution-mode" data-linux-mode="bash">
                        <i class="fa fa-terminal"></i>
                        <strong>Bash / Shell</strong>
                        <span>Run a command, upload a Bash archive, or use the shell runtime.</span>
                      </button>
                      <button type="button" class="linux-execution-mode" data-linux-mode="python">
                        <i class="fa fa-code"></i>
                        <strong>Python</strong>
                        <span>Open the inline Python workspace with runtime and dependency controls.</span>
                      </button>
                    </div>
                    <div class="linux-execution-section linux-shell-options" style="display: none;">
                      <div class="linux-execution-section-header">
                        <strong>Shell execution</strong>
                        <span>Use these for direct shell commands, Bash archives, and Talend shell packages.</span>
                      </div>
                      <div class="linux-execution-choice-grid">
                        <button type="button" class="linux-execution-choice" data-linux-shell-choice="command"><i class="fa fa-terminal"></i><strong>Command</strong><span>Paste and run shell commands.</span></button>
                        <button type="button" class="linux-execution-choice" data-linux-shell-choice="bash"><i class="fa fa-file-archive-o"></i><strong>Bash Script</strong><span>Upload a Bash zip package.</span></button>
                        <button type="button" class="linux-execution-choice" data-linux-shell-choice="talend"><i class="fa fa-cubes"></i><strong>Talend Script</strong><span>Upload a Talend Linux package.</span></button>
                      </div>
                    </div>
                    <div class="linux-execution-section linux-python-options" style="display: none;">
                      <div class="linux-execution-section-header">
                        <strong>Python execution</strong>
                        <span>Use these for inline workspaces, uploaded Python packages, repository paths, or Git sources.</span>
                      </div>
                      <div class="linux-execution-choice-grid">
                        <button type="button" class="linux-execution-choice" data-linux-python-choice="inline"><i class="fa fa-code"></i><strong>Inline Workspace</strong><span>Edit Python files directly here.</span></button>
                        <button type="button" class="linux-execution-choice" data-linux-python-choice="upload"><i class="fa fa-upload"></i><strong>Upload Python</strong><span>Upload a .py file or zip package.</span></button>
                        <button type="button" class="linux-execution-choice" data-linux-python-choice="path"><i class="fa fa-folder-open"></i><strong>Repository Path</strong><span>Run Python already in the repository.</span></button>
                        <button type="button" class="linux-execution-choice" data-linux-python-choice="git"><i class="fa fa-code-fork"></i><strong>Git Source</strong><span>Clone and run a branch or tag.</span></button>
                      </div>
                    </div>
                    <div class="row linuxLegacyExecutionFields">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="linuxExecutionStrategy">Execution Strategy</label>
                          <select class="form-control" id="linuxExecutionStrategy" name="linuxExecutionStrategy">
                            <option value="0" selected>-- Select an action -- </option>
                            <option value="command">Linux Command Execution</option>
                            <option value="script">Script/File Execution</option>
                            <option value="python_inline">Inline Python Code</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6 linuxScriptTypeForm" style="display: none;">
                        <div class="form-group">
                          <label for="linuxScriptType">Script Type</label>
                          <select class="form-control" id="linuxScriptType" name="linuxScriptType"><option value="0" selected>-- Select a script type -- </option>
                            <option value="bash">Bash Script</option>
                            <option value="talend">Talend Data Integration Script</option>
                            <option value="python">Python File or Repository</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="row pythonSourceForm" style="display: none;">
                      <div class="col-md-4 pythonSourceModeColumn">
                        <div class="form-group">
                          <label for="pythonSourceMode">Python Source</label>
                          <select class="form-control" id="pythonSourceMode" name="pythonSourceMode">
                            <option value="upload" selected>Uploaded File or Archive</option>
                            <option value="path">Repository Path</option>
                            <option value="git">Git Repository URL</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-8 pythonEntryPointColumn">
                        <div class="form-group">
                          <label for="pythonEntryPoint">Entry Python File or Nested Path</label>
                          <input type="text" class="form-control" id="pythonEntryPoint" name="pythonEntryPoint" maxlength="500" autocomplete="off" placeholder="main.py or pyjob/main.py">
                        </div>
                      </div>
                    </div>
                    <div class="row pythonPathSourceForm" style="display: none;">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="pythonSourcePath">Repository Folder or File</label>
                          <input type="text" class="form-control" id="pythonSourcePath" name="pythonSourcePath" maxlength="1000" autocomplete="off" placeholder="python/jobs/my-job or python/jobs/my-job/main.py">
                        </div>
                      </div>
                    </div>
                    <div class="row pythonGitSourceForm" style="display: none;">
                      <div class="col-md-8">
                        <div class="form-group">
                          <label for="pythonRepositoryUrl">Git Repository URL</label>
                          <input type="text" class="form-control" id="pythonRepositoryUrl" name="pythonRepositoryUrl" maxlength="1000" autocomplete="off" placeholder="https://github.com/org/project.git">
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="pythonRepositoryBranch">Branch or Tag</label>
                          <input type="text" class="form-control" id="pythonRepositoryBranch" name="pythonRepositoryBranch" maxlength="200" autocomplete="off" placeholder="main">
                        </div>
                      </div>
                    </div>
                    <div class="row pythonRuntimeForm" style="display: none;">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="pythonRuntimeMode">Runtime</label>
                          <select class="form-control" id="pythonRuntimeMode" name="pythonRuntimeMode">
                            <option value="local" selected>Jenkins Agent</option>
                            <option value="docker">Docker Container</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6 pythonAgentPythonColumn">
                        <div class="form-group">
                          <label for="pythonVersion">Agent Python</label>
                          <select class="form-control" id="pythonVersion" name="pythonVersion">
                            <option value="python3" selected>Default python3</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6 pythonDockerImageColumn" style="display: none;">
                        <div class="form-group">
                          <label for="pythonDockerImage">Docker Image</label>
                          <input type="text" class="form-control" id="pythonDockerImage" name="pythonDockerImage" maxlength="200" autocomplete="off" list="pythonDockerImageOptions" placeholder="alpine:3.20, eclipse-temurin:17-jre-alpine, or python:3.12-slim">
                          <datalist id="pythonDockerImageOptions">
                            <option value="alpine:3.20">
                            <option value="busybox:1.36">
                            <option value="bash:5.2">
                            <option value="debian:12-slim">
                            <option value="eclipse-temurin:17-jre-alpine">
                            <option value="eclipse-temurin:17-jre-jammy">
                            <option value="eclipse-temurin:11-jre-jammy">
                            <option value="python:3.13-slim">
                            <option value="python:3.12-slim">
                            <option value="python:3.11-slim">
                            <option value="python:3.10-slim">
                          </datalist>
                        </div>
                      </div>
                    </div>
                    <div class="row pythonInlineSourceForm" style="display: none;">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label id="pythonWorkspaceLabel" for="pythonInlineCode">Python Workspace</label>
                          <input type="hidden" id="pythonInlineFilesJson" name="pythonInlineFilesJson" value="{&quot;files&quot;:[],&quot;directories&quot;:[]}">
                          <div class="python-inline-workspace">
                            <div class="python-inline-sidebar">
                              <div class="python-inline-sidebar-header">
                                <span class="python-inline-sidebar-title">Files</span>
                                <div class="python-inline-sidebar-actions">
                                  <button type="button" class="btn btn-xs" id="addPythonInlineFile" title="Add Python file"><i class="fa fa-file-code-o"></i></button>
                                  <button type="button" class="btn btn-xs" id="addPythonInlineFolder" title="Add folder"><i class="fa fa-folder-o"></i></button>
                                </div>
                              </div>
                              <div class="python-inline-file-list" id="pythonInlineFileList"></div>
                            </div>
                            <div class="python-inline-main">
                              <div class="python-inline-tabs" role="tablist">
                                <button type="button" class="python-inline-tab active" data-python-inline-pane="code"><i class="fa fa-code"></i> <span id="pythonInlineEditorFile">main.py</span><span class="python-inline-tab-close" title="Close tab"><i class="fa fa-times"></i></span></button>
                                <button type="button" class="python-inline-tab" data-python-inline-pane="requirements"><i class="fa fa-list-alt"></i> requirements.txt<span class="python-inline-tab-close" title="Close tab"><i class="fa fa-times"></i></span></button>
                                <button type="button" class="python-inline-tab" data-python-inline-pane="dockerfile"><i class="fa fa-cube"></i> Dockerfile<span class="python-inline-tab-close" title="Close tab"><i class="fa fa-times"></i></span></button>
                                <button type="button" class="python-inline-tab" data-python-inline-pane="extra" style="display: none;"><i class="fa fa-file-code-o"></i> <span id="pythonInlineExtraTabFile">lib.py</span><span class="python-inline-tab-close" title="Close tab"><i class="fa fa-times"></i></span></button>
                              </div>
                              <div class="python-inline-empty-state" id="pythonInlineEmptyState"><i class="fa fa-file-code-o"></i>&nbsp;Select a file</div>
                              <div class="python-inline-editor-panel active" data-python-inline-pane="code">
                              <div class="linux-code-editor python-code-editor">
                                <div class="linux-code-editor-bar">
                                  <span class="linux-code-editor-tab"><i class="fa fa-code"></i> <span id="pythonInlineEditorActiveFile">main.py</span></span>
                                  <span class="linux-code-editor-actions">
                                    <button type="button" class="btn btn-xs" id="runPythonInlinePreview" title="Run with Jenkins Python"><i class="fa fa-play"></i></button>
                                    <button type="button" class="btn btn-xs" id="applyPythonInlineTemplate" title="Insert JobSeeker template"><i class="fa fa-magic"></i></button>
                                    <span class="linux-code-editor-meta">python</span>
                                  </span>
                                </div>
                                <div class="linux-code-editor-body">
                                  <div class="linux-code-editor-lines" id="pythonInlineCodeNumbers" aria-hidden="true">1</div>
                                  <div class="linux-code-editor-source">
                                    <pre class="python-syntax-highlight" id="pythonInlineCodeHighlight" aria-hidden="true"></pre>
                                    <textarea class="form-control python-highlighted-input" id="pythonInlineCode" name="pythonInlineCode" maxlength="50000" autocomplete="off" rows="12" spellcheck="false" wrap="off" placeholder="def main():&#10;    print(&quot;Hello from JobSeeker&quot;)&#10;&#10;if __name__ == &quot;__main__&quot;:&#10;    main()"></textarea>
                                  </div>
                                </div>
                                <div class="python-lint-panel" id="pythonInlineLintPanel" aria-live="polite"></div>
                                <div class="python-preview-panel" id="pythonInlinePreviewPanel" aria-live="polite"></div>
                                <div class="linux-code-editor-status"><span>Python</span><span>UTF-8</span><span>LF</span></div>
                              </div>
                              </div>
                              <div class="python-inline-editor-panel" data-python-inline-pane="requirements">
                              <div class="linux-code-editor python-requirements-editor">
                                <div class="linux-code-editor-bar">
                                  <span class="linux-code-editor-tab"><i class="fa fa-list-alt"></i> requirements.txt</span>
                                  <span class="linux-code-editor-meta">pip</span>
                                </div>
                                <div class="linux-code-editor-body">
                                  <div class="linux-code-editor-lines" id="pythonRequirementsTextNumbers" aria-hidden="true">1</div>
                                  <textarea class="form-control" id="pythonRequirementsText" name="pythonRequirementsText" maxlength="20000" autocomplete="off" rows="12" spellcheck="false" wrap="off" placeholder="requests==2.32.3&#10;pandas==2.2.2"></textarea>
                                </div>
                                <div class="linux-code-editor-status"><span>Requirements</span><span>UTF-8</span><span>LF</span></div>
                              </div>
                              </div>
                              <div class="python-inline-editor-panel" data-python-inline-pane="dockerfile">
                              <div class="linux-code-editor python-dockerfile-editor">
                                <div class="linux-code-editor-bar">
                                  <span class="linux-code-editor-tab"><i class="fa fa-cube"></i> Dockerfile</span>
                                  <span class="linux-code-editor-meta">docker</span>
                                </div>
                                <div class="linux-code-editor-body">
                                  <div class="linux-code-editor-lines" id="pythonDockerfileTextNumbers" aria-hidden="true">1</div>
                                  <textarea class="form-control" id="pythonDockerfileText" name="pythonDockerfileText" maxlength="50000" autocomplete="off" rows="12" spellcheck="false" wrap="off" placeholder="FROM python:3.13-slim&#10;WORKDIR /app&#10;RUN apt-get update &amp;&amp; apt-get install -y --no-install-recommends build-essential &amp;&amp; rm -rf /var/lib/apt/lists/*"></textarea>
                                </div>
                                <div class="linux-code-editor-status"><span>Dockerfile</span><span>UTF-8</span><span>LF</span></div>
                              </div>
                              </div>
                              <div class="python-inline-editor-panel" data-python-inline-pane="extra">
                                <div class="linux-code-editor python-extra-editor">
                                  <div class="linux-code-editor-bar">
                                    <span class="linux-code-editor-tab"><i class="fa fa-file-code-o"></i> <span id="pythonInlineExtraEditorFile">lib.py</span></span>
                                    <span class="linux-code-editor-meta">python</span>
                                  </div>
                                  <div class="linux-code-editor-body">
                                    <div class="linux-code-editor-lines" id="pythonInlineExtraCodeNumbers" aria-hidden="true">1</div>
                                    <div class="linux-code-editor-source">
                                      <pre class="python-syntax-highlight" id="pythonInlineExtraCodeHighlight" aria-hidden="true"></pre>
                                      <textarea class="form-control python-highlighted-input" id="pythonInlineExtraCode" maxlength="50000" autocomplete="off" rows="12" spellcheck="false" wrap="off" placeholder="def helper():&#10;    return &quot;ready&quot;"></textarea>
                                    </div>
                                  </div>
                                  <div class="python-lint-panel" id="pythonInlineExtraLintPanel" aria-live="polite"></div>
                                  <div class="linux-code-editor-status"><span>Python</span><span>UTF-8</span><span>LF</span></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <hr>
                    <div class="row linuxCommandForm" style="display: none;">
                      <div class="col-md-12 ">
                        <div class="form-group">
                          <label for="linuxCommandLine">Linux Command Line</label>
                          <div class="linux-code-editor">
                            <div class="linux-code-editor-bar">
                              <span class="linux-code-editor-tab"><i class="fa fa-terminal"></i> command.sh</span>
                              <span class="linux-code-editor-meta">bash</span>
                            </div>
                            <div class="linux-code-editor-body">
                              <div class="linux-code-editor-lines" id="linuxCommandLineNumbers" aria-hidden="true">1</div>
                              <textarea class="form-control" id="linuxCommandLine" name="linuxCommandLine" maxlength="5000" autocomplete="off" rows="10" spellcheck="false" wrap="off" placeholder="echo &quot;Starting job&quot;&#10;./run-task.sh"></textarea>
                            </div>
                            <div class="linux-code-editor-status"><span>Shell</span><span>UTF-8</span><span>LF</span></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="row linuxUploadScript" style="display: none;">
                      <div class="col-md-12 ">
                        <div class="form-group">
                          <div id="linuxColumn"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!--Close Run Liux Command, Script Area-->

            <!-- Schedule Job Area-->
            <div id="build" style="display: none;">
              <div class="col-lg-6 col-md-6 col-xs-12 removeBuild">
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <div class="box-tools pull-right">
                      <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                    <h3 class="box-title">
                      <b>Schedule Job</b></h3>
                    </div>
                    <div class="box-body" style="padding: 18px;">
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="timeoutStrategy">Trigger Action</label>
                            <select class="form-control" id="action" name="action">
                              <option value="0">-- Select an action --</option>
                              <option value="single">Single Execution</option>
                              <option value="repetitive">Repetitive Executions</option>
                              <option value="tags">Execution Tags Options</option>
                              <option value="cron">Custom Cron Expression</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      <div class="row tags" style="display: none;">
                        <hr>
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="tag">Execution Tag Option</label>
                            <select class="form-control" id="tag" name="tag">
                              <option value="@hourly">Hourly Executions</option>
                              <option value="@daily">Daily Executions</option>
                              <option value="@weekly">Weekly Executions</option>
                              <option value="@monthly">Monthly Executions</option>
                              <option value="@annually">Annually Executions</option>
                              <option value="@yearly">Yearly Executions</option>
                              <option value="@midnight">Midnight Executions</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      <div class="row singleForm" style="display: none;">
                        <hr>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                          <div class="input-group" style="width: 100%;">
                            <label for="singleMinute">Every Minute: </label><br>
                            <select class="form-control select2" id="singleMinute" name="singleMinute[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php
                              $i = 0;
                              for ($i=0; $i < 60; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                          <div class="input-group" style="width: 100%;">
                            <label>At Hour: </label><br>
                            <select class="form-control select2" id="singleHour" name="singleHour[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php
                              $i = 0;
                              for ($i=0; $i < 24; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row singleForm" style="display: none;">
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleDayOfMonth">On Day of month:</label><br>
                            <select class="form-control select2" id="singleDayOfMonth" name="singleDayOfMonth[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php
                              $i = 1;
                              for ($i=1; $i < 32; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleMonth">On Month:</label><br>
                            <select class="form-control select2" id="singleMonth" name="singleMonth[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php
                              $i = 1;
                              for ($i=1; $i < 13; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row singleForm" style="display: none;">
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleDayOfWeek">On Day of Week:</label><br>
                            <select class="form-control select2" id="singleDayOfWeek" name="singleDayOfWeek[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php
                              $i = 0;
                              for ($i=1; $i < 8; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row repetitive" style="display: none;">
                        <hr>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveMinute">In X Minutes</label><br>
                            <select class="form-control" id="repetitiveMinute" name="repetitiveMinute">
                              <option value="*">All</option>
                              <?php
                              $i = 0;
                              for ($i=0; $i < 60; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveHour">Hour</label>
                            <select class="form-control" id="repetitiveHour" name="repetitiveHour">
                              <option value="*">All</option>
                              <?php
                              $i = 0;
                              for ($i=0; $i < 24; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveDayOfMonth">Day of month</label>
                            <select class="form-control" id="repetitiveDayOfMonth" name="repetitiveDayOfMonth">
                              <option value="*">All</option>
                              <?php
                              $i = 1;
                              for ($i=1; $i < 32; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveMonth">Month</label>
                            <select class="form-control" id="repetitiveMonth" name="repetitiveMonth">
                              <option value="*">All</option>
                              <?php
                              $i = 1;
                              for ($i=1; $i < 13; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveDayOfWeek">Day of Week</label>
                            <select class="form-control" id="repetitiveDayOfWeek" name="repetitiveDayOfWeek">
                              <option value="*">All</option>
                              <?php
                              $i = 0;
                              for ($i=1; $i < 8; $i++) {
                                echo '<option value="'.$i.'">'.$i.'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div>

                      <div class="row customCronForm" style="display: none;">
                        <hr>
                        <div class="col-md-12">
                          <div class="form-group">
                            <label for="customCronExpression">Jenkins Cron Expression</label>
                            <input type="text" class="form-control" id="customCronExpression" name="customCronExpression" maxlength="120" autocomplete="off" placeholder="H 2 * * 1-5">
                            <p class="help-block">Use a five-field Jenkins cron expression such as <code>H 2 * * 1-5</code> or <code>H/15 * * * *</code>.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Close Schedule Job Area-->
            </div>
          </div>

          <!-- Row and column for Abort Job and Email Notification Divs -->
          <div class="row">
            <div class="col-lg-12 col-md-12 col-xs-12">

             <!-- Email Notification Area -->
             <div id="enableEmail" style="display: none;">
              <div class="col-lg-6 col-md-6 col-xs-12">
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <div class="box-tools pull-right">
                      <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                    <h3 class="box-title">
                      <b>Enable Email Notification</b></h3>
                    </div><div class="box-body">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="recipients">Recipients</label>
                          <input type="text" class="form-control" id="recipients" name="recipients">
                          <small><b>Example:</b> user@example.com,team@example.com</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Close Email Notification Area -->

              <!-- Abort Job if it Stuck Area -->
              <div id="abortIfStuck" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Abort the job if its stuck option</b></h3>
                      </div>
                      <div class="box-body" style="padding: 20px;">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="timeoutStrategy">Timeout Strategy</label>
                            <select class="form-control" id="timeoutStrategy" name="timeoutStrategy">
                              <option value="noActivity">No Activity</option>
                              <option value="absolute">Absolute</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6 timeoutSeconds">
                          <div class="form-group">
                            <label for="timeoutMinutes">Timeout Seconds</label>
                            <input type="number" class="form-control" id="timeoutSeconds" name="timeoutSeconds" min="60" maxlength="50" autocomplete="off">
                          </div>
                        </div>
                        <div class="col-md-6 timeoutMinutes" style="display: none;">
                          <div class="form-group">
                            <label for="timeoutMinutes">Timeout Minutes</label>
                            <input type="number" class="form-control" id="timeoutMinutes" name="timeoutMinutes" min="1"  maxlength="50" autocomplete="off">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Abort Job if it Stuck Area -->
              </div>
            </div>
            <!-- Close Row and column for Abort Job and Email Notification Divs -->

            <!-- Row and column for Job Execution Area and Editable Email Notification -->
            <div class="row">
              <div class="col-lg-12 col-md-12 col-xs-12">

               <!-- Job Execution Area -->
               <div id="runJob" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div id="overlay" class="overlay" style="display: none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Wire job pipeline</b></h3>
                      </div>
                      <div class="box-body job-flow-panel">
                        <select id="jobList" name="jobList[]" multiple="multiple" style="display: none;"></select>
                        <select id="upstreamJobList" name="upstreamJobList[]" multiple="multiple" style="display: none;"></select>
                        <div class="job-flow-builder" id="jobFlowBuilder">
                          <div class="job-flow-toolbar">
                            <div>
                              <span class="job-flow-kicker">Pipeline wiring</span>
                              <h4><i class="fa fa-random"></i> Upstream and Downstream Graph</h4>
                            </div>
                            <div class="job-flow-toolbar-actions">
                              <button type="button" class="btn btn-xs btn-link" id="refreshJobGraph"><i class="fa fa-refresh"></i> Refresh</button>
                              <button type="button" class="btn btn-xs btn-link" id="chainJobDrafts"><i class="fa fa-link"></i> Chain Drafts</button>
                              <button type="button" class="btn btn-xs btn-link" id="clearActiveWiring"><i class="fa fa-eraser"></i> Clear Active</button>
                            </div>
                          </div>
                          <div class="job-flow-layout">
                            <div class="job-flow-palette">
                              <div class="job-flow-palette-header">
                                <label for="jobFlowSearch"><i class="fa fa-search"></i> Job Palette</label>
                                <input type="text" class="form-control input-sm" id="jobFlowSearch" autocomplete="off" placeholder="Filter jobs">
                              </div>
                              <div class="job-flow-palette-list" id="jobFlowPalette"></div>
                            </div>
                            <div class="job-flow-canvas">
                              <div class="job-flow-lane job-flow-drop-zone" data-flow-lane="upstream">
                                <span class="job-flow-lane-title"><i class="fa fa-arrow-left"></i> Upstream</span>
                                <div class="job-flow-drop-list" id="jobFlowUpstreamList"></div>
                              </div>
                              <div class="job-flow-active-column">
                                <div class="job-flow-active-card">
                                  <span class="job-flow-active-label">Active Draft</span>
                                  <div id="jobFlowActiveNode"></div>
                                </div>
                              </div>
                              <div class="job-flow-lane job-flow-drop-zone" data-flow-lane="downstream">
                                <span class="job-flow-lane-title"><i class="fa fa-arrow-right"></i> Downstream</span>
                                <div class="job-flow-drop-list" id="jobFlowDownstreamList"></div>
                              </div>
                            </div>
                          </div>
                          <div class="job-flow-selected" id="jobFlowSelected"></div>
                          <div class="job-flow-condition">
                            <h5><b>Select an option for your next jobs.</b></h5>
                            <div class="form-group">
                              <div class="radio">
                                <label>
                                  <input type="radio" name="optionsRadios" id="option1" value="1" checked="">
                                  Run next jobs only if this job has been successfully executed.
                                </label>
                              </div>
                              <div class="radio">
                                <label>
                                  <input type="radio" name="optionsRadios" id="option2" value="2">
                                  Run next jobs even if this job has been failed.
                                </label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Job Execution Area -->

                <!-- Editable Email Notification Area -->
               <div id="editableEmail" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div id="overlay" class="overlay" style="display: none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Editable email notification</b></h3>
                      </div>
                      <div class="box-body">
                        <div class="row">
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-green">Success</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onSuccess" name="onSuccess" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attSuccess" name="attSuccess" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-red">Failure</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onFailure" name="onFailure" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attFailure" name="attFailure" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-red">Abort</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onAbort" name="onAbort" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attAbort" name="attAbort" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Job Execution Area -->

                <div id="environmentBox" class="job-creation-environment-panel" style="display: none;">
                  <select class="env" id="environment" name="environment" required style="display: none;"></select>
                  <span id="jobCreationEnvironmentHelp" style="display: none;"></span>
                </div>

              </div>
            </div>
            <!-- Close and column for Job Execution Area and Editable Email Notification -->
        </div>
      </div>
    </div>
  </div>
</div>
</form> <!-- Close Form -->
        <div id="output"></div>

    </div>
  </section>
</div>
  <script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>
  <script type="text/javascript" src="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.js"></script>
  <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/job-inspect-modal.js?v=1"></script>
  <script type="text/javascript">
    $(document).ready(function(){

      $('.select2').select2({
       placeholder: "Click to Select a option",
       allowClear: true
     });

      var addUserForm = $("#InsertDbSettings");
      var validator = addUserForm.validate({

        rules:{
          job_name :{ maxlength : 50 },
          job_names :{ maxlength : 5000 },
          description :{ maxlength : 500 }
        },
        messages:{
          job_name :{ maxlength : "Job name can contain up to 50 characters" },
          job_names :{ maxlength : "Bulk job names can contain up to 5000 characters" },
          description :{ maxlength : "Description can contain up to 500 characters" }
        }
      });

      $('#send').on('click mouseenter focus', function(){
        $('#trigger_after_save').val('0');
        updateJobCreationReview();
      });

      $('#saveAndTrigger').on('click mouseenter focus', function(){
        $('#trigger_after_save').val('1');
        updateJobCreationReview();
      });

      $('#toggleBulkDrafts').click(function() {
        setBulkDraftsVisible(! $('#bulkDraftsPanel').is(':visible'));
      });

      $('#generateBatchNames').click(function() {
        generateBatchNames(3);
      });

      $('#syncDraftNames').click(function() {
        syncDraftsFromNames();
      });

      $('#addJobDraft').click(function() {
        addJobDraft();
      });

      $('#duplicateJobDraft').click(function() {
        duplicateJobDraft();
      });

      $('#removeJobDraft').click(function() {
        removeJobDraft();
      });

      $('#jobDraftTabs').on('click', 'a', function(event) {
        event.preventDefault();
        switchJobDraft(parseInt($(this).data('draft-index'), 10));
      });

      $('#jobFlowSearch').on('input', function() {
        renderJobPipelineGraph();
      });

      $('#refreshJobGraph').on('click', function() {
        ensureAvailableJobsLoaded(true);
        refreshAvailableJobsTable(false);
      });

      $('#chainJobDrafts').on('click', function() {
        chainJobDraftTabs();
      });

      $('#clearActiveWiring').on('click', function() {
        clearActiveJobWiring();
      });

      $(document).on('dragstart', '.job-flow-node', function(event) {
        var dataTransfer = event.originalEvent && event.originalEvent.dataTransfer;
        if (! dataTransfer) {
          return;
        }

        var node = jobFlowNodeFromElement(this);
        dataTransfer.effectAllowed = 'copyMove';
        dataTransfer.setData('application/json', JSON.stringify(node));
        dataTransfer.setData('text/plain', node.name);
      });

      $(document).on('dragover', '.job-flow-drop-zone', function(event) {
        event.preventDefault();
        $(this).addClass('is-drag-over');
      });

      $(document).on('dragleave drop', '.job-flow-drop-zone', function() {
        $(this).removeClass('is-drag-over');
      });

      $(document).on('drop', '.job-flow-drop-zone', function(event) {
        event.preventDefault();

        var dataTransfer = event.originalEvent && event.originalEvent.dataTransfer;
        if (! dataTransfer) {
          return;
        }

        var rawPayload = dataTransfer.getData('application/json');
        var node = null;

        try {
          node = JSON.parse(rawPayload);
        } catch (error) {
          node = { type: 'existing', name: dataTransfer.getData('text/plain'), draftIndex: -1 };
        }

        if ($(this).data('flow-lane') === 'upstream') {
          addJobFlowConnection(node, activeJobFlowNode());
        } else {
          addJobFlowConnection(activeJobFlowNode(), node);
        }

        jobFlowSelectedNode = null;
        renderJobPipelineGraph();
      });

      $(document).on('click', '.job-flow-node', function(event) {
        if ($(event.target).closest('.job-flow-remove-edge').length) {
          return;
        }

        selectOrConnectJobFlowNode(jobFlowNodeFromElement(this));
      });

      $(document).on('keydown', '.job-flow-node', function(event) {
        if (event.which === 13 || event.which === 32) {
          event.preventDefault();
          selectOrConnectJobFlowNode(jobFlowNodeFromElement(this));
        }
      });

      $(document).on('click', '.job-flow-remove-edge', function(event) {
        event.preventDefault();
        event.stopPropagation();
        removeActiveJobFlowConnection($(this).attr('data-flow-lane'), {
          type: $(this).attr('data-flow-type'),
          name: $(this).attr('data-flow-name'),
          draftIndex: $(this).attr('data-flow-draft-index')
        });
      });

      $('#InsertDbSettings').on('submit', function(event) {
        if (! requireConcreteGlobalEnvironment()) {
          event.preventDefault();
          return false;
        }

        syncAllDraftEnvironments($('#environment').val() || '0');
        syncPythonInlineFilesInput();
        if (submitJobDraftsIfNeeded()) {
          event.preventDefault();
        }
      });

      $('#InsertDbSettings').on('input change', 'input, textarea, select', function() {
        updateJobCreationReview();
      });

      $('#linuxCommandLine').on('input change scroll', function() {
        updateLinuxCommandEditor();
      });

      $('#pythonInlineCode').on('input change scroll', function() {
        updatePythonInlineEditor();
      });

      $('#pythonInlineCode, #pythonInlineExtraCode').on('keydown', function(event) {
        handleCodeEditorTab(event, this);
      });

      $('#pythonRequirementsText').on('input change scroll', function() {
        updatePythonRequirementsEditor();
      });

      $('#pythonDockerfileText').on('input change scroll', function() {
        updatePythonDockerfileEditor();
      });

      $('#pythonInlineExtraCode').on('input change scroll', function() {
        captureActivePythonInlineExtraFile();
        updatePythonInlineExtraEditor();
        syncPythonInlineFilesInput(false);
      });

      $('#pythonEntryPoint').on('input change', function() {
        updatePythonInlineEditor();
        renderPythonInlineWorkspace();
      });

      $('#applyPythonInlineTemplate').on('click', function() {
        applyPythonInlineJobSeekerTemplate(true);
      });

      $('#runPythonInlinePreview').on('click', function() {
        runPythonInlinePreview();
      });

      $('.python-inline-tabs').on('click', '.python-inline-tab', function(event) {
        var pane = $(this).attr('data-python-inline-pane');
        if ($(event.target).closest('.python-inline-tab-close').length) {
          closePythonInlinePane(pane);
          return;
        }

        setPythonInlinePane(pane, pane == 'extra' ? pythonInlineActiveExtraPath : '');
      });

      $('#pythonInlineFileList').on('click', '.python-inline-file', function() {
        setPythonInlinePane($(this).attr('data-python-inline-pane'), $(this).attr('data-python-inline-path') || '');
      });

      $('#pythonInlineFileList').on('click', '.python-inline-file-remove', function(event) {
        event.preventDefault();
        event.stopPropagation();
        removePythonInlineWorkspacePath($(this).attr('data-python-inline-path') || '', $(this).attr('data-python-inline-type') || 'file');
      });

      $('#addPythonInlineFile').on('click', function() {
        addPythonInlineFile();
      });

      $('#addPythonInlineFolder').on('click', function() {
        addPythonInlineFolder();
      });

      $('#pythonRuntimeMode').on('change', function() {
        updatePythonSourceControls();
        updateJobCreationReview();
      });

      $('#pythonVersion').on('change', function() {
        updatePythonDockerImageFromVersion();
        updatePythonRuntimeControls();
        updateJobCreationReview();
      });

      $('#pythonDockerImage').on('input change', function() {
        updateJobCreationReview();
      });


      var petNamePrefixes = ['milo', 'luna', 'piper', 'nova', 'ruby', 'jasper', 'olive', 'cosmo'];
      var petNameSuffixes = ['sunny', 'maple', 'pixel', 'river', 'coco', 'sage', 'mango', 'ember'];
      var singleEveryMinuteAcknowledged = false;
      var repetitiveEveryMinuteAcknowledged = false;
      var customCronEveryMinuteAcknowledged = false;
      var jobDrafts = [];
      var activeDraftIndex = 0;
      var applyingJobDraft = false;
      var activeConfigPanel = '';
      var availableJobCache = [];
      var availableJobsLoading = false;
      var availableJobEnvironmentRequests = {};
      var jobFlowSelectedNode = null;
      var pythonInlineOpenPanes = { code: true, requirements: false, dockerfile: false, extra: false };
      var pythonInlineActivePane = 'code';
      var pythonInlineActiveExtraPath = '';
      var pythonInlineExtraFiles = [];
      var pythonInlineDirectories = [];
      var environmentHelper = window.JobSeekerEnvironment || {
        detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName, fullName: jobName}); },
        detectFromJob: function(job) { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
        normalize: function(value) { return $.trim(String(value || '')).toUpperCase(); },
        text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
      };
      var draftCheckboxFields = ['checkBuild', 'checkEnvironment', 'abort', 'winCommand', 'linuxCommand', 'runJobCheck', 'emailCheck', 'editableEmailCheck'];
      var draftScalarFields = ['job_name', 'description', 'executionStrategy', 'scriptType', 'windowsCommandLine', 'linuxExecutionStrategy', 'linuxScriptType', 'pythonSourceMode', 'pythonEntryPoint', 'pythonSourcePath', 'pythonRepositoryUrl', 'pythonRepositoryBranch', 'pythonInlineCode', 'pythonRequirementsText', 'pythonDockerfileText', 'pythonInlineFilesJson', 'pythonRuntimeMode', 'pythonVersion', 'pythonDockerImage', 'linuxCommandLine', 'action', 'tag', 'customCronExpression', 'repetitiveMinute', 'repetitiveHour', 'repetitiveDayOfMonth', 'repetitiveMonth', 'repetitiveDayOfWeek', 'recipients', 'timeoutStrategy', 'timeoutSeconds', 'timeoutMinutes', 'onSuccess', 'attSuccess', 'onFailure', 'attFailure', 'onAbort', 'attAbort', 'environment'];
      var draftArrayFields = ['singleMinute', 'singleHour', 'singleDayOfMonth', 'singleMonth', 'singleDayOfWeek', 'jobList', 'upstreamJobList'];

      function pythonInlineJobSeekerTemplate() {
        return [
          'import sys',
          'import time',
          'import os',
          'from os import path',
          '',
          'from jobseeker import JobSeeker',
          '',
          '',
          'JOB_NAME = path.basename(__file__).replace(".py", "")',
          'ENVIRONMENT = sys.argv[1] if len(sys.argv) > 1 else "LOCAL"',
          'PREVIEW = os.getenv("JOBSEEKER_PREVIEW") == "1"',
          '',
          '',
          'def operation():',
          '    with JobSeeker(environment=ENVIRONMENT, job=JOB_NAME) as js:',
          '        with js.task("Inline Python Job", "DW_Master") as tmf:',
          '            rows = tmf.context("rows", cast=int, default=5)',
          '            rows = min(rows, 5) if PREVIEW else rows',
          '            wait_seconds = 0.2 if PREVIEW else 1',
          '',
          '            for index in range(1, rows + 1):',
          '                print("Processing row {}/{}".format(index, rows))',
          '                tmf.progress(total=rows, processed=index, msg="Processed {} of {} rows".format(index, rows))',
          '                time.sleep(wait_seconds)',
          '',
          '            tmf.finish(total=rows, processed=rows, msg="Inline JobSeeker Python job completed")',
          '',
          '    return True',
          '',
          '',
          'if __name__ == "__main__":',
          '    operation()',
          ''
        ].join('\n');
      }

      function applyPythonInlineJobSeekerTemplate(force) {
        if (! pythonWorkspaceAllowsInlineCode()) {
          return;
        }

        var editor = $('#pythonInlineCode');
        if (! editor.length) {
          return;
        }

        if ($.trim($('#pythonEntryPoint').val()) === '') {
          $('#pythonEntryPoint').val('main.py');
        }

        if (force === true || $.trim(editor.val()) === '') {
          editor.val(pythonInlineJobSeekerTemplate());
          pythonInlineOpenPanes.code = true;
          pythonInlineActivePane = 'code';
          updatePythonInlineEditor();
          renderPythonInlineWorkspace();
          updateJobCreationReview();
        }
      }

      function randomJobNameToken() {
        return ('000' + Math.floor(Math.random() * 46656).toString(36)).slice(-3);
      }

      function generateJobName() {
        var prefix = petNamePrefixes[Math.floor(Math.random() * petNamePrefixes.length)];
        var suffix = petNameSuffixes[Math.floor(Math.random() * petNameSuffixes.length)];

        return prefix + '-' + suffix + '-' + randomJobNameToken();
      }

      function ensureJobName() {
        var jobName = $.trim($('#job_name').val());

        if (jobName == '') {
          jobName = generateJobName();
          $('#job_name').val(jobName);
          toastr.info('Generated job name: ' + jobName, 'Job Name');
        }

        return jobName;
      }

      function uniqueValues(values) {
        var seen = {};
        var unique = [];

        $.each(values || [], function(index, value) {
          value = $.trim(String(value || ''));
          if (value === '' || seen[value]) {
            return;
          }

          seen[value] = true;
          unique.push(value);
        });

        return unique;
      }

      function collectJobNames(includeGeneratedPlaceholder) {
        var names = [];
        var primaryName = $.trim($('#job_name').val());
        var additionalNames = $.trim($('#job_names').val());

        if (primaryName !== '') {
          names.push(primaryName);
        }

        if (additionalNames !== '') {
          names = names.concat(additionalNames.split(/[\r\n,;]+/));
        }

        names = uniqueValues(names);

        if (names.length === 0 && includeGeneratedPlaceholder) {
          names.push('Auto-generated name');
        }

        return names;
      }

      function normalizeJobFlowName(value) {
        return $.trim(String(value == null ? '' : value));
      }

      function findDraftIndexByName(jobName) {
        jobName = normalizeJobFlowName(jobName);

        for (var index = 0; index < jobDrafts.length; index++) {
          if (normalizeJobFlowName(jobDrafts[index].job_name) === jobName) {
            return index;
          }
        }

        return -1;
      }

      function draftNameMap() {
        var names = {};

        $.each(jobDrafts, function(index, draft) {
          var name = normalizeJobFlowName(draft.job_name);
          if (name !== '') {
            names[name] = index;
          }
        });

        return names;
      }

      function ensureDraftNameForFlow(index) {
        ensureJobDraftsInitialized();

        if (index < 0 || index >= jobDrafts.length) {
          return '';
        }

        var draft = jobDrafts[index];
        var name = normalizeJobFlowName(draft.job_name);

        if (name === '') {
          name = generateJobName();
          draft.job_name = name;

          if (index === activeDraftIndex) {
            $('#job_name').val(name);
          }

          updateDraftNamesTextarea();
          renderJobDraftTabs();
          renderJobDraftComparison();
        }

        return name;
      }

      function normalizeAvailableJobs(jobs) {
        var names = [];
        var seen = {};

        $.each(jobs || [], function(index, row) {
          var name = normalizeJobFlowName(jobNameFromRow(row));
          if (name !== '' && ! seen[name]) {
            seen[name] = true;
            names.push({ name: name, row: row });
          }
        });

        names.sort(function(left, right) {
          return left.name.localeCompare(right.name);
        });

        return names;
      }

      function updateJobFlowSelectOptions() {
        var downstreamSelection = normalizeArray($('#jobList').val());
        var upstreamSelection = normalizeArray($('#upstreamJobList').val());
        var availableNames = {};
        var draftNames = draftNameMap();

        $('#jobList, #upstreamJobList').empty();

        $.each(availableJobCache, function(index, item) {
          if (! availableJobMatchesGlobalEnvironment(item)) {
            return;
          }

          availableNames[item.name] = true;
          ensureSelectOption('#jobList', item.name);
          ensureSelectOption('#upstreamJobList', item.name);
        });

        $.each(jobDrafts, function(index, draft) {
          var name = normalizeJobFlowName(draft.job_name);
          if (name !== '') {
            ensureSelectOption('#jobList', name);
            ensureSelectOption('#upstreamJobList', name);
          }
        });

        $('#jobList').val($.grep(downstreamSelection, function(value) { return availableNames[value] || draftNames[value] != null; }));
        $('#upstreamJobList').val($.grep(upstreamSelection, function(value) { return availableNames[value] || draftNames[value] != null; }));
      }

      function rememberAvailableJobs(jobs) {
        var previousRows = {};

        $.each(availableJobCache, function(index, item) {
          previousRows[item.name] = item.row;
        });

        availableJobCache = normalizeAvailableJobs(jobs);
        $.each(availableJobCache, function(index, item) {
          if (previousRows[item.name] && previousRows[item.name].environmentHydrated) {
            item.row.environmentInfo = previousRows[item.name].environmentInfo;
            item.row.environmentHydrated = true;
          }
        });

        hydrateAvailableJobEnvironments();
        updateJobFlowSelectOptions();
        renderJobPipelineGraph();
      }

      function ensureAvailableJobsLoaded(forceRefresh) {
        if (availableJobsLoading || (! forceRefresh && availableJobCache.length)) {
          return;
        }

        availableJobsLoading = true;
        renderJobPipelineGraph();

        $.ajax({
          url: '<?php echo base_url(); ?>jobCreation/availableJobs',
          type: 'GET',
          dataType: 'json',
          data: availableJobsRequestData()
        }).done(function(response) {
          rememberAvailableJobs(response && response.jobs ? response.jobs : []);
        }).fail(function(xhr, textStatus) {
          if (textStatus === 'abort' || (xhr && xhr.status === 0)) {
            return;
          }

          var message = xhr && xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Failed to fetch available jobs from server';
          toastr.error(message, 'Job Graph');
        }).always(function() {
          availableJobsLoading = false;
          renderJobPipelineGraph();
        });
      }

      function addUniqueDraftArrayValue(draft, field, value) {
        value = normalizeJobFlowName(value);
        if (value === '') {
          return false;
        }

        if ($.inArray(value, normalizeArray(draft[field])) !== -1) {
          return false;
        }

        draft[field] = uniqueValues(normalizeArray(draft[field]).concat([value]));
        return true;
      }

      function removeDraftArrayValue(draft, field, value) {
        value = normalizeJobFlowName(value);
        draft[field] = $.grep(normalizeArray(draft[field]), function(item) {
          return normalizeJobFlowName(item) !== value;
        });
      }

      function refreshDraftPipelineEnabled(draft) {
        if (! draft) {
          return;
        }

        if (normalizeArray(draft.jobList).length || normalizeArray(draft.upstreamJobList).length) {
          draft.runJobCheck = '1';
        } else if (draft.runJobCheck !== '1') {
          draft.runJobCheck = '0';
        }
      }

      function draftForJobFlowNode(node) {
        if (! node || node.type !== 'draft') {
          return null;
        }

        var index = parseInt(node.draftIndex, 10);
        if (isNaN(index) || index < 0 || index >= jobDrafts.length) {
          index = findDraftIndexByName(node.name);
        }

        return index >= 0 ? { draft: jobDrafts[index], index: index } : null;
      }

      function normalizeJobFlowNode(node) {
        node = $.extend({ type: 'existing', name: '', draftIndex: -1 }, node || {});
        node.type = node.type === 'draft' ? 'draft' : 'existing';
        node.draftIndex = parseInt(node.draftIndex, 10);

        if (node.type === 'draft') {
          if (isNaN(node.draftIndex) || node.draftIndex < 0) {
            node.draftIndex = findDraftIndexByName(node.name);
          }
          node.name = ensureDraftNameForFlow(node.draftIndex);
        } else {
          node.name = normalizeJobFlowName(node.name);
        }

        return node;
      }

      function activeJobFlowNode() {
        return normalizeJobFlowNode({
          type: 'draft',
          draftIndex: activeDraftIndex,
          name: jobDrafts[activeDraftIndex] ? jobDrafts[activeDraftIndex].job_name : ''
        });
      }

      function syncActiveDraftPipelineFields() {
        ensureJobDraftsInitialized();
        var activeDraft = jobDrafts[activeDraftIndex] || createEmptyDraft('');

        refreshDraftPipelineEnabled(activeDraft);
        updateJobFlowSelectOptions();
        setSelectValues('#jobList', normalizeArray(activeDraft.jobList));
        setSelectValues('#upstreamJobList', normalizeArray(activeDraft.upstreamJobList));
        $('#runJobCheck').prop('checked', draftChecked(activeDraft, 'runJobCheck'));
        refreshJobOptionPanels();
      }

      function addJobFlowConnection(sourceNode, targetNode) {
        ensureJobDraftsInitialized();
        saveActiveJobDraft(false);

        sourceNode = normalizeJobFlowNode(sourceNode);
        targetNode = normalizeJobFlowNode(targetNode);

        if (sourceNode.name === '' || targetNode.name === '') {
          toastr.error('Both jobs need names before they can be wired.', 'Job Graph');
          return false;
        }

        if (sourceNode.name === targetNode.name) {
          toastr.warning('A job cannot trigger itself.', 'Job Graph');
          return false;
        }

        if (sourceNode.type === 'existing' && targetNode.type === 'existing') {
          toastr.warning('Existing-to-existing links are managed from the source job in Jenkins.', 'Job Graph');
          return false;
        }

        var sourceDraftInfo = draftForJobFlowNode(sourceNode);
        var targetDraftInfo = draftForJobFlowNode(targetNode);
        var changed = false;

        if (sourceDraftInfo) {
          changed = addUniqueDraftArrayValue(sourceDraftInfo.draft, 'jobList', targetNode.name);
          sourceDraftInfo.draft.runJobCheck = '1';
        } else if (targetDraftInfo) {
          changed = addUniqueDraftArrayValue(targetDraftInfo.draft, 'upstreamJobList', sourceNode.name);
          targetDraftInfo.draft.runJobCheck = '1';
        } else {
          return false;
        }

        if (! changed) {
          toastr.info('That link already exists.', 'Job Graph');
          return false;
        }

        if (sourceDraftInfo && targetDraftInfo) {
          removeDraftArrayValue(targetDraftInfo.draft, 'upstreamJobList', sourceNode.name);
        }

        syncActiveDraftPipelineFields();
        updateJobCreationReview();
        return true;
      }

      function removeActiveJobFlowConnection(lane, node) {
        ensureJobDraftsInitialized();
        saveActiveJobDraft(false);

        var activeDraft = jobDrafts[activeDraftIndex] || createEmptyDraft('');
        var activeName = ensureDraftNameForFlow(activeDraftIndex);
        node = normalizeJobFlowNode(node);

        if (lane === 'downstream') {
          removeDraftArrayValue(activeDraft, 'jobList', node.name);
        } else if (lane === 'upstream') {
          if (node.type === 'draft') {
            var sourceDraftInfo = draftForJobFlowNode(node);
            if (sourceDraftInfo) {
              removeDraftArrayValue(sourceDraftInfo.draft, 'jobList', activeName);
              refreshDraftPipelineEnabled(sourceDraftInfo.draft);
            }
          } else {
            removeDraftArrayValue(activeDraft, 'upstreamJobList', node.name);
          }
        }

        refreshDraftPipelineEnabled(activeDraft);
        if (! normalizeArray(activeDraft.jobList).length && ! normalizeArray(activeDraft.upstreamJobList).length) {
          activeDraft.runJobCheck = '0';
        }

        syncActiveDraftPipelineFields();
        updateJobCreationReview();
      }

      function inferJobFlowNode(jobName) {
        var draftIndex = findDraftIndexByName(jobName);

        return {
          type: draftIndex >= 0 ? 'draft' : 'existing',
          draftIndex: draftIndex,
          name: jobName
        };
      }

      function activeUpstreamFlowNodes(activeDraft) {
        var nodes = [];
        var seen = {};
        var activeName = normalizeJobFlowName(activeDraft.job_name);

        $.each(normalizeArray(activeDraft.upstreamJobList), function(index, jobName) {
          jobName = normalizeJobFlowName(jobName);
          if (jobName !== '' && ! seen['existing:' + jobName]) {
            seen['existing:' + jobName] = true;
            nodes.push({ type: 'existing', draftIndex: -1, name: jobName });
          }
        });

        if (activeName !== '') {
          $.each(jobDrafts, function(index, draft) {
            if (index === activeDraftIndex || $.inArray(activeName, normalizeArray(draft.jobList)) === -1) {
              return;
            }

            var sourceName = normalizeJobFlowName(draft.job_name);
            if (sourceName !== '' && ! seen['draft:' + sourceName]) {
              seen['draft:' + sourceName] = true;
              nodes.push({ type: 'draft', draftIndex: index, name: sourceName });
            }
          });
        }

        return nodes;
      }

      function activeDownstreamFlowNodes(activeDraft) {
        var nodes = [];
        var seen = {};

        $.each(normalizeArray(activeDraft.jobList), function(index, jobName) {
          jobName = normalizeJobFlowName(jobName);
          if (jobName === '' || seen[jobName]) {
            return;
          }

          seen[jobName] = true;
          nodes.push(inferJobFlowNode(jobName));
        });

        return nodes;
      }

      function jobFlowNodeIcon(type) {
        return type === 'draft' ? 'fa fa-file-code-o' : 'fa fa-cogs';
      }

      function jobFlowNodeMeta(node) {
        if (node.type === 'draft') {
          return node.draftIndex === activeDraftIndex ? 'Active draft' : 'Draft tab ' + (node.draftIndex + 1);
        }

        return 'Existing Jenkins job';
      }

      function jobFlowNodeIdentity(node) {
        return (node.type || 'existing') + ':' + normalizeJobFlowName(node.name);
      }

      function renderJobFlowNode(node, options) {
        options = options || {};
        node = $.extend({ type: 'existing', draftIndex: -1, name: '' }, node || {});

        var name = normalizeJobFlowName(node.name);
        var label = name || (node.type === 'draft' ? draftName(jobDrafts[node.draftIndex], node.draftIndex, true) : 'Unnamed job');
        var isSelected = jobFlowSelectedNode && jobFlowNodeIdentity(jobFlowSelectedNode) === jobFlowNodeIdentity(node);
        var removeButton = '';

        if (options.removable) {
          removeButton = '<button type="button" class="job-flow-remove-edge" title="Remove link" data-flow-lane="' + escapeAttribute(options.lane || '') + '" data-flow-type="' + escapeAttribute(node.type) + '" data-flow-name="' + escapeAttribute(name) + '" data-flow-draft-index="' + escapeAttribute(node.draftIndex) + '"><i class="fa fa-times"></i></button>';
        }

        return '<div class="job-flow-node job-flow-node-' + escapeAttribute(node.type) + (options.active ? ' job-flow-active-node' : '') + (options.removable ? ' job-flow-edge-node' : '') + (isSelected ? ' is-selected' : '') + '" role="button" tabindex="0" draggable="true" data-flow-type="' + escapeAttribute(node.type) + '" data-flow-name="' + escapeAttribute(name) + '" data-flow-draft-index="' + escapeAttribute(node.draftIndex) + '">' +
          '<span class="job-flow-node-icon"><i class="' + escapeAttribute(jobFlowNodeIcon(node.type)) + '"></i></span>' +
          '<span class="job-flow-node-main"><span class="job-flow-node-title">' + escapeHtml(label) + '</span><span class="job-flow-node-meta">' + escapeHtml(jobFlowNodeMeta(node)) + '</span></span>' +
          removeButton +
        '</div>';
      }

      function jobFlowNodeMatchesSearch(name, query) {
        return query === '' || name.toLowerCase().indexOf(query) !== -1;
      }

      function renderJobPipelineGraph() {
        if (! $('#jobFlowBuilder').length) {
          return;
        }

        ensureJobDraftsInitialized();
        updateJobFlowSelectOptions();

        var query = $.trim($('#jobFlowSearch').val() || '').toLowerCase();
        var activeDraft = jobDrafts[activeDraftIndex] || createEmptyDraft('');
        var activeNode = {
          type: 'draft',
          draftIndex: activeDraftIndex,
          name: normalizeJobFlowName(activeDraft.job_name)
        };
        var draftNames = draftNameMap();
        var draftPalette = [];
        var existingPalette = [];

        $.each(jobDrafts, function(index, draft) {
          if (index === activeDraftIndex) {
            return;
          }

          var label = draftName(draft, index, true);
          if (jobFlowNodeMatchesSearch(label, query)) {
            draftPalette.push(renderJobFlowNode({ type: 'draft', draftIndex: index, name: normalizeJobFlowName(draft.job_name) }));
          }
        });

        $.each(availableJobCache, function(index, item) {
          if (draftNames[item.name] != null || ! availableJobMatchesGlobalEnvironment(item) || ! jobFlowNodeMatchesSearch(item.name, query)) {
            return;
          }

          existingPalette.push(renderJobFlowNode({ type: 'existing', draftIndex: -1, name: item.name }));
        });

        $('#jobFlowPalette').html(
          '<div class="job-flow-palette-section"><span class="job-flow-section-title">Temporary Drafts</span>' +
          (draftPalette.length ? draftPalette.join('') : '<div class="job-flow-empty-palette">No other drafts</div>') +
          '</div><div class="job-flow-palette-section"><span class="job-flow-section-title">Created Jobs</span>' +
          (existingPalette.length ? existingPalette.join('') : '<div class="job-flow-empty-palette">' + (availableJobsLoading ? 'Loading jobs...' : 'No matching jobs') + '</div>') +
          '</div>'
        );

        $('#jobFlowActiveNode').html(renderJobFlowNode(activeNode, { active: true }));

        var upstreamNodes = activeUpstreamFlowNodes(activeDraft);
        var downstreamNodes = activeDownstreamFlowNodes(activeDraft);

        $('#jobFlowUpstreamList').html(upstreamNodes.length ? $.map(upstreamNodes, function(node) {
          return renderJobFlowNode(node, { removable: true, lane: 'upstream' });
        }).join('') : '<div class="job-flow-empty-lane">No upstream jobs</div>');

        $('#jobFlowDownstreamList').html(downstreamNodes.length ? $.map(downstreamNodes, function(node) {
          return renderJobFlowNode(node, { removable: true, lane: 'downstream' });
        }).join('') : '<div class="job-flow-empty-lane">No downstream jobs</div>');

        if (jobFlowSelectedNode) {
          $('#jobFlowSelected').addClass('is-visible').html('<i class="fa fa-crosshairs"></i> Source selected: <b>' + escapeHtml(jobFlowSelectedNode.name || 'Unnamed job') + '</b>');
        } else {
          $('#jobFlowSelected').removeClass('is-visible').empty();
        }
      }

      function jobFlowNodeFromElement(element) {
        var node = {
          type: $(element).attr('data-flow-type') || 'existing',
          name: $(element).attr('data-flow-name') || '',
          draftIndex: $(element).attr('data-flow-draft-index') || -1
        };

        return normalizeJobFlowNode(node);
      }

      function selectOrConnectJobFlowNode(node) {
        if (! node || node.name === '') {
          return;
        }

        if (! jobFlowSelectedNode) {
          jobFlowSelectedNode = node;
          renderJobPipelineGraph();
          return;
        }

        if (jobFlowNodeIdentity(jobFlowSelectedNode) === jobFlowNodeIdentity(node)) {
          jobFlowSelectedNode = null;
          renderJobPipelineGraph();
          return;
        }

        addJobFlowConnection(jobFlowSelectedNode, node);
        jobFlowSelectedNode = null;
        renderJobPipelineGraph();
      }

      function chainJobDraftTabs() {
        ensureJobDraftsInitialized();
        saveActiveJobDraft(false);

        if (jobDrafts.length < 2) {
          toastr.info('Add at least two drafts before chaining.', 'Job Graph');
          return;
        }

        for (var index = 0; index < jobDrafts.length; index++) {
          ensureDraftNameForFlow(index);
        }

        for (var sourceIndex = 0; sourceIndex < jobDrafts.length - 1; sourceIndex++) {
          addUniqueDraftArrayValue(jobDrafts[sourceIndex], 'jobList', jobDrafts[sourceIndex + 1].job_name);
          jobDrafts[sourceIndex].runJobCheck = '1';
        }

        syncActiveDraftPipelineFields();
        updateJobCreationReview();
        toastr.success('Draft tabs chained in order.', 'Job Graph');
      }

      function clearActiveJobWiring() {
        ensureJobDraftsInitialized();
        saveActiveJobDraft(false);

        var activeName = ensureDraftNameForFlow(activeDraftIndex);
        var activeDraft = jobDrafts[activeDraftIndex] || createEmptyDraft('');
        activeDraft.jobList = [];
        activeDraft.upstreamJobList = [];
        activeDraft.runJobCheck = '0';

        $.each(jobDrafts, function(index, draft) {
          if (index !== activeDraftIndex) {
            removeDraftArrayValue(draft, 'jobList', activeName);
            refreshDraftPipelineEnabled(draft);
          }
        });

        jobFlowSelectedNode = null;
        syncActiveDraftPipelineFields();
        updateJobCreationReview();
      }

      function setBulkDraftsVisible(isVisible) {
        isVisible = !!isVisible;
        $('#bulkDraftsPanel').toggle(isVisible);
        $('#toggleBulkDrafts')
          .toggleClass('btn-primary', isVisible)
          .toggleClass('btn-default', ! isVisible)
          .find('.bulk-drafts-toggle-label').text(isVisible ? 'Hide Bulk Tools' : 'Show Bulk Tools');
      }

      function updateLinuxCommandEditor() {
        var editor = $('#linuxCommandLine');
        var lineNumbers = $('#linuxCommandLineNumbers');

        if (! editor.length || ! lineNumbers.length) {
          return;
        }

        var value = editor.val() || '';
        var minimumLines = parseInt(editor.attr('rows'), 10) || 1;
        var lineCount = Math.max(value.split('\n').length, minimumLines);
        var lines = [];

        for (var index = 1; index <= lineCount; index++) {
          lines.push(index);
        }

        lineNumbers.html(lines.join('<br>'));
        lineNumbers.scrollTop(editor.scrollTop());
      }

      function escapePythonSyntaxHtml(value) {
        return String(value || '').replace(/[&<>]/g, function(character) {
          return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[character];
        });
      }

      function renderPythonSyntaxLine(line) {
        var tokens = [];
        var tokenPattern = /("""[\s\S]*?"""|'''[\s\S]*?'''|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|#.*|@[A-Za-z_][\w.]*|\b\d+(?:\.\d+)?\b|\b(?:False|None|True|and|as|assert|async|await|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise|return|try|while|with|yield)\b|\b(?:abs|all|any|bool|dict|enumerate|filter|float|int|len|list|map|max|min|open|print|range|set|str|sum|tuple|zip)\b)/g;
        var cursor = 0;
        var match;

        while ((match = tokenPattern.exec(line)) !== null) {
          tokens.push(escapePythonSyntaxHtml(line.slice(cursor, match.index)));
          var token = match[0];
          var className = 'py-keyword';

          if (token.charAt(0) === '#') {
            className = 'py-comment';
          } else if (token.charAt(0) === '\"' || token.charAt(0) === "'") {
            className = 'py-string';
          } else if (token.charAt(0) === '@') {
            className = 'py-decorator';
          } else if (/^\d/.test(token)) {
            className = 'py-number';
          } else if (/^(?:abs|all|any|bool|dict|enumerate|filter|float|int|len|list|map|max|min|open|print|range|set|str|sum|tuple|zip)$/.test(token)) {
            className = 'py-builtin';
          }

          tokens.push('<span class="' + className + '">' + escapePythonSyntaxHtml(token) + '</span>');
          cursor = match.index + token.length;
        }

        tokens.push(escapePythonSyntaxHtml(line.slice(cursor)));
        return tokens.join('');
      }

      function renderPythonSyntaxHighlight(editor, highlight) {
        if (! editor.length || ! highlight.length) {
          return;
        }

        var value = editor.val() || '';
        var rendered = $.map(value.split('\n'), function(line) {
          return renderPythonSyntaxLine(line) || ' ';
        }).join('\n');

        highlight.html(rendered);
        highlight.scrollTop(editor.scrollTop());
        highlight.scrollLeft(editor.scrollLeft());
      }

      function stripPythonLineForLint(line) {
        var cleaned = '';
        var quote = '';
        var escaped = false;

        for (var index = 0; index < line.length; index++) {
          var character = line.charAt(index);

          if (quote !== '') {
            if (escaped) {
              escaped = false;
            } else if (character === '\\') {
              escaped = true;
            } else if (character === quote) {
              quote = '';
            }
            cleaned += ' ';
            continue;
          }

          if (character === '#') {
            break;
          }

          if (character === '"' || character === "'") {
            quote = character;
            cleaned += ' ';
            continue;
          }

          cleaned += character;
        }

        return cleaned;
      }

      function lintPythonInlineCode(source) {
        var issues = [];
        var lines = String(source || '').split('\n');
        var stack = [];
        var pairs = { '(': ')', '[': ']', '{': '}' };
        var closing = { ')': '(', ']': '[', '}': '{' };

        $.each(lines, function(index, line) {
          var lineNumber = index + 1;
          var trimmed = $.trim(line);
          var codeOnly = stripPythonLineForLint(line);

          if (/\t/.test(line)) {
            issues.push({ type: 'warning', line: lineNumber, text: 'Tab indentation detected. Prefer spaces for Python blocks.' });
          }

          if (/\s+$/.test(line)) {
            issues.push({ type: 'warning', line: lineNumber, text: 'Trailing whitespace.' });
          }

          if (/^(if|elif|else|for|while|def|class|try|except|finally|with)\b/.test(trimmed) && stripPythonLineForLint(trimmed).indexOf(':') === -1) {
            issues.push({ type: 'error', line: lineNumber, text: 'Likely missing colon at the end of this block statement.' });
          }

          for (var cursor = 0; cursor < codeOnly.length; cursor++) {
            var character = codeOnly.charAt(cursor);
            if (pairs[character]) {
              stack.push({ character: character, line: lineNumber });
            } else if (closing[character]) {
              var expected = closing[character];
              var last = stack.pop();
              if (! last || last.character !== expected) {
                issues.push({ type: 'error', line: lineNumber, text: 'Unmatched closing bracket ' + character + '.' });
              }
            }
          }
        });

        $.each(stack, function(index, item) {
          issues.push({ type: 'error', line: item.line, text: 'Unclosed bracket ' + item.character + '.' });
        });

        return issues.slice(0, 8);
      }

      function renderPythonLintPanel(panel, issues) {
        if (! panel.length) {
          return;
        }

        if (! issues.length) {
          panel.removeClass('active').empty();
          return;
        }

        var html = ['<strong><i class="fa fa-exclamation-triangle"></i> Python checks</strong><ul>'];
        $.each(issues, function(index, issue) {
          html.push('<li class="python-lint-' + issue.type + '">Line ' + issue.line + ': ' + escapePythonSyntaxHtml(issue.text) + '</li>');
        });
        html.push('</ul>');
        panel.addClass('active').html(html.join(''));
      }

      function handleCodeEditorTab(event, element) {
        if (! event || event.key !== 'Tab') {
          return;
        }

        event.preventDefault();

        var textarea = element;
        var value = textarea.value;
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var indent = '    ';

        if (start !== end) {
          var blockStart = value.lastIndexOf('\n', start - 1) + 1;
          var blockEndAnchor = end > start && value.charAt(end - 1) === '\n' ? end - 1 : end;
          var blockEnd = value.indexOf('\n', blockEndAnchor);
          var selectionStartOffset = start - blockStart;
          var selectionEndOffset = end - blockStart;
          var block;
          var lines;
          var updatedLines;
          var removedBeforeStart = 0;
          var removedBeforeEnd = 0;
          var currentOffset = 0;

          blockEnd = blockEnd === -1 ? value.length : blockEnd;
          block = value.substring(blockStart, blockEnd);
          lines = block.split('\n');

          if (event.shiftKey) {
            updatedLines = $.map(lines, function(line) {
              var removeCount = line.substr(0, indent.length) === indent ? indent.length : (line.charAt(0) === '\t' ? 1 : 0);

              if (currentOffset < selectionStartOffset) {
                removedBeforeStart += Math.min(removeCount, selectionStartOffset - currentOffset);
              }

              if (currentOffset < selectionEndOffset) {
                removedBeforeEnd += Math.min(removeCount, selectionEndOffset - currentOffset);
              }

              currentOffset += line.length + 1;
              return line.substring(removeCount);
            });
          } else {
            updatedLines = $.map(lines, function(line) {
              currentOffset += line.length + 1;
              return indent + line;
            });
          }

          textarea.value = value.substring(0, blockStart) + updatedLines.join('\n') + value.substring(blockEnd);
          textarea.selectionStart = event.shiftKey ? Math.max(blockStart, start - removedBeforeStart) : start + indent.length;
          textarea.selectionEnd = event.shiftKey ? Math.max(textarea.selectionStart, end - removedBeforeEnd) : end + (indent.length * updatedLines.length);
          $(textarea).trigger('input');
          return;
        }

        if (event.shiftKey) {
          var lineStart = value.lastIndexOf('\n', start - 1) + 1;
          var removeCount = value.substr(lineStart, indent.length) === indent ? indent.length : (value.charAt(lineStart) === '\t' ? 1 : 0);

          if (removeCount > 0) {
            textarea.value = value.substring(0, lineStart) + value.substring(lineStart + removeCount);
            textarea.selectionStart = Math.max(lineStart, start - removeCount);
            textarea.selectionEnd = Math.max(lineStart, end - removeCount);
          }
        } else {
          textarea.value = value.substring(0, start) + indent + value.substring(end);
          textarea.selectionStart = textarea.selectionEnd = start + indent.length;
        }

        $(textarea).trigger('input');
      }

      function updatePythonInlineEditor() {
        var editor = $('#pythonInlineCode');
        var lineNumbers = $('#pythonInlineCodeNumbers');

        if (! editor.length || ! lineNumbers.length) {
          return;
        }

        var value = editor.val() || '';
        var minimumLines = parseInt(editor.attr('rows'), 10) || 1;
        var lineCount = Math.max(value.split('\n').length, minimumLines);
        var lines = [];

        for (var index = 1; index <= lineCount; index++) {
          lines.push(index);
        }

        lineNumbers.html(lines.join('<br>'));
        lineNumbers.scrollTop(editor.scrollTop());
        renderPythonSyntaxHighlight(editor, $('#pythonInlineCodeHighlight'));
        renderPythonLintPanel($('#pythonInlineLintPanel'), lintPythonInlineCode(value));
        $('#pythonInlineEditorFile, #pythonInlineEditorActiveFile').text($.trim($('#pythonEntryPoint').val()) || 'main.py');
      }

      function updatePythonRequirementsEditor() {
        var editor = $('#pythonRequirementsText');
        var lineNumbers = $('#pythonRequirementsTextNumbers');

        if (! editor.length || ! lineNumbers.length) {
          return;
        }

        var value = editor.val() || '';
        var minimumLines = parseInt(editor.attr('rows'), 10) || 1;
        var lineCount = Math.max(value.split('\n').length, minimumLines);
        var lines = [];

        for (var index = 1; index <= lineCount; index++) {
          lines.push(index);
        }

        lineNumbers.html(lines.join('<br>'));
        lineNumbers.scrollTop(editor.scrollTop());
      }

      function updatePythonDockerfileEditor() {
        var editor = $('#pythonDockerfileText');
        var lineNumbers = $('#pythonDockerfileTextNumbers');

        if (! editor.length || ! lineNumbers.length) {
          return;
        }

        var value = editor.val() || '';
        var minimumLines = parseInt(editor.attr('rows'), 10) || 1;
        var lineCount = Math.max(value.split('\n').length, minimumLines);
        var lines = [];

        for (var index = 1; index <= lineCount; index++) {
          lines.push(index);
        }

        lineNumbers.html(lines.join('<br>'));
        lineNumbers.scrollTop(editor.scrollTop());
      }

      function updatePythonInlineExtraEditor() {
        var editor = $('#pythonInlineExtraCode');
        var lineNumbers = $('#pythonInlineExtraCodeNumbers');

        if (! editor.length || ! lineNumbers.length) {
          return;
        }

        var value = editor.val() || '';
        var minimumLines = parseInt(editor.attr('rows'), 10) || 1;
        var lineCount = Math.max(value.split('\n').length, minimumLines);
        var lines = [];

        for (var index = 1; index <= lineCount; index++) {
          lines.push(index);
        }

        lineNumbers.html(lines.join('<br>'));
        lineNumbers.scrollTop(editor.scrollTop());
        renderPythonSyntaxHighlight(editor, $('#pythonInlineExtraCodeHighlight'));
        renderPythonLintPanel($('#pythonInlineExtraLintPanel'), lintPythonInlineCode(value));
        $('#pythonInlineExtraTabFile, #pythonInlineExtraEditorFile').text(pythonInlineActiveExtraPath || 'lib.py');
      }

      function pythonWorkspaceAllowsInlineCode() {
        return $('#linuxExecutionStrategy').val() == 'python_inline';
      }

      function pythonInlineDockerfileAvailable() {
        return pythonWorkspaceAllowsInlineCode() && $('#pythonRuntimeMode').val() == 'docker';
      }

      function normalizePythonInlineWorkspacePath(path, requirePythonFile) {
        path = $.trim(String(path || '')).replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
        if (path === '') {
          return '';
        }

        var segments = path.split('/');
        for (var index = 0; index < segments.length; index++) {
          var segment = segments[index];
          if (segment === '' || segment === '.' || segment === '..' || segment.charAt(0) === '.' || ! /^[A-Za-z0-9._ -]+$/.test(segment)) {
            return '';
          }
        }

        var normalizedPath = segments.join('/');
        var lowerPath = normalizedPath.toLowerCase();
        if (lowerPath === 'requirements.txt' || lowerPath === 'dockerfile' || lowerPath.indexOf('__pycache__/') !== -1 || lowerPath.indexOf('.jobseeker-python-libs/') !== -1) {
          return '';
        }

        if (requirePythonFile && ! /\.py$/i.test(normalizedPath)) {
          return '';
        }

        return normalizedPath;
      }

      function pythonInlineEntryPath() {
        return normalizePythonInlineWorkspacePath($.trim($('#pythonEntryPoint').val()) || 'main.py', true) || 'main.py';
      }

      function pythonInlineExtraFile(path) {
        path = normalizePythonInlineWorkspacePath(path, true);
        for (var index = 0; index < pythonInlineExtraFiles.length; index++) {
          if (pythonInlineExtraFiles[index].path === path) {
            return pythonInlineExtraFiles[index];
          }
        }

        return null;
      }

      function sortPythonInlineWorkspace() {
        pythonInlineDirectories = uniqueValues(pythonInlineDirectories).sort();
        pythonInlineExtraFiles.sort(function(left, right) {
          return left.path.localeCompare(right.path);
        });
      }

      function ensurePythonInlineParentDirectories(path) {
        var parts = normalizePythonInlineWorkspacePath(path, true).split('/');
        var currentPath = '';

        for (var index = 0; index < parts.length - 1; index++) {
          currentPath = currentPath === '' ? parts[index] : currentPath + '/' + parts[index];
          if ($.inArray(currentPath, pythonInlineDirectories) === -1) {
            pythonInlineDirectories.push(currentPath);
          }
        }
      }

      function captureActivePythonInlineExtraFile() {
        if (pythonInlineActivePane != 'extra' || pythonInlineActiveExtraPath === '') {
          return;
        }

        var file = pythonInlineExtraFile(pythonInlineActiveExtraPath);
        if (file) {
          file.content = $('#pythonInlineExtraCode').val() || '';
        }
      }

      function syncPythonInlineFilesInput(captureActive) {
        if (captureActive !== false) {
          captureActivePythonInlineExtraFile();
        }

        sortPythonInlineWorkspace();
        $('#pythonInlineFilesJson').val(JSON.stringify({
          files: pythonInlineExtraFiles,
          directories: pythonInlineDirectories
        }));
      }

      function loadPythonInlineFilesFromHidden() {
        var payload = null;

        try {
          payload = JSON.parse($('#pythonInlineFilesJson').val() || '{"files":[],"directories":[]}');
        } catch (error) {
          payload = { files: [], directories: [] };
        }

        loadPythonInlineFilesPayload(payload);
      }

      function loadPythonInlineFilesPayload(payload) {
        pythonInlineExtraFiles = [];
        pythonInlineDirectories = [];

        $.each(payload && $.isArray(payload.directories) ? payload.directories : [], function(index, directory) {
          var path = normalizePythonInlineWorkspacePath(directory && directory.path ? directory.path : directory, false);
          if (path !== '' && $.inArray(path, pythonInlineDirectories) === -1) {
            pythonInlineDirectories.push(path);
          }
        });

        $.each(payload && $.isArray(payload.files) ? payload.files : [], function(index, file) {
          var path = normalizePythonInlineWorkspacePath(file && file.path ? file.path : '', true);
          if (path !== '' && path.toLowerCase() !== pythonInlineEntryPath().toLowerCase() && ! pythonInlineExtraFile(path)) {
            pythonInlineExtraFiles.push({ path: path, content: String(file.content || '') });
            ensurePythonInlineParentDirectories(path);
          }
        });

        syncPythonInlineFilesInput(false);
        renderPythonInlineWorkspace();
      }

      function resetPythonInlineWorkspaceState() {
        pythonInlineOpenPanes = { code: true, requirements: false, dockerfile: false, extra: false };
        pythonInlineActivePane = 'code';
        pythonInlineActiveExtraPath = '';
        pythonInlineExtraFiles = [];
        pythonInlineDirectories = [];
        $('#pythonInlineExtraCode').val('');
        syncPythonInlineFilesInput(false);
        renderPythonInlineWorkspace();
      }

      function pythonInlinePaneAvailable(pane) {
        if (pane == 'code' || pane == 'requirements') {
          return pythonWorkspaceAllowsInlineCode();
        }

        if (pane == 'dockerfile') {
          return pythonInlineDockerfileAvailable();
        }

        if (pane == 'extra') {
          return pythonWorkspaceAllowsInlineCode() && pythonInlineActiveExtraPath !== '' && !! pythonInlineExtraFile(pythonInlineActiveExtraPath);
        }

        return false;
      }

      function choosePythonInlineFallbackPane() {
        if (pythonInlineOpenPanes.code && pythonInlinePaneAvailable('code')) {
          return { pane: 'code', path: '' };
        }

        if (pythonInlineOpenPanes.requirements && pythonInlinePaneAvailable('requirements')) {
          return { pane: 'requirements', path: '' };
        }

        if (pythonInlineOpenPanes.dockerfile && pythonInlinePaneAvailable('dockerfile')) {
          return { pane: 'dockerfile', path: '' };
        }

        if (pythonInlineOpenPanes.extra && pythonInlinePaneAvailable('extra')) {
          return { pane: 'extra', path: pythonInlineActiveExtraPath };
        }

        return { pane: '', path: '' };
      }

      function renderPythonInlineWorkspace() {
        var isInline = pythonWorkspaceAllowsInlineCode();
        var isDocker = pythonInlineDockerfileAvailable();

        if (! isDocker) {
          pythonInlineOpenPanes.dockerfile = false;
          if (pythonInlineActivePane == 'dockerfile') {
            pythonInlineActivePane = '';
          }
        }

        if (! isInline) {
          pythonInlineOpenPanes.code = false;
          pythonInlineOpenPanes.requirements = false;
          pythonInlineOpenPanes.extra = false;
          pythonInlineActivePane = '';
        }

        if (! pythonInlinePaneAvailable(pythonInlineActivePane) || (pythonInlineActivePane != '' && ! pythonInlineOpenPanes[pythonInlineActivePane])) {
          var fallback = choosePythonInlineFallbackPane();
          pythonInlineActivePane = fallback.pane;
          pythonInlineActiveExtraPath = fallback.path;
        }

        $('.python-inline-sidebar-actions').toggle(isInline);
        $('.python-inline-tab[data-python-inline-pane="code"]').toggle(isInline && pythonInlineOpenPanes.code);
        $('.python-inline-tab[data-python-inline-pane="requirements"]').toggle(isInline && pythonInlineOpenPanes.requirements);
        $('.python-inline-tab[data-python-inline-pane="dockerfile"]').toggle(isDocker && pythonInlineOpenPanes.dockerfile);
        $('.python-inline-tab[data-python-inline-pane="extra"]').toggle(isInline && pythonInlineOpenPanes.extra && pythonInlineActiveExtraPath !== '');

        $('.python-inline-tab').removeClass('active').filter('[data-python-inline-pane="' + pythonInlineActivePane + '"]').addClass('active');
        $('.python-inline-editor-panel').removeClass('active').filter('[data-python-inline-pane="' + pythonInlineActivePane + '"]').addClass('active');
        $('#pythonInlineEmptyState').toggleClass('active', pythonInlineActivePane === '');
        $('#runPythonInlinePreview').toggle(isInline);

        var rows = [];
        if (isInline) {
          rows.push('<div class="python-inline-file-row"><button type="button" class="python-inline-file' + (pythonInlineActivePane == 'code' ? ' active' : '') + '" data-python-inline-pane="code"><i class="fa fa-code"></i><span>' + escapeHtml(pythonInlineEntryPath()) + '</span></button></div>');
          rows.push('<div class="python-inline-file-row"><button type="button" class="python-inline-file' + (pythonInlineActivePane == 'requirements' ? ' active' : '') + '" data-python-inline-pane="requirements"><i class="fa fa-list-alt"></i><span>requirements.txt</span></button></div>');

          if (isDocker) {
            rows.push('<div class="python-inline-file-row"><button type="button" class="python-inline-file' + (pythonInlineActivePane == 'dockerfile' ? ' active' : '') + '" data-python-inline-pane="dockerfile"><i class="fa fa-cube"></i><span>Dockerfile</span></button></div>');
          }

          $.each(pythonInlineDirectories, function(index, directory) {
            rows.push('<div class="python-inline-file-row"><span class="python-inline-folder"><i class="fa fa-folder-o"></i><span>' + escapeHtml(directory) + '</span></span><button type="button" class="python-inline-file-remove" title="Remove" data-python-inline-type="directory" data-python-inline-path="' + escapeAttribute(directory) + '"><i class="fa fa-trash"></i></button></div>');
          });

          $.each(pythonInlineExtraFiles, function(index, file) {
            rows.push('<div class="python-inline-file-row"><button type="button" class="python-inline-file' + (pythonInlineActivePane == 'extra' && pythonInlineActiveExtraPath == file.path ? ' active' : '') + '" data-python-inline-pane="extra" data-python-inline-path="' + escapeAttribute(file.path) + '"><i class="fa fa-file-code-o"></i><span>' + escapeHtml(file.path) + '</span></button><button type="button" class="python-inline-file-remove" title="Remove" data-python-inline-type="file" data-python-inline-path="' + escapeAttribute(file.path) + '"><i class="fa fa-trash"></i></button></div>');
          });
        }

        $('#pythonInlineFileList').html(rows.join(''));
        updatePythonInlineEditor();
        updatePythonRequirementsEditor();
        updatePythonDockerfileEditor();
        updatePythonInlineExtraEditor();
      }

      function setPythonInlinePane(pane) {
        var extraPath = arguments.length > 1 ? arguments[1] : '';
        captureActivePythonInlineExtraFile();
        pane = pane == 'extra' ? 'extra' : (pane == 'dockerfile' ? 'dockerfile' : (pane == 'requirements' ? 'requirements' : 'code'));

        if (pane == 'extra') {
          extraPath = normalizePythonInlineWorkspacePath(extraPath, true);
          if (extraPath === '' || ! pythonInlineExtraFile(extraPath)) {
            return;
          }

          pythonInlineActiveExtraPath = extraPath;
        } else if (! pythonInlinePaneAvailable(pane)) {
          return;
        }

        pythonInlineOpenPanes[pane] = true;
        pythonInlineActivePane = pane;

        if (pane == 'extra') {
          $('#pythonInlineExtraCode').val(pythonInlineExtraFile(pythonInlineActiveExtraPath).content || '');
        }

        renderPythonInlineWorkspace();

        if (pane == 'requirements') {
          $('#pythonRequirementsText').trigger('focus');
        } else if (pane == 'dockerfile') {
          $('#pythonDockerfileText').trigger('focus');
        } else if (pane == 'extra') {
          $('#pythonInlineExtraCode').trigger('focus');
        } else {
          $('#pythonInlineCode').trigger('focus');
        }
      }

      function closePythonInlinePane(pane) {
        captureActivePythonInlineExtraFile();
        pane = pane == 'extra' ? 'extra' : (pane == 'dockerfile' ? 'dockerfile' : (pane == 'requirements' ? 'requirements' : 'code'));
        pythonInlineOpenPanes[pane] = false;

        if (pythonInlineActivePane == pane) {
          pythonInlineActivePane = '';
        }

        renderPythonInlineWorkspace();
      }

      function addPythonInlineFile() {
        if (! pythonWorkspaceAllowsInlineCode()) {
          return;
        }

        var path = normalizePythonInlineWorkspacePath(window.prompt('Python file path', 'libs/helper.py'), true);
        if (path === '') {
          return;
        }

        if (path.toLowerCase() === pythonInlineEntryPath().toLowerCase()) {
          toastr.error('Use the entry file tab for ' + path + '.', 'Inline Python');
          return;
        }

        if (pythonInlineExtraFile(path)) {
          toastr.error('That inline Python file already exists.', 'Inline Python');
          return;
        }

        ensurePythonInlineParentDirectories(path);
        pythonInlineExtraFiles.push({ path: path, content: '' });
        syncPythonInlineFilesInput(false);
        setPythonInlinePane('extra', path);
        updateJobCreationReview();
      }

      function addPythonInlineFolder() {
        if (! pythonWorkspaceAllowsInlineCode()) {
          return;
        }

        var path = normalizePythonInlineWorkspacePath(window.prompt('Folder path', 'libs'), false);
        if (path === '') {
          return;
        }

        if ($.inArray(path, pythonInlineDirectories) === -1) {
          pythonInlineDirectories.push(path);
        }

        syncPythonInlineFilesInput(false);
        renderPythonInlineWorkspace();
        updateJobCreationReview();
      }

      function removePythonInlineWorkspacePath(path, type) {
        path = normalizePythonInlineWorkspacePath(path, type != 'directory');
        if (path === '') {
          return;
        }

        if (! window.confirm('Remove ' + path + '?')) {
          return;
        }

        if (type == 'directory') {
          pythonInlineDirectories = $.grep(pythonInlineDirectories, function(directory) {
            return directory !== path && directory.indexOf(path + '/') !== 0;
          });
          pythonInlineExtraFiles = $.grep(pythonInlineExtraFiles, function(file) {
            return file.path.indexOf(path + '/') !== 0;
          });
          if (pythonInlineActivePane == 'extra' && pythonInlineActiveExtraPath.indexOf(path + '/') === 0) {
            pythonInlineActivePane = '';
            pythonInlineActiveExtraPath = '';
            pythonInlineOpenPanes.extra = false;
          }
        } else {
          pythonInlineExtraFiles = $.grep(pythonInlineExtraFiles, function(file) {
            return file.path !== path;
          });
          if (pythonInlineActivePane == 'extra' && pythonInlineActiveExtraPath == path) {
            pythonInlineActivePane = '';
            pythonInlineActiveExtraPath = '';
            pythonInlineOpenPanes.extra = false;
          }
        }

        syncPythonInlineFilesInput(false);
        renderPythonInlineWorkspace();
        updateJobCreationReview();
      }

      function dockerImageForPythonVersion(version) {
        version = $.trim(version || $('#pythonVersion').val() || 'python3').replace(/^python/, '');
        return 'python:' + (version || '3') + '-slim';
      }

      function linuxExecutionUsesPython() {
        return ($('#linuxExecutionStrategy').val() == 'python_inline') ||
          ($('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() == 'python');
      }

      function linuxExecutionUsesTalend() {
        return $('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() == 'talend';
      }

      function linuxExecutionHasRuntime() {
        if (! $('#linuxCommand').is(':checked')) {
          return false;
        }

        if ($('#linuxExecutionStrategy').val() == 'command' || $('#linuxExecutionStrategy').val() == 'python_inline') {
          return true;
        }

        return $('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() != '0' && $('#linuxScriptType').val() !== '';
      }

      function dockerImageForLinuxExecution() {
        if (linuxExecutionUsesPython()) {
          return dockerImageForPythonVersion();
        }

        return linuxExecutionUsesTalend() ? 'eclipse-temurin:17-jre-alpine' : 'alpine:3.20';
      }

      function isManagedDockerImage(image) {
        return /^python:3(?:\.[0-9]{1,2})?-slim$/.test(image) || /^(alpine:3\.20|busybox:1\.36|bash:5\.2|debian:12-slim|eclipse-temurin:(?:17-jre-alpine|17-jre-jammy|11-jre-jammy))$/.test(image);
      }

      function updatePythonDockerImageFromVersion() {
        var image = $.trim($('#pythonDockerImage').val() || '');

        if (image === '' || isManagedDockerImage(image)) {
          $('#pythonDockerImage').val(dockerImageForLinuxExecution());
        }
      }

      function updatePythonRuntimeControls() {
        var hasRuntime = linuxExecutionHasRuntime();
        var isDockerRuntime = $('#pythonRuntimeMode').val() == 'docker';
        $('.pythonRuntimeForm').toggle(hasRuntime);

        if (! hasRuntime) {
          return;
        }

        $('.pythonAgentPythonColumn').toggle(! isDockerRuntime && linuxExecutionUsesPython());
        $('.pythonDockerImageColumn').toggle(isDockerRuntime);

        if (isDockerRuntime) {
          updatePythonDockerImageFromVersion();
        }
      }

      function pythonRuntimeSummary() {
        if ($('#pythonRuntimeMode').val() == 'docker') {
          return 'Docker ' + ($.trim($('#pythonDockerImage').val()) || dockerImageForLinuxExecution()) + (pythonWorkspaceAllowsInlineCode() && $.trim($('#pythonDockerfileText').val()) ? ' + custom Dockerfile' : '');
        }

        return linuxExecutionUsesPython() ? 'Jenkins Agent python3' : 'Jenkins Agent shell';
      }

      function loadInlinePythonSource(jobName, entryPoint) {
        $('#pythonInlineCode').val('');
        $('#pythonRequirementsText').val('');
        $('#pythonDockerfileText').val('');
        loadPythonInlineFilesPayload({ files: [], directories: [] });
        updatePythonInlineEditor();
        updatePythonRequirementsEditor();
        updatePythonDockerfileEditor();

        var requestData = {
          job_name: jobName,
          entry_point: entryPoint || 'main.py'
        };

        var sourceRequest = $.ajax({
          type: 'GET',
          url: '<?php echo base_url(); ?>jobCreation/inlinePythonSource',
          data: requestData,
          dataType: 'text'
        }).done(function(sourceCode) {
          $('#pythonInlineCode').val(sourceCode);
          updatePythonInlineEditor();
          updateJobCreationReview();
        }).fail(function() {
          toastr.warning('The inline Python source file could not be loaded from the repository.', 'Edit Job');
        });

        $.ajax({
          type: 'GET',
          url: '<?php echo base_url(); ?>jobCreation/inlinePythonRequirements',
          data: requestData,
          dataType: 'text'
        }).done(function(requirementsText) {
          $('#pythonRequirementsText').val(requirementsText);
          updatePythonRequirementsEditor();
          updateJobCreationReview();
        }).fail(function() {
          $('#pythonRequirementsText').val('');
          updatePythonRequirementsEditor();
        });

        $.ajax({
          type: 'GET',
          url: '<?php echo base_url(); ?>jobCreation/inlinePythonDockerfile',
          data: requestData,
          dataType: 'text'
        }).done(function(dockerfileText) {
          $('#pythonDockerfileText').val(dockerfileText);
          updatePythonDockerfileEditor();
          updateJobCreationReview();
        }).fail(function() {
          $('#pythonDockerfileText').val('');
          updatePythonDockerfileEditor();
        });

        $.ajax({
          type: 'GET',
          url: '<?php echo base_url(); ?>jobCreation/inlinePythonFiles',
          data: requestData,
          dataType: 'json'
        }).done(function(payload) {
          loadPythonInlineFilesPayload(payload);
          updateJobCreationReview();
        }).fail(function() {
          loadPythonInlineFilesPayload({ files: [], directories: [] });
        });

        return sourceRequest;
      }

      function renderPythonInlinePreview(state, title, output) {
        var panel = $('#pythonInlinePreviewPanel');
        panel
          .removeClass('python-preview-success python-preview-failed')
          .addClass('active')
          .toggleClass('python-preview-success', state === 'success')
          .toggleClass('python-preview-failed', state === 'failed')
          .html('<strong>' + escapeHtml(title) + '</strong><pre>' + escapeHtml(output || '') + '</pre>');
      }

      function pythonInlinePreviewError(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          return xhr.responseJSON.message;
        }

        if (xhr && xhr.responseText) {
          return xhr.responseText;
        }

        return fallback || 'Unable to run the inline Python preview.';
      }

      function runPythonInlinePreview() {
        if (! pythonWorkspaceAllowsInlineCode()) {
          toastr.warning('Select Inline Workspace before running the Python preview.', 'Inline Python');
          return;
        }

        if (! requireConcreteGlobalEnvironment()) {
          return;
        }

        syncPythonInlineFilesInput();

        var button = $('#runPythonInlinePreview');
        var originalHtml = button.html();
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        renderPythonInlinePreview('running', 'Running with Jenkins Python...', 'Waiting for Jenkins to start the temporary preview job.');

        $.ajax({
          type: 'POST',
          url: '<?php echo base_url(); ?>jobCreation/runInlinePythonPreview',
          dataType: 'json',
          data: {
            environment: $('#environment').val() || '',
            pythonEntryPoint: $('#pythonEntryPoint').val() || 'main.py',
            pythonInlineCode: $('#pythonInlineCode').val() || '',
            pythonRequirementsText: $('#pythonRequirementsText').val() || '',
            pythonInlineFilesJson: $('#pythonInlineFilesJson').val() || '{"files":[],"directories":[]}',
            pythonVersion: $('#pythonVersion').val() || 'python3'
          }
        }).done(function(response) {
          var ok = !!(response && response.ok);
          var output = response && response.output ? response.output : '';
          var title = response && response.message ? response.message : (ok ? 'Inline Python preview succeeded.' : 'Inline Python preview failed.');

          renderPythonInlinePreview(ok ? 'success' : 'failed', title, output || 'Jenkins finished without console output.');
          if (ok) {
            toastr.success('Inline Python ran successfully on Jenkins.', 'Inline Python');
          } else {
            toastr.error(title, 'Inline Python');
          }
        }).fail(function(xhr) {
          var message = pythonInlinePreviewError(xhr, 'Unable to run the inline Python preview.');
          renderPythonInlinePreview('failed', 'Inline Python preview could not start.', message);
          toastr.error(message, 'Inline Python');
        }).always(function() {
          button.prop('disabled', false).html(originalHtml);
        });
      }

      function selectedText(selector, fallback) {
        var value = $(selector).val();
        var text = $(selector + ' option:selected').text();
        return value && value != '0' ? $.trim(text || value) : fallback;
      }

      function listValue(selector, fallback) {
        var value = $(selector).val();

        if ($.isArray(value)) {
          return value.length ? value.join(',') : fallback;
        }

        return value || fallback;
      }

      function scheduleSummary() {
        if (! $('#checkBuild').is(':checked')) {
          return 'Manual only';
        }

        var action = $('#action').val();
        if (action == 'single') {
          return 'Cron: ' + listValue('#singleMinute', '*') + ' ' + listValue('#singleHour', '*') + ' ' + listValue('#singleDayOfMonth', '*') + ' ' + listValue('#singleMonth', '*') + ' ' + listValue('#singleDayOfWeek', '*');
        }

        if (action == 'repetitive') {
          return 'Every ' + ($('#repetitiveMinute').val() || '*') + ' minute(s), hour ' + ($('#repetitiveHour').val() || '*') + ', day ' + ($('#repetitiveDayOfMonth').val() || '*') + ', month ' + ($('#repetitiveMonth').val() || '*') + ', weekday ' + ($('#repetitiveDayOfWeek').val() || '*');
        }

        if (action == 'tags') {
          return 'Tag: ' + ($('#tag').val() || 'Not selected');
        }

        if (action == 'cron') {
          return 'Cron: ' + ($.trim($('#customCronExpression').val() || '') || 'Not set');
        }

        return 'Schedule enabled, action not selected';
      }

      function commandSummary() {
        if ($('#winCommand').is(':checked')) {
          if ($('#executionStrategy').val() == 'command') {
            return 'Windows command';
          }

          if ($('#executionStrategy').val() == 'script') {
            return 'Windows ' + selectedText('#scriptType', 'script');
          }

          return 'Windows execution enabled';
        }

        if ($('#linuxCommand').is(':checked')) {
          if ($('#linuxExecutionStrategy').val() == 'command') {
            return 'Linux command / ' + pythonRuntimeSummary();
          }

          if ($('#linuxExecutionStrategy').val() == 'python_inline') {
            return 'Linux Inline Python Code / ' + pythonRuntimeSummary();
          }

          if ($('#linuxExecutionStrategy').val() == 'script') {
            var source = selectedText('#linuxScriptType', 'script');
            if ($('#linuxScriptType').val() == 'python') {
              source += ' / ' + selectedText('#pythonSourceMode', 'uploaded source') + ' / ' + pythonRuntimeSummary();
            } else if ($('#linuxScriptType').val() != '0') {
              source += ' / ' + pythonRuntimeSummary();
            }
            return 'Linux ' + source;
          }

          return 'Linux execution enabled';
        }

        return 'No command or script selected';
      }

      function normalizeArray(value) {
        if (value == null || value === '') {
          return [];
        }

        return $.isArray(value) ? value.slice(0) : [value];
      }

      function createEmptyDraft(name) {
        return {
          job_name: name || '',
          description: '',
          checkBuild: '0',
          checkEnvironment: '1',
          abort: '0',
          timestamp: '1',
          winCommand: '0',
          linuxCommand: '0',
          runJobCheck: '0',
          emailCheck: '0',
          editableEmailCheck: '0',
          executionStrategy: '0',
          scriptType: '0',
          windowsCommandLine: '',
          linuxExecutionStrategy: '0',
          linuxScriptType: '0',
          pythonSourceMode: 'upload',
          pythonEntryPoint: '',
          pythonSourcePath: '',
          pythonRepositoryUrl: '',
          pythonRepositoryBranch: '',
          pythonInlineCode: '',
          pythonRequirementsText: '',
          pythonDockerfileText: '',
          pythonInlineFilesJson: '{"files":[],"directories":[]}',
          pythonRuntimeMode: 'local',
          pythonVersion: 'python3',
          pythonDockerImage: '',
          linuxCommandLine: '',
          action: '0',
          tag: '@hourly',
          customCronExpression: '',
          singleMinute: ['*'],
          singleHour: ['*'],
          singleDayOfMonth: ['*'],
          singleMonth: ['*'],
          singleDayOfWeek: ['*'],
          repetitiveMinute: '*',
          repetitiveHour: '*',
          repetitiveDayOfMonth: '*',
          repetitiveMonth: '*',
          repetitiveDayOfWeek: '*',
          recipients: '',
          timeoutStrategy: 'noActivity',
          timeoutSeconds: '60',
          timeoutMinutes: '1',
          jobList: [],
          upstreamJobList: [],
          optionsRadios: '1',
          onSuccess: '0',
          attSuccess: 'true',
          onFailure: '0',
          attFailure: 'true',
          onAbort: '0',
          attAbort: 'true',
          environment: isConfiguredGlobalEnvironment(currentGlobalEnvironmentValue()) ? normalizeGlobalEnvironment(currentGlobalEnvironmentValue()) : '0'
        };
      }

      function draftFromForm() {
        syncPythonInlineFilesInput();
        syncEnvironmentFromGlobal(false);
        var draft = createEmptyDraft($.trim($('#job_name').val()));

        $.each(draftCheckboxFields, function(index, field) {
          draft[field] = $('#' + field).is(':checked') ? '1' : '0';
        });

        $.each(draftScalarFields, function(index, field) {
          var element = $('#' + field);
          if (element.length) {
            var value = element.val();
            draft[field] = value == null ? '' : value;
          }
        });

        $.each(draftArrayFields, function(index, field) {
          draft[field] = normalizeArray($('#' + field).val());
        });

        draft.optionsRadios = $('input[name="optionsRadios"]:checked').val() || '1';

        return draft;
      }

      function draftChecked(draft, field) {
        return draft && (draft[field] === '1' || draft[field] === 1 || draft[field] === true);
      }

      function draftName(draft, index, includePlaceholder) {
        var name = $.trim(draft && draft.job_name ? draft.job_name : '');
        if (name !== '') {
          return name;
        }

        return includePlaceholder ? (index === 0 ? 'Auto-generated name' : 'Draft ' + (index + 1)) : '';
      }

      function draftListValue(draft, field, fallback) {
        var value = normalizeArray(draft ? draft[field] : []);
        return value.length ? value.join(',') : fallback;
      }

      function draftSelectedValue(draft, field, fallback) {
        var value = draft && draft[field] != null && draft[field] !== '' ? draft[field] : fallback;
        return value == null || value === '' || value === '0' ? fallback : value;
      }

      function draftScheduleSummary(draft) {
        if (! draftChecked(draft, 'checkBuild')) {
          return 'Manual only';
        }

        if (draft.action === 'single') {
          return 'Cron: ' + draftListValue(draft, 'singleMinute', '*') + ' ' + draftListValue(draft, 'singleHour', '*') + ' ' + draftListValue(draft, 'singleDayOfMonth', '*') + ' ' + draftListValue(draft, 'singleMonth', '*') + ' ' + draftListValue(draft, 'singleDayOfWeek', '*');
        }

        if (draft.action === 'repetitive') {
          return 'Every ' + (draft.repetitiveMinute || '*') + ' minute(s), hour ' + (draft.repetitiveHour || '*') + ', day ' + (draft.repetitiveDayOfMonth || '*') + ', month ' + (draft.repetitiveMonth || '*') + ', weekday ' + (draft.repetitiveDayOfWeek || '*');
        }

        if (draft.action === 'tags') {
          return 'Tag: ' + (draft.tag || 'Not selected');
        }

        if (draft.action === 'cron') {
          return 'Cron: ' + (draft.customCronExpression || 'Not set');
        }

        return 'Schedule enabled';
      }

      function draftPythonRuntimeSummary(draft) {
        if (draft && draft.pythonRuntimeMode === 'docker') {
          return 'Docker ' + (draft.pythonDockerImage || draftDockerImageDefault(draft)) + (draft.linuxExecutionStrategy === 'python_inline' && $.trim(draft.pythonDockerfileText || '') ? ' + custom Dockerfile' : '');
        }

        return draftUsesPythonRuntime(draft) ? 'Jenkins Agent python3' : 'Jenkins Agent shell';
      }

      function draftDockerImageDefault(draft) {
        if (draftUsesPythonRuntime(draft)) {
          return dockerImageForPythonVersion(draft.pythonVersion || 'python3');
        }

        return draft && draft.linuxExecutionStrategy === 'script' && draft.linuxScriptType === 'talend' ? 'eclipse-temurin:17-jre-alpine' : 'alpine:3.20';
      }

      function draftUsesPythonRuntime(draft) {
        return draft && (draft.linuxExecutionStrategy === 'python_inline' || (draft.linuxExecutionStrategy === 'script' && draft.linuxScriptType === 'python'));
      }

      function draftCommandSummary(draft) {
        if (draftChecked(draft, 'winCommand')) {
          if (draft.executionStrategy === 'command') {
            return 'Windows command';
          }

          if (draft.executionStrategy === 'script') {
            return 'Windows ' + draftSelectedValue(draft, 'scriptType', 'script');
          }

          return 'Windows execution';
        }

        if (draftChecked(draft, 'linuxCommand')) {
          if (draft.linuxExecutionStrategy === 'command') {
            return 'Linux command / ' + draftPythonRuntimeSummary(draft);
          }

          if (draft.linuxExecutionStrategy === 'python_inline') {
            return 'Linux Inline Python Code / ' + draftPythonRuntimeSummary(draft);
          }

          if (draft.linuxExecutionStrategy === 'script') {
            var source = draftSelectedValue(draft, 'linuxScriptType', 'script');
            if (draft.linuxScriptType === 'python') {
              source += ' / ' + draftSelectedValue(draft, 'pythonSourceMode', 'upload') + ' / ' + draftPythonRuntimeSummary(draft);
            } else if (draft.linuxScriptType !== '0') {
              source += ' / ' + draftPythonRuntimeSummary(draft);
            }
            return 'Linux ' + source;
          }

          return 'Linux execution';
        }

        return 'No command or script selected';
      }

      function draftUsesUploadedSource(draft) {
        return (draftChecked(draft, 'winCommand') && draft.executionStrategy === 'script' && draft.scriptType !== '0') ||
          (draftChecked(draft, 'linuxCommand') && draft.linuxExecutionStrategy === 'script' && draft.linuxScriptType !== 'python' && draft.linuxScriptType !== '0') ||
          (draftChecked(draft, 'linuxCommand') && draft.linuxExecutionStrategy === 'script' && draft.linuxScriptType === 'python' && draft.pythonSourceMode === 'upload');
      }

      function draftControlSummary(draft) {
        var labels = ['timestamps'];
        if (draftChecked(draft, 'abort')) {
          labels.push('timeout');
        }
        labels.push($('#trigger_after_save').val() == '1' ? 'trigger after save' : 'save only');
        return labels.join(', ');
      }

      function draftNotificationSummary(draft) {
        var labels = [];
        if (draftChecked(draft, 'emailCheck')) {
          labels.push('failure email');
        }
        if (draftChecked(draft, 'editableEmailCheck')) {
          labels.push('editable email');
        }
        return labels.length ? labels.join(', ') : 'None';
      }

      function draftEnvironmentSummary(draft) {
        return draftSelectedValue(draft, 'environment', 'Not selected');
      }

      function draftDownstreamSummary(draft) {
        return draftChecked(draft, 'runJobCheck') ? draftListValue(draft, 'jobList', 'None') : 'None';
      }

      function draftUpstreamSummary(draft) {
        var upstreamNames = normalizeArray(draft ? draft.upstreamJobList : []);
        return upstreamNames.length ? upstreamNames.join(',') : 'None';
      }

      function saveActiveJobDraft(renderAfterSave) {
        if (applyingJobDraft || ! jobDrafts.length) {
          return;
        }

        jobDrafts[activeDraftIndex] = draftFromForm();

        if (renderAfterSave !== false) {
          renderJobDraftTabs();
          renderJobDraftComparison();
        }
      }

      function renderJobDraftTabs() {
        var tabs = [];

        $.each(jobDrafts, function(index, draft) {
          tabs.push('<li' + (index === activeDraftIndex ? ' class="active"' : '') + '><a href="#" data-draft-index="' + index + '"><i class="fa fa-file-code-o"></i> ' + escapeHtml(draftName(draft, index, true)) + '</a></li>');
        });

        $('#jobDraftTabs').html(tabs.join(''));
      }

      function renderJobDraftComparison() {
        var rows = ['<thead><tr><th>Job</th><th>Execution</th><th>Schedule</th><th>Environment</th><th>Pipeline</th><th>Controls</th></tr></thead><tbody>'];

        $.each(jobDrafts, function(index, draft) {
          rows.push('<tr' + (index === activeDraftIndex ? ' class="active-draft-row"' : '') + '><td><b>' + escapeHtml(draftName(draft, index, true)) + '</b></td><td>' + escapeHtml(draftCommandSummary(draft)) + '</td><td>' + escapeHtml(draftScheduleSummary(draft)) + '</td><td>' + escapeHtml(draftEnvironmentSummary(draft)) + '</td><td><b>Up:</b> ' + escapeHtml(draftUpstreamSummary(draft)) + '<br><b>Down:</b> ' + escapeHtml(draftDownstreamSummary(draft)) + '</td><td>' + escapeHtml(draftControlSummary(draft)) + '</td></tr>');
        });

        rows.push('</tbody>');
        $('#jobDraftComparison').html(rows.join(''));
      }

      function updateDraftNamesTextarea() {
        $('#job_names').val($.map(jobDrafts, function(draft, index) {
          return index === activeDraftIndex ? null : draft.job_name;
        }).join('\n'));
      }

      function syncOptionCards() {
        var enabledCount = 0;
        var enabledPanelCount = 0;
        var enabledConfigs = [];

        $('.job-option-card').each(function() {
          var card = $(this);
          var checkbox = card.find('input[type="checkbox"]');
          var isEnabled = checkbox.is(':checked');
          var panelSelector = card.data('option-panel');

          card.toggleClass('active', isEnabled);

          if (isEnabled) {
            enabledCount++;
            if (panelSelector) {
              enabledPanelCount++;
              var iconClass = (card.find('.job-option-icon').attr('class') || 'fa fa-sliders').replace(/\s*job-option-icon\b/g, '');
              var optionTitle = $.trim(card.find('.job-option-title').text());
              enabledConfigs.push({
                panelSelector: panelSelector,
                iconClass: iconClass,
                optionTitle: optionTitle
              });
            }
          }
        });

        if (! enabledConfigs.length) {
          activeConfigPanel = '';
        } else if (! activeConfigPanel || ! $.grep(enabledConfigs, function(config) { return config.panelSelector === activeConfigPanel; }).length) {
          activeConfigPanel = enabledConfigs[0].panelSelector;
        }

        var configChips = $.map(enabledConfigs, function(config) {
          var isActive = config.panelSelector === activeConfigPanel;
          return '<button type="button" class="btn btn-default btn-xs job-config-chip' + (isActive ? ' active' : '') + '" data-option-panel="' + escapeHtml(config.panelSelector) + '"><i class="' + escapeHtml(config.iconClass) + '"></i>' + escapeHtml(config.optionTitle) + '</button>';
        });

        $('#jobOptionEnabledCount').text(enabledCount + (enabledCount == 1 ? ' option enabled' : ' options enabled'));
        $('#jobOptionEmptyState').toggle(enabledPanelCount === 0);
        $('#jobConfigWorkbench').toggleClass('is-empty', enabledPanelCount === 0);
        $('#jobConfigSideNav').html(configChips.length ? configChips.join('') : '<span class="job-config-menu-empty">No configurable options enabled.</span>');
        applyActiveConfigPanel(enabledConfigs);
      }

      function applyActiveConfigPanel(enabledConfigs) {
        var enabledPanelMap = {};

        $.each(enabledConfigs, function(index, config) {
          enabledPanelMap[config.panelSelector] = true;
        });

        $('.job-option-card').each(function() {
          var panelSelector = $(this).data('option-panel');

          if (! panelSelector) {
            return;
          }

          $(panelSelector)
            .toggleClass('job-config-panel-active', !!enabledPanelMap[panelSelector] && panelSelector === activeConfigPanel)
            .toggleClass('job-config-panel-inactive', !!enabledPanelMap[panelSelector] && panelSelector !== activeConfigPanel);
        });

        $('#jobConfigPanelEmptyNote').toggle(enabledConfigs.length > 0 && ! activeConfigPanel);
      }

      function focusOptionPanel(panelSelector) {
        if (! panelSelector || applyingJobDraft) {
          return;
        }

        activeConfigPanel = panelSelector;
        refreshJobOptionPanels();

        window.setTimeout(function() {
          var panel = $(panelSelector);
          var box = panel.find('.box').first();

          if (! panel.length || ! panel.is(':visible') || ! box.length) {
            return;
          }

          box.addClass('job-config-highlight');

          window.setTimeout(function() {
            box.removeClass('job-config-highlight');
          }, 900);
        }, 180);
      }

      function refreshJobOptionPanels() {
        updateSchedulePanel();
        $('#environmentBox').hide();
        $('#checkEnvironment').prop('checked', true);
        $('#abortIfStuck').toggle($('#abort').is(':checked'));
        $('#enableEmail').toggle($('#emailCheck').is(':checked'));
        $('#editableEmail').toggle($('#editableEmailCheck').is(':checked'));
        $('#runJob').toggle($('#runJobCheck').is(':checked'));
        $('#runWinCommand').toggle($('#winCommand').is(':checked'));
        $('#runlinuxCommand').toggle($('#linuxCommand').is(':checked'));

        $('.scriptTypeForm').toggle($('#winCommand').is(':checked') && $('#executionStrategy').val() == 'script');
        $('.windowsCommandForm').toggle($('#winCommand').is(':checked') && $('#executionStrategy').val() == 'command');
        $('.uploadScript').toggle($('#winCommand').is(':checked') && $('#executionStrategy').val() == 'script' && $('#scriptType').val() != '0');

        $('.linuxScriptTypeForm').toggle($('#linuxCommand').is(':checked') && $('#linuxExecutionStrategy').val() == 'script');
        $('.linuxCommandForm').toggle($('#linuxCommand').is(':checked') && $('#linuxExecutionStrategy').val() == 'command');
        updatePythonSourceControls();
        if ($('#linuxCommand').is(':checked') && $('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() != '0') {
          syncLinuxScriptUpload();
        }
        syncLinuxExecutionChoiceControls();
        syncOptionCards();
      }

      function loadJobDraft(draft) {
        var normalizedDraft = $.extend(true, createEmptyDraft(''), draft || {});
        normalizedDraft.checkEnvironment = '1';
        normalizedDraft.environment = isConfiguredGlobalEnvironment(currentGlobalEnvironmentValue()) ? normalizeGlobalEnvironment(currentGlobalEnvironmentValue()) : '0';

        applyingJobDraft = true;

        $.each(draftCheckboxFields, function(index, field) {
          $('#' + field).prop('checked', draftChecked(normalizedDraft, field));
        });

        $.each(draftScalarFields, function(index, field) {
          var element = $('#' + field);
          if (element.length) {
            element.val(normalizedDraft[field]);
          }
        });

        $.each(draftArrayFields, function(index, field) {
          $('#' + field).val(normalizeArray(normalizedDraft[field])).trigger('change.select2');
        });

          updateLinuxCommandEditor();
          updatePythonInlineEditor();
          updatePythonRequirementsEditor();
          loadPythonInlineFilesFromHidden();
          updatePythonRuntimeControls();
          renderPythonInlineWorkspace();
          if (pythonWorkspaceAllowsInlineCode()) {
            setPythonInlinePane('code');
          }
        updateDraftNamesTextarea();
        $('input[name="optionsRadios"][value="' + (normalizedDraft.optionsRadios || '1') + '"]').prop('checked', true);
        refreshJobOptionPanels();

        applyingJobDraft = false;
        updateJobCreationReview();
      }

      function switchJobDraft(index) {
        if (index < 0 || index >= jobDrafts.length || index === activeDraftIndex) {
          return;
        }

        saveActiveJobDraft(false);
        activeDraftIndex = index;
        loadJobDraft(jobDrafts[activeDraftIndex]);
      }

      function replaceDraftsWithCurrentForm() {
        jobDrafts = [draftFromForm()];
        activeDraftIndex = 0;
        renderJobDraftTabs();
        renderJobDraftComparison();
      }

      function syncDraftsFromNames() {
        saveActiveJobDraft(false);

        var names = collectJobNames(false);
        var sourceDraft = $.extend(true, createEmptyDraft(''), jobDrafts[activeDraftIndex] || draftFromForm());
        var existingByName = {};
        var nextDrafts = [];

        $.each(jobDrafts, function(index, draft) {
          var name = $.trim(draft.job_name || '');
          if (name !== '') {
            existingByName[name] = draft;
          }
        });

        if (! names.length) {
          names.push(sourceDraft.job_name || generateJobName());
        }

        $.each(names, function(index, name) {
          var draft = existingByName[name] ? $.extend(true, {}, existingByName[name]) : $.extend(true, {}, sourceDraft);
          draft.job_name = name;
          nextDrafts.push(draft);
        });

        jobDrafts = nextDrafts;
        activeDraftIndex = 0;
        $('#job_name').val(jobDrafts[0].job_name);
        updateDraftNamesTextarea();
        loadJobDraft(jobDrafts[activeDraftIndex]);
        setBulkDraftsVisible(jobDrafts.length > 1 || $.trim($('#job_names').val()) !== '');
      }

      function addJobDraft() {
        saveActiveJobDraft(false);
        jobDrafts.push(createEmptyDraft(generateJobName()));
        activeDraftIndex = jobDrafts.length - 1;
        updateDraftNamesTextarea();
        loadJobDraft(jobDrafts[activeDraftIndex]);
        setBulkDraftsVisible(true);
      }

      function duplicateJobDraft() {
        saveActiveJobDraft(false);
        var draft = $.extend(true, {}, jobDrafts[activeDraftIndex] || draftFromForm());
        draft.job_name = generateJobName();
        jobDrafts.push(draft);
        activeDraftIndex = jobDrafts.length - 1;
        updateDraftNamesTextarea();
        loadJobDraft(jobDrafts[activeDraftIndex]);
        setBulkDraftsVisible(true);
      }

      function removeJobDraft() {
        if (jobDrafts.length <= 1) {
          jobDrafts = [createEmptyDraft('')];
          activeDraftIndex = 0;
          $('#job_names').val('');
          loadJobDraft(jobDrafts[0]);
          return;
        }

        jobDrafts.splice(activeDraftIndex, 1);
        activeDraftIndex = Math.max(0, activeDraftIndex - 1);
        updateDraftNamesTextarea();
        loadJobDraft(jobDrafts[activeDraftIndex]);
      }

      function ensureJobDraftsInitialized() {
        if (! jobDrafts.length) {
          jobDrafts = [draftFromForm()];
          activeDraftIndex = 0;
        }
      }

      function validateDraftNames(drafts) {
        var seen = {};

        for (var index = 0; index < drafts.length; index++) {
          var name = $.trim(drafts[index].job_name || '');
          if (name === '') {
            name = generateJobName();
            drafts[index].job_name = name;
          }

          if (name.length > 50 || /\s/.test(name) || /[^A-Za-z0-9._\/\-]/.test(name) || name.indexOf('..') !== -1 || name.charAt(0) === '/' || name.charAt(name.length - 1) === '/' || name.indexOf('//') !== -1) {
            return 'Job name "' + name + '" is invalid.';
          }

          if (seen[name]) {
            return 'Job name "' + name + '" is duplicated.';
          }

          seen[name] = true;
        }

        return '';
      }

      function draftFormData(draft, triggerAfterSave) {
        var formData = new FormData();

        formData.append('job_name', draft.job_name || '');
        formData.append('job_names', '');
        formData.append('description', draft.description || '');
        formData.append('trigger_after_save', triggerAfterSave || '0');
        formData.append('timestamp', '1');

        $.each(draftCheckboxFields, function(index, field) {
          if (draftChecked(draft, field)) {
            formData.append(field, '1');
          }
        });

        $.each(draftScalarFields, function(index, field) {
          if (field !== 'job_name' && field !== 'description') {
            formData.append(field, draft[field] == null ? '' : draft[field]);
          }
        });

        $.each(draftArrayFields, function(index, field) {
          $.each(normalizeArray(draft[field]), function(valueIndex, value) {
            formData.append(field + '[]', value);
          });
        });

        formData.append('optionsRadios', draft.optionsRadios || '1');

        return formData;
      }

      function responseFlashText(responseText, selector) {
        var response = $('<div>').append($.parseHTML(responseText || '', document, false));
        return $.trim(response.find(selector).first().text().replace(/×/g, ''));
      }

      function postJobDraft(draft, triggerAfterSave) {
        return $.ajax({
          url: $('#InsertDbSettings').attr('action'),
          method: 'POST',
          data: draftFormData(draft, triggerAfterSave),
          processData: false,
          contentType: false
        }).then(function(responseText) {
          var errorText = responseFlashText(responseText, '.alert-danger, .alert-error');
          if (errorText !== '') {
            return $.Deferred().reject(errorText).promise();
          }

          return responseFlashText(responseText, '.alert-success') || 'Saved';
        });
      }

      function submitJobDraftsIfNeeded() {
        ensureJobDraftsInitialized();
        saveActiveJobDraft(false);

        if (jobDrafts.length <= 1 && $.trim($('#job_names').val()) !== '') {
          syncDraftsFromNames();
          saveActiveJobDraft(false);
        }

        if (jobDrafts.length <= 1) {
          return false;
        }

        var drafts = $.map(jobDrafts, function(draft) { return $.extend(true, {}, draft); });
        var validationError = validateDraftNames(drafts);
        var triggerAfterSave = $('#trigger_after_save').val() == '1' ? '1' : '0';
        var index = 0;
        var savedNames = [];

        if (validationError !== '') {
          toastr.error(validationError, 'Job Drafts');
          return true;
        }

        jobDrafts = $.map(drafts, function(draft) { return $.extend(true, {}, draft); });
        activeDraftIndex = Math.min(activeDraftIndex, jobDrafts.length - 1);
        loadJobDraft(jobDrafts[activeDraftIndex]);

        setSaveJobState(true, 'Saving 0 of ' + drafts.length + ' job drafts...');

        function submitNext() {
          if (index >= drafts.length) {
            savedJobNames = savedNames.slice(0);
            savedJobName = savedJobNames[0] || '';
            $.each(savedJobNames, function(savedIndex, jobName) {
              jobCreationDates[jobName] = new Date().toISOString();
            });
            setSaveJobState(false, '');
            toastr.success(savedNames.length + ' job draft(s) saved.', 'Job Creation');
            expandAvailableJobsBox();
            refreshAvailableJobsTable(false);
            updateJobCreationReview();
            return;
          }

          setSaveJobState(true, 'Saving ' + (index + 1) + ' of ' + drafts.length + ': ' + drafts[index].job_name + '...');
          postJobDraft(drafts[index], triggerAfterSave).done(function() {
            savedNames.push(drafts[index].job_name);
            index += 1;
            submitNext();
          }).fail(function(errorText) {
            setSaveJobState(false, '');
            toastr.error(errorText || ('Unable to save ' + drafts[index].job_name + '.'), 'Job Creation');
          });
        }

        submitNext();

        return true;
      }

      function updateJobCreationReview() {
        ensureJobDraftsInitialized();

        if (! applyingJobDraft) {
          saveActiveJobDraft(false);
        }

        var drafts = jobDrafts.length ? jobDrafts : [draftFromForm()];
        var activeDraft = drafts[activeDraftIndex] || drafts[0] || createEmptyDraft('');
        var labels = [];
        var uploadSourceWarning = '';

        $.each(drafts, function(index, draft) {
          labels.push('<span class="label label-primary">' + escapeHtml(draftName(draft, index, true)) + '</span>');
        });

        if (drafts.length > 1) {
          $.each(drafts, function(index, draft) {
            if (draftUsesUploadedSource(draft)) {
              uploadSourceWarning = '<br><span class="text-warning"><i class="fa fa-warning"></i> Uploaded sources must be prepared while each draft tab is active.</span>';
              return false;
            }
          });
        }

        $('#jobBatchPreview').text(drafts.length + ' job draft(s) will be saved.');
        $('#send').html('<i class="fa fa-save"></i> ' + (drafts.length > 1 ? 'Save Job Drafts' : 'Save Job'));
        $('#saveAndTrigger').html('<i class="fa fa-play"></i> ' + (drafts.length > 1 ? 'Save And Trigger Drafts' : 'Save And Trigger'));
        $('#jobCreationReview').html(
          '<dt>Jobs</dt><dd>' + labels.join(' ') + uploadSourceWarning + '</dd>' +
          '<dt>Active Draft</dt><dd>' + escapeHtml(draftName(activeDraft, activeDraftIndex, true)) + '</dd>' +
          '<dt>Description</dt><dd>' + (String(activeDraft.description || '').trim() ? escapeHtml(activeDraft.description) : '<span class="text-muted">None</span>') + '</dd>' +
          '<dt>Execution</dt><dd>' + escapeHtml(draftCommandSummary(activeDraft)) + '</dd>' +
          '<dt>Schedule</dt><dd>' + escapeHtml(draftScheduleSummary(activeDraft)) + '</dd>' +
          '<dt>Environment</dt><dd>' + escapeHtml(draftEnvironmentSummary(activeDraft)) + '</dd>' +
          '<dt>Controls</dt><dd>' + escapeHtml(draftControlSummary(activeDraft)) + '</dd>' +
          '<dt>Notifications</dt><dd>' + escapeHtml(draftNotificationSummary(activeDraft)) + '</dd>' +
          '<dt>Upstream</dt><dd>' + escapeHtml(draftUpstreamSummary(activeDraft)) + '</dd>' +
          '<dt>Downstream</dt><dd>' + escapeHtml(draftDownstreamSummary(activeDraft)) + '</dd>'
        );

        renderJobDraftTabs();
        renderJobDraftComparison();
        renderJobPipelineGraph();
      }

      function generateBatchNames(count) {
        var names = collectJobNames(false);
        for (var index = 0; index < count; index++) {
          names.push(generateJobName());
        }

        if ($.trim($('#job_name').val()) === '' && names.length > 0) {
          $('#job_name').val(names.shift());
        }

        $('#job_names').val(names.join('\n'));
        syncDraftsFromNames();
      }

      function updatePythonSourceControls() {
        var isScriptExecution = $('#linuxExecutionStrategy').val() == 'script';
        var isInlinePythonExecution = $('#linuxExecutionStrategy').val() == 'python_inline';
        var scriptType = $('#linuxScriptType').val();
        var sourceMode = $('#pythonSourceMode').val() || 'upload';

        if (isScriptExecution && scriptType == 'python' && sourceMode == 'inline') {
          setSelectValue('#linuxExecutionStrategy', 'python_inline');
          $('#linuxScriptType').val(0);
          setSelectValue('#pythonSourceMode', 'upload');
          isInlinePythonExecution = true;
          isScriptExecution = false;
          scriptType = '0';
          sourceMode = 'upload';
        }

        var isPythonScript = isScriptExecution && scriptType == 'python';

        $('.pythonSourceForm').toggle(isPythonScript || isInlinePythonExecution);
        $('.pythonSourceModeColumn').toggle(isPythonScript);
        $('.pythonEntryPointColumn')
          .toggleClass('col-md-8', isPythonScript)
          .toggleClass('col-md-12', isInlinePythonExecution);
        $('.pythonPathSourceForm').toggle(isPythonScript && sourceMode == 'path');
        $('.pythonGitSourceForm').toggle(isPythonScript && sourceMode == 'git');
        $('.pythonInlineSourceForm').toggle(isInlinePythonExecution);
        $('#runlinuxCommand').toggleClass('python-inline-expanded', $('#linuxCommand').is(':checked') && isInlinePythonExecution);
        $('#pythonWorkspaceLabel').text('Inline Python Workspace');

        if (isInlinePythonExecution && $.trim($('#pythonEntryPoint').val()) === '') {
          $('#pythonEntryPoint').val('main.py');
        }

        updatePythonInlineEditor();
        updatePythonRequirementsEditor();
        updatePythonDockerfileEditor();
        updatePythonRuntimeControls();
        renderPythonInlineWorkspace();

        if (! isScriptExecution || scriptType == '0' || scriptType == '' || (isPythonScript && sourceMode != 'upload')) {
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
        }
      }

      function syncLinuxScriptUpload() {
        var scriptType = $('#linuxScriptType').val();

        updatePythonSourceControls();

        if (! $('#linuxCommand').is(':checked') || $('#linuxExecutionStrategy').val() != 'script' || scriptType == '0' || scriptType == '') {
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
          return;
        }

        if (scriptType == 'python' && $('#pythonSourceMode').val() != 'upload') {
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
          return;
        }

        var jobName = ensureJobName();

        if (jobName == '' || jobName == null) {
          toastr.error('Please select a job name to upload the file.', 'File Upload Error');
          $('#linuxScriptType').val(0);
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
          return;
        }

        var acceptedFiles = scriptType == 'python' ? '.py,.zip' : (scriptType == 'bash' ? '.sh,.zip' : '.zip');
        var uploadMessage = scriptType == 'python' ? 'Drop Python .py or .zip files here or click to upload.' : (scriptType == 'bash' ? 'Drop Bash .sh or .zip files here or click to upload.' : 'Drop Talend zip packages here or click to upload.');
        $('.linuxUploadScript').show();
        $('.destroyDropzone').remove();
        $('#linuxColumn').append($('<DIV id="dropzone" class="destroyDropzone"><form class="dropzone needsclick" id="mydropzone" action="<?php echo base_url(); ?>upload/do_upload" enctype="multipart/form-data" method="post" style="height: 220px;"><DIV class="dz-message needsclick"><img src="<?php echo base_url(); ?>assets/images/bi.png" alt="cloud" style="height: 100px; width: 100px;"><h3><b>' + uploadMessage + '</b></h3><BR></DIV></form></DIV>'));

        $('#mydropzone').dropzone({
          maxFiles: 1,
          acceptedFiles: acceptedFiles,
          url: '<?php echo base_url(); ?>jobCreation/do_upload/' + encodeURIComponent(scriptType) + '/' + encodeURIComponent(jobName),
          maxFilesize: 100,
          sending: function() {
            toastr.info('Uploading File, please wait the file get uploaded', 'File Uploading');
            $('.buildXmlBtn').prop('disabled', true);
          },
          success: function() {
            toastr.success('Your file has been succesfully uploaded and unziped, now you are able to build the xml in order to set the job to execute your zip file content.', 'File Upload Success');
            $('.buildXmlBtn').prop('disabled', false);
          },
          error: function() {
            toastr.error('Erro during uploading file.', 'File Upload Error');
            $('.buildXmlBtn').prop('disabled', false);
          }
        });
      }

      function syncLinuxExecutionControls(resetScriptType) {
        if (! $('#linuxCommand').is(':checked')) {
          $('#runlinuxCommand').hide();
          $('.linux-shell-options, .linux-python-options, .linuxScriptTypeForm, .linuxCommandForm, .linuxUploadScript, .pythonSourceForm, .pythonRuntimeForm, .pythonPathSourceForm, .pythonGitSourceForm, .pythonInlineSourceForm').hide();
          $('.destroyDropzone').remove();
          syncLinuxExecutionChoiceControls();
          return;
        }

        $('#runlinuxCommand').show();

        if ($('#linuxExecutionStrategy').val() == 'command') {
          $('.linuxScriptTypeForm').hide();
          $('.linuxCommandForm').show();
          if (resetScriptType !== false) {
            $('#linuxScriptType').val(0);
          }
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
          updatePythonSourceControls();
        } else if ($('#linuxExecutionStrategy').val() == 'python_inline') {
          $('.linuxScriptTypeForm, .linuxCommandForm, .linuxUploadScript').hide();
          $('#linuxScriptType').val(0);
          $('.destroyDropzone').remove();
          updatePythonSourceControls();
          applyPythonInlineJobSeekerTemplate(false);
        } else if ($('#linuxExecutionStrategy').val() == 'script') {
          $('.linuxScriptTypeForm').show();
          $('.linuxCommandForm').hide();
          syncLinuxScriptUpload();
        } else {
          $('.linuxScriptTypeForm, .linuxCommandForm, .linuxUploadScript').hide();
          $('.destroyDropzone').remove();
          updatePythonSourceControls();
        }

        syncLinuxExecutionChoiceControls();
      }

      function currentLinuxExecutionMode() {
        return linuxExecutionUsesPython() ? 'python' : 'bash';
      }

      function syncLinuxExecutionChoiceControls() {
        var enabled = $('#linuxCommand').is(':checked');
        var mode = enabled ? currentLinuxExecutionMode() : '';
        var strategy = $('#linuxExecutionStrategy').val();
        var scriptType = $('#linuxScriptType').val();

        $('.linux-execution-mode, .linux-execution-choice').removeClass('active');
        $('.linux-shell-options, .linux-python-options').hide();

        if (! enabled) {
          return;
        }

        $('.linux-execution-mode[data-linux-mode="' + mode + '"]').addClass('active');

        if (mode == 'python') {
          var pythonChoice = strategy == 'python_inline' ? 'inline' : ($('#pythonSourceMode').val() || 'upload');
          $('.linux-python-options').show();
          $('.linux-execution-choice[data-linux-python-choice="' + pythonChoice + '"]').addClass('active');
        } else {
          var shellChoice = strategy == 'script' ? (scriptType == 'talend' ? 'talend' : 'bash') : 'command';
          $('.linux-shell-options').show();
          $('.linux-execution-choice[data-linux-shell-choice="' + shellChoice + '"]').addClass('active');
        }
      }

      function applyLinuxExecutionMode(mode) {
        $('#linuxCommand').prop('checked', true);
        activeConfigPanel = '#runlinuxCommand';

        if (mode == 'python' && ! linuxExecutionUsesPython()) {
          setSelectValue('#linuxExecutionStrategy', 'python_inline');
          $('#linuxScriptType').val(0);
          setSelectValue('#pythonSourceMode', 'upload');
        } else if (mode != 'python' && linuxExecutionUsesPython()) {
          setSelectValue('#linuxExecutionStrategy', 'command');
          $('#linuxScriptType').val(0);
        }

        syncLinuxExecutionControls(true);
        refreshJobOptionPanels();
        updateJobCreationReview();
      }

      function applyLinuxShellChoice(choice) {
        $('#linuxCommand').prop('checked', true);
        activeConfigPanel = '#runlinuxCommand';

        if (choice == 'bash' || choice == 'talend') {
          setSelectValue('#linuxExecutionStrategy', 'script');
          setSelectValue('#linuxScriptType', choice);
        } else {
          setSelectValue('#linuxExecutionStrategy', 'command');
          $('#linuxScriptType').val(0);
        }

        syncLinuxExecutionControls(true);
        refreshJobOptionPanels();
        updateJobCreationReview();
      }

      function applyLinuxPythonChoice(choice) {
        $('#linuxCommand').prop('checked', true);
        activeConfigPanel = '#runlinuxCommand';

        if (choice == 'inline') {
          setSelectValue('#linuxExecutionStrategy', 'python_inline');
          $('#linuxScriptType').val(0);
          setSelectValue('#pythonSourceMode', 'upload');
        } else {
          setSelectValue('#linuxExecutionStrategy', 'script');
          setSelectValue('#linuxScriptType', 'python');
          setSelectValue('#pythonSourceMode', choice || 'upload');
        }

        syncLinuxExecutionControls(true);
        refreshJobOptionPanels();
        updateJobCreationReview();
      }

      $('#pythonSourceMode').change(function() {
        updatePythonSourceControls();
        if ($('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() == 'python' && $('#pythonSourceMode').val() == 'upload') {
          syncLinuxScriptUpload();
        }
        syncLinuxExecutionChoiceControls();
      });

      $('#linuxExecutionStrategy').change(function() {
        syncLinuxExecutionControls(true);
        refreshJobOptionPanels();
        updateJobCreationReview();
      });

      $('#linuxScriptType').change(function() {
        syncLinuxScriptUpload();
        refreshJobOptionPanels();
        updateJobCreationReview();
      });

      $('#linuxCommand').change(function() {
        syncLinuxExecutionControls(false);
        refreshJobOptionPanels();
        updateJobCreationReview();
      });

      $(document).on('click', '.linux-execution-mode', function() {
        applyLinuxExecutionMode($(this).data('linux-mode'));
      });

      $(document).on('click', '.linux-execution-choice[data-linux-shell-choice]', function() {
        applyLinuxShellChoice($(this).data('linux-shell-choice'));
      });

      $(document).on('click', '.linux-execution-choice[data-linux-python-choice]', function() {
        applyLinuxPythonChoice($(this).data('linux-python-choice'));
      });

     // get Jenkins credentials
     var jenkins_url = '<?php echo $jenkins_url; ?>';
    var jenkins_username = '';
    var jenkins_token = '';
     var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';
      var availableJobsRefreshTimer = null;
      var availableJobsRefreshIntervalMs = 10000;
      var savedJobName = <?php echo json_encode($savedJobName); ?>;
      var savedJobNames = <?php echo json_encode(array_values($savedJobNames)); ?> || [];
      var savedJobCreatedAt = <?php echo json_encode($savedJobCreatedAt); ?>;
      var savedJobCreationDates = <?php echo json_encode($savedJobCreationDates); ?> || {};
      var jobCreationDates = <?php echo json_encode($jobCreationDates); ?> || {};
      var jobCreationAvailableFilterRegistered = false;

      $.each(savedJobCreationDates, function(jobName, createdAt) {
        jobCreationDates[jobName] = createdAt;
      });

      if (savedJobName && savedJobCreatedAt) {
       jobCreationDates[savedJobName] = savedJobCreatedAt;
      }

     function escapeHtml(value) {
      return $('<div>').text(value == null ? '' : value).html();
    }

    function escapeAttribute(value) {
      return escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function jobNameFromRow(row) {
      return row && (row.fullName || row.name) ? (row.fullName || row.name) : '';
    }

    function normalizeGlobalEnvironment(value) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
        return window.JobSeekerGlobalEnvironment.normalize(value);
      }

      if (environmentHelper.normalize) {
        return environmentHelper.normalize(value);
      }

      return $.trim(String(value || '')).toUpperCase();
    }

    function configuredGlobalEnvironmentNames() {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.configuredEnvironmentNames) {
        return window.JobSeekerGlobalEnvironment.configuredEnvironmentNames();
      }

      return $.map(window.jobseekerGlobalEnvironmentOptions || [], function(value) {
        return normalizeGlobalEnvironment(value);
      });
    }

    function configuredGlobalEnvironmentLabel(value) {
      var normalized = normalizeGlobalEnvironment(value);
      var labels = window.jobseekerGlobalEnvironmentOptions || [];

      for (var index = 0; index < labels.length; index++) {
        if (normalizeGlobalEnvironment(labels[index]) === normalized) {
          return labels[index];
        }
      }

      return normalized;
    }

    function isConfiguredGlobalEnvironment(value) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.isConfiguredEnvironment) {
        return window.JobSeekerGlobalEnvironment.isConfiguredEnvironment(value);
      }

      return $.inArray(normalizeGlobalEnvironment(value), configuredGlobalEnvironmentNames()) !== -1;
    }

    function currentGlobalEnvironmentValue() {
      var value = $('#globalEnvironmentSelector').val() || '';

      if (! value && window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.selected) {
        value = window.JobSeekerGlobalEnvironment.selected();
      }

      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.coerceToOption) {
        return window.JobSeekerGlobalEnvironment.coerceToOption(value);
      }

      value = normalizeGlobalEnvironment(value);
      return value || 'all';
    }

    function syncAllDraftEnvironments(environment) {
      ensureJobDraftsInitialized();
      $.each(jobDrafts, function(index, draft) {
        draft.checkEnvironment = '1';
        draft.environment = environment || '0';
      });
    }

    function syncEnvironmentFromGlobal(updateDrafts) {
      var environment = currentGlobalEnvironmentValue();
      var isValidEnvironment = isConfiguredGlobalEnvironment(environment);
      var environmentValue = isValidEnvironment ? normalizeGlobalEnvironment(environment) : '0';
      var environmentLabel = isValidEnvironment ? configuredGlobalEnvironmentLabel(environment) : 'Select an environment in the global selector';

      $('#checkEnvironment').prop('checked', true);
      $('#environment').empty().append($('<option>', {
        value: environmentValue,
        text: environmentLabel
      })).val(environmentValue).prop('required', true);

      $('#jobCreationEnvironmentHelp')
        .toggleClass('text-danger', ! isValidEnvironment)
        .toggleClass('text-muted', isValidEnvironment)
        .text(isValidEnvironment ? 'This job will be saved with ENVIRONMENT=' + environmentValue + '.' : 'Choose a configured Context environment in the top bar before saving.');

      if (updateDrafts !== false) {
        syncAllDraftEnvironments(environmentValue);
      }

      return isValidEnvironment;
    }

    function requireConcreteGlobalEnvironment() {
      if (syncEnvironmentFromGlobal(false)) {
        return true;
      }

      toastr.warning('Select a configured environment in the global selector before creating a job.', 'Environment Required');
      return false;
    }

    function availableJobEnvironmentInfo(row) {
      if (row && row.environmentInfo) {
        return row.environmentInfo;
      }

      return environmentHelper.detectFromJob(row || {});
    }

    function availableJobEnvironmentText(row) {
      return environmentHelper.text(availableJobEnvironmentInfo(row));
    }

    function availableJobMatchesGlobalEnvironment(rowOrItem) {
      var row = rowOrItem && rowOrItem.row ? rowOrItem.row : rowOrItem;
      var selectedEnvironment = currentGlobalEnvironmentValue();

      if (selectedEnvironment === 'all') {
        return true;
      }

      var environment = normalizeGlobalEnvironment(availableJobEnvironmentText(row));

      if (! isConfiguredGlobalEnvironment(environment)) {
        return false;
      }

      return environment === normalizeGlobalEnvironment(selectedEnvironment);
    }

    function availableJobsRequestData() {
      return {environment: currentGlobalEnvironmentValue()};
    }

    function hydrateAvailableJobEnvironment(item) {
      if (! item || ! item.name || item.row.environmentHydrated || availableJobEnvironmentRequests[item.name]) {
        return;
      }

      availableJobEnvironmentRequests[item.name] = $.ajax({
        url: jenkins_url + jenkinsJobPath(item.name) + '/config.xml',
        method: 'GET',
        dataType: 'text',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).done(function(xmlText) {
        item.row.environmentInfo = environmentHelper.detectFromConfig(xmlText || '', item.name);
      }).fail(function() {
        item.row.environmentInfo = environmentHelper.detectFromJob(item.row || {name: item.name, fullName: item.name});
      }).always(function() {
        item.row.environmentHydrated = true;
        delete availableJobEnvironmentRequests[item.name];
        updateJobFlowSelectOptions();
        renderJobPipelineGraph();
        if ($.fn.DataTable.isDataTable('#myTable')) {
          $('#myTable').DataTable().draw(false);
        }
      });
    }

    function hydrateAvailableJobEnvironments() {
      $.each(availableJobCache, function(index, item) {
        hydrateAvailableJobEnvironment(item);
      });
    }

    function renderJobCreationDate(jobName, type) {
      var createdAt = jobCreationDates[jobName] || '';
      var timestamp = Date.parse(createdAt);

      if (type === 'sort' || type === 'type') {
        return isNaN(timestamp) ? 0 : timestamp;
      }

      if (createdAt === '' || isNaN(timestamp)) {
        return '<span class="text-muted">Not tracked</span>';
      }

      return escapeHtml(new Date(timestamp).toLocaleString());
    }

    function isRecentlySavedJob(jobName) {
      return $.inArray(jobName, savedJobNames) !== -1 || (savedJobName && jobName === savedJobName);
    }

    function expandAvailableJobsBox() {
      if ($('#box').hasClass('collapsed-box')) {
        $('#box').boxWidget('expand');
      }
    }

    function firstXmlElement(xmlDoc, tagName, root) {
      var elements = (root || xmlDoc).getElementsByTagName(tagName);
      return elements.length ? elements[0] : null;
    }

    function firstXmlText(xmlDoc, tagName, root) {
      var element = firstXmlElement(xmlDoc, tagName, root);
      return element ? $.trim(element.textContent || '') : '';
    }

    function ensureSelectOption(selector, value) {
      if (value == null || value === '') {
        return;
      }

      var select = $(selector);
      var exists = select.find('option').filter(function() {
        return this.value == value;
      }).length > 0;

      if (!exists) {
        select.append($('<option>', { value: value, text: value }));
      }
    }

    function setSelectValue(selector, value) {
      ensureSelectOption(selector, value);
      $(selector).val(value).trigger('change');
    }

    function setSelectValues(selector, values) {
      values = $.grep(values || [], function(value) {
        return value != null && value !== '';
      });

      $.each(values, function(index, value) {
        ensureSelectOption(selector, value);
      });

      $(selector).val(values).trigger('change');
    }

    function cronValues(value) {
      return $.grep(($.trim(value || '')).split(','), function(part) {
        return part !== '';
      });
    }

    function resetScheduleControls() {
      $('#checkBuild').prop('checked', false);
      $('#action').val('0');
      $('#tag').val('@hourly');
        $('#customCronExpression').val('');
      setSelectValues('#singleMinute', ['*']);
      setSelectValues('#singleHour', ['*']);
      setSelectValues('#singleDayOfMonth', ['*']);
      setSelectValues('#singleMonth', ['*']);
      setSelectValues('#singleDayOfWeek', ['*']);
      setSelectValue('#repetitiveMinute', '*');
      setSelectValue('#repetitiveHour', '*');
      setSelectValue('#repetitiveDayOfMonth', '*');
      setSelectValue('#repetitiveMonth', '*');
      setSelectValue('#repetitiveDayOfWeek', '*');
      $('.singleForm, .repetitive, .tags, .customCronForm, #build').stop(true, true).hide();
      singleEveryMinuteAcknowledged = false;
      repetitiveEveryMinuteAcknowledged = false;
      customCronEveryMinuteAcknowledged = false;
    }

    function resetJobCreationForm() {
      var form = $('#InsertDbSettings')[0];
      if (form) {
        form.reset();
      }

      $('#job_name').prop('readonly', false).removeClass('input-loading');
      $('#job_names').val('');
      setBulkDraftsVisible(false);
      $('#trigger_after_save').val('0');
      $('#jobList, #upstreamJobList').val([]);
      jobFlowSelectedNode = null;
      $('.editJobBanner').hide();
      $('.editJobName').text('');
      $('.saveJobStatus').hide().text('');
      $('.select2').val(null).trigger('change');
      resetScheduleControls();
      $('#linuxExecutionStrategy').val('0');
      $('#linuxScriptType').val('0');
      $('#executionStrategy').val('0');
      $('#scriptType').val('0');
      $('#checkEnvironment').prop('checked', true);
      syncEnvironmentFromGlobal(true);
      $('#pythonRuntimeMode').val('local');
      $('#pythonVersion').val('python3');
      $('#pythonDockerImage').val('');
      $('#pythonDockerfileText').val('');
      $('#pythonInlineFilesJson').val('{"files":[],"directories":[]}');
      $('.singleForm, .repetitive, .tags, .customCronForm, #build, #runWinCommand, #runlinuxCommand, .scriptTypeForm, .windowsCommandForm, .uploadScript, .linuxScriptTypeForm, .linuxCommandForm, .linuxUploadScript, .pythonSourceForm, .pythonRuntimeForm, .pythonPathSourceForm, .pythonGitSourceForm, .pythonInlineSourceForm, #enableEmail, #abortIfStuck, #runJob, #editableEmail').hide();
      $('#environmentBox').hide();
      $('#timeoutMinutes, #timeoutSeconds').prop('required', false);
      $('#environment').prop('required', true);
      $('.destroyDropzone').remove();
      resetPythonInlineWorkspaceState();
      updatePythonInlineEditor();
      updatePythonRequirementsEditor();
      updatePythonDockerfileEditor();
      updatePythonRuntimeControls();
      refreshJobOptionPanels();
      updateJobCreationReview();
    }

    function showEditBanner(jobName) {
      $('.editJobName').text(jobName);
      $('.editJobBanner').show();
    }

    function hydrateSchedule(xmlDoc) {
      var spec = firstXmlText(xmlDoc, 'spec');

      if (spec === '') {
        resetScheduleControls();
        return;
      }

      $('#checkBuild').prop('checked', true);
      $('#build').show();

      if (spec.charAt(0) == '@') {
        setSelectValue('#action', 'tags');
        setSelectValue('#tag', spec);
        $('.tags').show();
        $('.singleForm, .repetitive, .customCronForm').hide();
        return;
      }

      var parts = spec.split(/\s+/);
      if (parts.length < 5) {
        return;
      }

      if (/^H\/\d+$/.test(parts[0])) {
        setSelectValue('#action', 'repetitive');
        setSelectValue('#repetitiveMinute', parts[0].replace('H/', ''));
        setSelectValue('#repetitiveHour', parts[1]);
        setSelectValue('#repetitiveDayOfMonth', parts[2]);
        setSelectValue('#repetitiveMonth', parts[3]);
        setSelectValue('#repetitiveDayOfWeek', parts[4]);
        $('.repetitive').show();
        $('.singleForm, .tags, .customCronForm').hide();
        return;
      }

      if (! cronSpecFitsSingleControls(parts)) {
        setSelectValue('#action', 'cron');
        $('#customCronExpression').val(spec);
        $('.customCronForm').show();
        $('.singleForm, .repetitive, .tags').hide();
        return;
      }

      setSelectValue('#action', 'single');
      setSelectValues('#singleMinute', cronValues(parts[0]));
      setSelectValues('#singleHour', cronValues(parts[1]));
      setSelectValues('#singleDayOfMonth', cronValues(parts[2]));
      setSelectValues('#singleMonth', cronValues(parts[3]));
      setSelectValues('#singleDayOfWeek', cronValues(parts[4]));
      $('.singleForm').show();
      $('.repetitive, .tags, .customCronForm').hide();
    }

    function cronPartFitsSingleControls(value, min, max) {
      var values = cronValues(value);

      if (! values.length) {
        return false;
      }

      for (var index = 0; index < values.length; index++) {
        if (values[index] === '*') {
          continue;
        }

        if (! /^\d+$/.test(values[index])) {
          return false;
        }

        var number = parseInt(values[index], 10);
        if (number < min || number > max) {
          return false;
        }
      }

      return true;
    }

    function cronSpecFitsSingleControls(parts) {
      return parts.length === 5 &&
        cronPartFitsSingleControls(parts[0], 0, 59) &&
        cronPartFitsSingleControls(parts[1], 0, 23) &&
        cronPartFitsSingleControls(parts[2], 1, 31) &&
        cronPartFitsSingleControls(parts[3], 1, 12) &&
        cronPartFitsSingleControls(parts[4], 1, 7);
    }

    function unquoteShellValue(value) {
      value = $.trim(value || '');
      if (value.length >= 2 && value.charAt(0) == "'" && value.charAt(value.length - 1) == "'") {
        return value.substring(1, value.length - 1).replace(/'\\''/g, "'");
      }

      if (value.length >= 2 && value.charAt(0) == '"' && value.charAt(value.length - 1) == '"') {
        return value.substring(1, value.length - 1);
      }

      return value;
    }

    function shellExportValue(command, variableName) {
      var prefix = 'export ' + variableName + '=';
      var lines = command.split(/\r?\n/);

      for (var index = 0; index < lines.length; index++) {
        if (lines[index].indexOf(prefix) === 0) {
          return unquoteShellValue(lines[index].substring(prefix.length));
        }
      }

      return '';
    }

    function shellExportBase64Value(command, variableName) {
      var encoded = shellExportValue(command, variableName);

      if (encoded === '' || ! window.atob) {
        return '';
      }

      try {
        return window.atob(encoded);
      } catch (error) {
        return '';
      }
    }

    function relativeScriptPath(sourceDirectory, scriptPath) {
      if (sourceDirectory !== '' && scriptPath.indexOf(sourceDirectory + '/') === 0) {
        return scriptPath.substring(sourceDirectory.length + 1);
      }

      return scriptPath.split('/').pop();
    }

    function loadEnvironmentOptions() {
      syncEnvironmentFromGlobal(true);
      return $.Deferred().resolve().promise();
    }

    function showEnvironmentEditor() {
      $('#checkEnvironment').prop('checked', true);
      $('#environmentBox').show();
      $('#environment').prop('required', true);
      loadEnvironmentOptions();
    }

    function hydrateEnvironmentFromPythonCommand(command) {
      var runLine = '';
      var lines = command.split(/\r?\n/);

      $.each(lines, function(index, line) {
        var trimmedLine = $.trim(line);
        if (trimmedLine.indexOf('python3 "$JOBSEEKER_SCRIPT_PATH"') === 0 || trimmedLine.indexOf('"$JOBSEEKER_PYTHON" "$JOBSEEKER_SCRIPT_PATH"') === 0) {
          runLine = trimmedLine;
        }
      });

      var match = runLine.match(/^(?:python3|"\$JOBSEEKER_PYTHON") "\$JOBSEEKER_SCRIPT_PATH"\s+(.+)$/);
      if (!match) {
        return;
      }

      var environment = unquoteShellValue(match[1]);
      if (environment !== '') {
        showEnvironmentEditor(environment);
      }
    }

    function hydratePythonRuntime(command) {
      var runtimeMode = shellExportValue(command, 'JOBSEEKER_PYTHON_RUNTIME');
      var pythonExecutable = shellExportValue(command, 'JOBSEEKER_PYTHON');
      var dockerImage = shellExportValue(command, 'JOBSEEKER_DOCKER_IMAGE');
      var requirementsText = shellExportBase64Value(command, 'JOBSEEKER_PYTHON_REQUIREMENTS_B64');
      var dockerfileText = shellExportBase64Value(command, 'JOBSEEKER_PYTHON_DOCKERFILE_B64');
      var isDockerRuntime = runtimeMode == 'docker';

      setSelectValue('#pythonRuntimeMode', isDockerRuntime ? 'docker' : 'local');
      if (isDockerRuntime && dockerImage === '' && pythonExecutable !== '' && pythonExecutable !== 'python3') {
        dockerImage = dockerImageForPythonVersion(pythonExecutable);
      }
      setSelectValue('#pythonVersion', 'python3');

      if (dockerImage !== '') {
        $('#pythonDockerImage').val(dockerImage);
      }

      if (requirementsText !== '') {
        $('#pythonRequirementsText').val(requirementsText);
        updatePythonRequirementsEditor();
      }

      if (dockerfileText !== '') {
        $('#pythonDockerfileText').val(dockerfileText);
        updatePythonDockerfileEditor();
      }

      updatePythonRuntimeControls();
    }

    function hydrateLinuxRuntime(command) {
      var runtimeMode = shellExportValue(command, 'JOBSEEKER_LINUX_RUNTIME');
      var dockerImage = shellExportValue(command, 'JOBSEEKER_DOCKER_IMAGE');

      setSelectValue('#pythonRuntimeMode', runtimeMode == 'docker' ? 'docker' : 'local');
      $('#pythonVersion').val('python3');

      if (dockerImage !== '') {
        $('#pythonDockerImage').val(dockerImage);
      }

      updatePythonRuntimeControls();
    }

    function hydrateLinuxDockerCommand(command) {
      $('#linuxCommand').prop('checked', true);
      $('#runlinuxCommand').show();
      hydrateLinuxRuntime(command);

      var rawCommand = shellExportBase64Value(command, 'JOBSEEKER_LINUX_COMMAND_B64');
      if (rawCommand !== '') {
        setSelectValue('#linuxExecutionStrategy', 'command');
        $('.linuxCommandForm').show();
        $('.linuxScriptTypeForm, .pythonSourceForm, .pythonRuntimeForm, .pythonInlineSourceForm, .linuxUploadScript').hide();
        $('#linuxCommandLine').val(rawCommand);
        updateLinuxCommandEditor();
        updatePythonRuntimeControls();
        return;
      }

      var scriptType = shellExportValue(command, 'JOBSEEKER_LINUX_SCRIPT_TYPE') || 'bash';
      setSelectValue('#linuxExecutionStrategy', 'script');
      $('.linuxScriptTypeForm').show();
      $('.linuxCommandForm').hide();
      setSelectValue('#linuxScriptType', scriptType);
      $('.linuxUploadScript').show();
      updatePythonSourceControls();
      hydrateLinuxRuntime(command);
    }

    function hydratePythonCommand(jobName, command) {
      $('#linuxCommand').prop('checked', true);
      $('#runlinuxCommand').show();
      setSelectValue('#linuxExecutionStrategy', 'script');
      $('.linuxScriptTypeForm').show();
      $('.linuxCommandForm').hide();
      setSelectValue('#linuxScriptType', 'python');
      $('.pythonSourceForm').show();
      hydratePythonRuntime(command);

      var cloneLine = '';
      $.each(command.split(/\r?\n/), function(index, line) {
        if (line.indexOf('git clone --depth 1') === 0) {
          cloneLine = line;
        }
      });

      if (cloneLine !== '') {
        var branchMatch = cloneLine.match(/--branch '([^']+)'/);
        var urlMatch = cloneLine.match(/'([^']+)' "\$WORKSPACE\/jobseeker-python-source"$/);
        setSelectValue('#linuxScriptType', 'python');
        setSelectValue('#pythonSourceMode', 'git');
        $('#pythonRepositoryUrl').val(urlMatch ? urlMatch[1] : '');
        $('#pythonRepositoryBranch').val(branchMatch ? branchMatch[1] : '');
        $('#pythonEntryPoint').val(shellExportValue(command, 'JOBSEEKER_ENTRYPOINT'));
        $('.pythonGitSourceForm').show();
        $('.pythonPathSourceForm, .pythonInlineSourceForm, .linuxUploadScript').hide();
        updatePythonSourceControls();
        hydrateEnvironmentFromPythonCommand(command);
        return;
      }

      var sourceDirectory = shellExportValue(command, 'JOBSEEKER_SOURCE_DIR');
      var scriptPath = shellExportValue(command, 'JOBSEEKER_SCRIPT_PATH');
      var entryPoint = relativeScriptPath(sourceDirectory, scriptPath);
      var uploadPath = '/python/jobs/' + jobName;

      if (sourceDirectory.indexOf(uploadPath) !== -1) {
        setSelectValue('#linuxScriptType', 'python');
        setSelectValue('#pythonSourceMode', 'upload');
        $('#pythonEntryPoint').val(entryPoint);
        $('.linuxUploadScript').show();
        $('.pythonPathSourceForm, .pythonGitSourceForm, .pythonInlineSourceForm').hide();
        updatePythonSourceControls();
      } else if (sourceDirectory.indexOf('/python/inline/') !== -1) {
        setSelectValue('#linuxExecutionStrategy', 'python_inline');
        $('#linuxScriptType').val(0);
        setSelectValue('#pythonSourceMode', 'upload');
        $('#pythonEntryPoint').val(entryPoint || 'main.py');
        updatePythonSourceControls();
        loadInlinePythonSource(jobName, entryPoint || 'main.py');
      } else {
        setSelectValue('#linuxScriptType', 'python');
        setSelectValue('#pythonSourceMode', 'path');
        $('#pythonSourcePath').val(sourceDirectory);
        $('#pythonEntryPoint').val(entryPoint);
        $('.pythonPathSourceForm').show();
        $('.pythonGitSourceForm, .pythonInlineSourceForm, .linuxUploadScript').hide();
        updatePythonSourceControls();
      }

      hydrateEnvironmentFromPythonCommand(command);
    }

    function hydrateBuilders(xmlDoc, jobName) {
      var shell = firstXmlElement(xmlDoc, 'hudson.tasks.Shell');
      var batch = firstXmlElement(xmlDoc, 'hudson.tasks.BatchFile');

      if (shell) {
        var shellCommand = firstXmlText(xmlDoc, 'command', shell);
        if (shellCommand.indexOf('JOBSEEKER_PYTHON_RUNTIME') !== -1 || shellCommand.indexOf('JOBSEEKER_PYTHON_LIB') !== -1) {
          hydratePythonCommand(jobName, shellCommand);
        } else if (shellCommand.indexOf('JOBSEEKER_LINUX_RUNTIME') !== -1) {
          hydrateLinuxDockerCommand(shellCommand);
        } else if ($.trim(shellCommand).indexOf('sh ') === 0) {
          $('#linuxCommand').prop('checked', true);
          $('#runlinuxCommand').show();
          setSelectValue('#linuxExecutionStrategy', 'script');
          $('.linuxScriptTypeForm').show();
          $('.linuxCommandForm').hide();
          setSelectValue('#linuxScriptType', 'bash');
        } else if (shellCommand !== '') {
          $('#linuxCommand').prop('checked', true);
          $('#runlinuxCommand').show();
          setSelectValue('#linuxExecutionStrategy', 'command');
          $('.linuxCommandForm').show();
          $('.linuxScriptTypeForm, .pythonSourceForm, .pythonRuntimeForm, .pythonInlineSourceForm, .linuxUploadScript').hide();
          $('#linuxCommandLine').val(shellCommand);
          updateLinuxCommandEditor();
        }
      }

      if (batch) {
        var batchCommand = firstXmlText(xmlDoc, 'command', batch);
        $('#winCommand').prop('checked', true);
        $('#runWinCommand').show();
        setSelectValue('#executionStrategy', 'command');
        $('.windowsCommandForm').show();
        $('.scriptTypeForm, .uploadScript').hide();
        $('#windowsCommandLine').val(batchCommand);
      }
    }

    function hydratePublishers(xmlDoc) {
      var mailer = firstXmlElement(xmlDoc, 'hudson.tasks.Mailer');
      if (mailer) {
        $('#emailCheck').prop('checked', true);
        $('#enableEmail').show();
        $('#recipients').val(firstXmlText(xmlDoc, 'recipients', mailer));
      }

      var extendedMailer = firstXmlElement(xmlDoc, 'hudson.plugins.emailext.ExtendedEmailPublisher');
      var generatedFailureEmail = false;
      if (extendedMailer) {
        var failureTrigger = firstXmlElement(xmlDoc, 'hudson.plugins.emailext.plugins.trigger.FailureTrigger', extendedMailer);
        var recipientList = firstXmlText(xmlDoc, 'recipientList', failureTrigger);
        var body = firstXmlText(xmlDoc, 'body', failureTrigger);
        if (recipientList !== '') {
          $('#emailCheck').prop('checked', true);
          $('#enableEmail').show();
          $('#recipients').val(recipientList);
          generatedFailureEmail = body.indexOf('Jenkins marked this JobSeeker build as failed') !== -1;
        }
      }

      var buildTrigger = firstXmlElement(xmlDoc, 'hudson.tasks.BuildTrigger');
      if (buildTrigger) {
        var childProjects = $.map(firstXmlText(xmlDoc, 'childProjects', buildTrigger).split(','), function(value) {
          return $.trim(value);
        });
        $('#runJobCheck').prop('checked', true);
        $('#runJob').show();
        setSelectValues('#jobList', childProjects);

        var threshold = firstXmlElement(xmlDoc, 'threshold', buildTrigger);
        var thresholdName = firstXmlText(xmlDoc, 'name', threshold);
        $('input[name="optionsRadios"][value="' + (thresholdName == 'FAILURE' ? '2' : '1') + '"]').prop('checked', true);
      }

      if (extendedMailer && !generatedFailureEmail) {
        toastr.warning('Editable email templates cannot be restored from Jenkins XML. Select templates again before saving if you want to keep editable email notifications.', 'Edit Job');
      }
    }

    function hydrateBuildWrappers(xmlDoc) {
      $('#timestamp').prop('checked', !!firstXmlElement(xmlDoc, 'hudson.plugins.timestamper.TimestamperBuildWrapper'));

      var timeoutWrapper = firstXmlElement(xmlDoc, 'hudson.plugins.build__timeout.BuildTimeoutWrapper');
      if (!timeoutWrapper) {
        return;
      }

      $('#abort').prop('checked', true);
      $('#abortIfStuck').show();
      var strategy = firstXmlElement(xmlDoc, 'strategy', timeoutWrapper);
      var strategyClass = strategy ? strategy.getAttribute('class') || '' : '';

      if (strategyClass.indexOf('AbsoluteTimeOutStrategy') !== -1) {
        setSelectValue('#timeoutStrategy', 'absolute');
        $('#timeoutMinutes').val(firstXmlText(xmlDoc, 'timeoutMinutes', timeoutWrapper)).prop('required', true);
        $('.timeoutMinutes').show();
        $('.timeoutSeconds').hide();
      } else {
        setSelectValue('#timeoutStrategy', 'noActivity');
        $('#timeoutSeconds').val(firstXmlText(xmlDoc, 'timeoutSecondsString', timeoutWrapper)).prop('required', true);
        $('.timeoutSeconds').show();
        $('.timeoutMinutes').hide();
      }
    }

    function hydrateJobFormFromXml(jobName, xmlText) {
      var xmlDoc = $.parseXML(xmlText);

      resetJobCreationForm();
      $('#job_name').val(jobName);
      $('#description').val(firstXmlText(xmlDoc, 'description'));
      hydrateSchedule(xmlDoc);
      hydrateBuilders(xmlDoc, jobName);
      hydratePublishers(xmlDoc);
      hydrateBuildWrappers(xmlDoc);
      refreshJobOptionPanels();
      showEditBanner(jobName);
      replaceDraftsWithCurrentForm();
      updateJobCreationReview();

      toastr.info('Loaded ' + jobName + ' for editing.', 'Edit Job');
      $('html, body').animate({ scrollTop: $('#InsertDbSettings').offset().top - 70 }, 300);
    }

    function loadJobForEdit(jobName) {
      setSaveJobState(true, 'Loading job...');
      $('.overlay').fadeIn();

      $.ajax({
        url: jenkins_url + jenkinsJobPath(jobName) + '/config.xml',
        method: 'GET',
        dataType: 'text',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).done(function(xmlText) {
        try {
          hydrateJobFormFromXml(jobName, xmlText);
        } catch (error) {
          console.error(error);
          toastr.error('Unable to read this job configuration.', 'Edit Job');
        }
      }).fail(function() {
        console.error(arguments);
        toastr.error('Unable to load this job from Jenkins.', 'Edit Job');
      }).always(function() {
        $('.overlay').fadeOut();
        setSaveJobState(false, '');
      });
    }

    $('#clearEditJob').click(function() {
      resetJobCreationForm();
      jobDrafts = [createEmptyDraft('')];
      activeDraftIndex = 0;
      loadJobDraft(jobDrafts[0]);
      toastr.info('Ready to create a new job.', 'New Job');
    });

    $('#myTable').on('click', '.editJob', function() {
      loadJobForEdit($(this).data('job'));
    });

    $('.job-option-card input[type="checkbox"]').on('change', function() {
      var card = $(this).closest('.job-option-card');

      if ($(this).is(':checked') && card.data('option-panel')) {
        activeConfigPanel = card.data('option-panel');
      }

      refreshJobOptionPanels();
    });

    $(document).on('click', '.job-config-chip', function() {
      focusOptionPanel($(this).data('option-panel'));
    });

     // Logic for editable email notification

     $('#editableEmailCheck').click(function(){
      if($(this).is(":checked")){
      $('#editableEmail').fadeIn();
      $('.fetchEmail option').remove();
      $('.fetchEmail').append($('<option>', {
                value: 0,
                text: "Please, select an option"
                }))

      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>EmailSettings/fetchall/name",
        dataType: "html",
        beforeSend: function(){
          $('.overlay').fadeIn();
        },
        success: function(data){
          var json = JSON.parse(data);

           $.each(json["data"], function(i, item) {
            var newJson = (json["data"][i].name);

            $('.fetchEmail').append($('<option>', {
                value: newJson,
                text: newJson
                }))
             })
           $('.overlay').fadeOut();
        },
        error: function(arguments){
          toastr.error('Fail to fetch email template data' + arguments, 'Error to Fech Data')
          $('.overlay').fadeOut();
        }

    });

      }
        else if($(this).is(":not(:checked)")){
          $('#editableEmail').fadeOut();
        }
      });


     // Logic for run another job after this build function
     $('#runJobCheck').click(function(){
      if($(this).is(":checked")){
       ensureAvailableJobsLoaded(false);
       $('#runJob').fadeIn();
       renderJobPipelineGraph();
   }
   else if($(this).is(":not(:checked)")){
    $('#runJob').fadeOut();
    jobFlowSelectedNode = null;
  }
});


     // Logic for enable email notification
     $('#emailCheck').click(function(){
      if($(this).is(":checked")){
        $('#enableEmail').fadeIn();

      }
      else if($(this).is(":not(:checked)")){
        $('#enableEmail').fadeOut();
      }
    });



    // Logic for Execute a Windows Command or script
    $('#winCommand').click(function(){ // If checkbox is checked
      if($(this).is(":checked")){

        // Show Windows command Div
        $('#runWinCommand').fadeIn();

       //Windows Execution Strategy area script
       $('#executionStrategy').change(function(){
        var val = $('#executionStrategy').val();

        // If the option is to execute windows command line
        if(val == 'command' && val != 0){
          $('.scriptTypeForm').fadeOut();
          $('.destroyDropzone').remove();
          $("#scriptType").val(0);
          $('.windowsCommandForm').fadeIn();

        // If the option is to execute an script
      } else if(val == 'script' && val != 0) {
        $('.scriptTypeForm').fadeIn();
        $('.windowsCommandForm').fadeOut();

          // Windows Script Execution
          $('#scriptType').change(function(){
            var val = $('#scriptType').val();
            var job_name = $('#job_name').val();

            if (val != 0) {
              job_name = ensureJobName();

                if(job_name != '' && job_name != null){
                  var acceptedFiles = val == 'python' ? ".py,.zip" : ".zip";
                  var uploadMessage = val == 'python' ? "Drop Python .py or .zip files here or click to upload." : "Drop zip files here or click to upload.";
                  $('.uploadScript').show();
                  $('.destroyDropzone').remove();
                  $('#windowsColumn').append($('<DIV id="dropzone" class="destroyDropzone"><form class="dropzone needsclick" id="mydropzone" action="<?php echo base_url(); ?>upload/do_upload" enctype="multipart/form-data" method="post" style="height: 220px;"><DIV class="dz-message needsclick"><img src="<?php echo base_url(); ?>assets/images/bi.png" alt="cloud" style="height: 100px; width: 100px;"><h3><b>' + uploadMessage + '</b></h3><BR></DIV></form></DIV>'));

                  $("#mydropzone").dropzone({
                    maxFiles: 1,
                    acceptedFiles: acceptedFiles,
                    url: "<?php echo base_url(); ?>jobCreation/do_upload/" + encodeURIComponent(val) + "/" + encodeURIComponent(job_name),
                    maxFilesize: 100,
                    sending: function () {
                      toastr.info("Uploading File, please wait the file get uploaded", "File Uploading")
                      $(".buildXmlBtn").prop('disabled', true);
                    },
                    success: function(file, response) {
                      console.log(file)
                      console.log(response)
                      toastr.success("Your file has been succesfully uploaded and unziped, now you are able to build the xml in order to set the job to execute your zip file content.", "File Upload Success")
                      $(".buildXmlBtn").prop('disabled', false);
                    },
                    error: function(file, response) {
                      console.log(file)
                      console.log(response)
                      toastr.error("Erro during uploading file.", "File Upload Error")
                      $(".buildXmlBtn").prop('disabled', false);
                    }


                  });
                } else {
                  toastr.error("Please Select a job name to upload the file", "File Upload Error")
                  $("#scriptType").val(0);
                }

            } else {
              $('.uploadScript').fadeOut();
              $('.destroyDropzone').remove();
            }

          });

        } else if(val == 0){
          $('.windowsCommandForm').fadeOut();
          $('.scriptTypeForm').fadeOut();
        }

      });

     }
      else if($(this).is(":not(:checked)")){ // If checkbox is NOT checked

        // Hide Windows Command Div
        $('#runWinCommand').fadeOut();

      }
    });

    function updateScheduleActionForms() {
      var val = $('#action').val();

      if (! $('#checkBuild').is(':checked') || val == '0') {
        $('.singleForm, .repetitive, .tags, .customCronForm').stop(true, true).hide();
        return;
      }

      $('.singleForm').toggle(val == 'single');
      $('.repetitive').toggle(val == 'repetitive');
      $('.tags').toggle(val == 'tags');
      $('.customCronForm').toggle(val == 'cron');
    }

    function updateSchedulePanel() {
      if ($('#checkBuild').is(':checked')) {
        $('#build').stop(true, true).fadeIn();
        updateScheduleActionForms();
      } else {
        resetScheduleControls();
      }
    }

    function confirmEveryMinuteSchedule() {
      var val = $('#action').val();

      if (! $('#checkBuild').is(':checked') || val == 0) {
        return;
      }

      if (val == 'single') {
        var singleMinute = $('#singleMinute').val();
        var singleHour = $('#singleHour').val();
        var singleDayOfMonth = $('#singleDayOfMonth').val();
        var singleMonth = $('#singleMonth').val();
        var singleDayOfWeek = $('#singleDayOfWeek').val();

        if (! singleEveryMinuteAcknowledged && singleMinute == '*' && singleHour == '*' && singleDayOfMonth == '*' && singleMonth == '*' && singleDayOfWeek == '*') {
          alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>',
            function(){
             alertify.success('You has agreeded with your choice, be careful !');
             singleEveryMinuteAcknowledged = true;
           },
           function(){
            alertify.error('Operation Aborted');
            singleEveryMinuteAcknowledged = false;
          }
          );
        }
      } else if (val == 'repetitive') {
        var repetitiveMinute = $('#repetitiveMinute').val();
        var repetitiveHour = $('#repetitiveHour').val();
        var repetitiveDayOfMonth = $('#repetitiveDayOfMonth').val();
        var repetitiveMonth = $('#repetitiveMonth').val();
        var repetitiveDayOfWeek = $('#repetitiveDayOfWeek').val();

        if (! repetitiveEveryMinuteAcknowledged && repetitiveMinute == '*' && repetitiveHour == '*' && repetitiveDayOfMonth == '*' && repetitiveMonth == '*' && repetitiveDayOfWeek == '*') {
          alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>',
            function(){
             alertify.success('You has agreeded with your choice, be careful !');
             repetitiveEveryMinuteAcknowledged = true;
           },
           function(){
            alertify.error('Operation Aborted');
            repetitiveEveryMinuteAcknowledged = false;
          }
          );
        }
      } else if (val == 'cron') {
        var customCronExpression = $.trim($('#customCronExpression').val() || '').replace(/\s+/g, ' ');

        if (! customCronEveryMinuteAcknowledged && customCronExpression == '* * * * *') {
          alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>',
            function(){
             alertify.success('You has agreeded with your choice, be careful !');
             customCronEveryMinuteAcknowledged = true;
           },
           function(){
            alertify.error('Operation Aborted');
            customCronEveryMinuteAcknowledged = false;
          }
          );
        }
      }
    }

    $('#checkBuild').change(updateSchedulePanel);
    $('#action').change(updateScheduleActionForms);
    $('#send, #saveAndTrigger').hover(confirmEveryMinuteSchedule);

$('#abort').click(function(){
  function updateTimeoutRequiredFields() {
    var isAbsolute = $('#timeoutStrategy').val() == 'absolute';
    $('#timeoutMinutes').prop('required', isAbsolute);
    $('#timeoutSeconds').prop('required', !isAbsolute);
  }

  if($(this).is(":checked")){

    $('#abortIfStuck').fadeIn();
    updateTimeoutRequiredFields();

    $('#timeoutStrategy').change(function(){
      var val = $('#timeoutStrategy').val();
      console.log(val)
      if (val == 'absolute') {
        $('.timeoutSeconds').fadeOut();
        $('.timeoutMinutes').fadeIn();
      } else {
        $('.timeoutSeconds').fadeIn();
        $('.timeoutMinutes').fadeOut();
      }
      updateTimeoutRequiredFields();
    });

  }
  else if($(this).is(":not(:checked)")){
    $('#abortIfStuck').fadeOut();
    $('#timeoutMinutes').prop('required', false);
    $('#timeoutSeconds').prop('required', false);
  }
});

  function jenkinsJobPath(jobName) {
    return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
      return 'job/' + encodeURIComponent(segment);
    }).join('/');
  }

  function setSaveJobState(isSaving, message) {
    $('.buildXmlBtn').prop('disabled', isSaving);
    $('.saveJobStatus').text(message || '').toggle(!!message);
  }

  function refreshAvailableJobsTable(resetPaging) {
    if ($.fn.DataTable.isDataTable('#myTable')) {
      $('#myTable').DataTable().ajax.reload(null, resetPaging === true);
    }
  }

  function ensureAvailableJobFilterRegistered() {
    if (jobCreationAvailableFilterRegistered || ! $.fn.DataTable) {
      return;
    }

    $.fn.DataTable.ext.search.push(function(settings, data, dataIndex, rowData) {
      if (! settings || ! settings.nTable || settings.nTable.id !== 'myTable') {
        return true;
      }

      var row = rowData || (settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex]._aData : null);
      return availableJobMatchesGlobalEnvironment(row || {});
    });

    jobCreationAvailableFilterRegistered = true;
  }

  function triggerAvailableJobRequest(jobName) {
    return $.ajax({
      url: jenkins_url + jenkinsJobPath(jobName) + '/build?delay=0sec',
      type: 'POST',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
    }).then(null, function(xhr) {
      if (xhr && (xhr.status === 400 || xhr.status === 404)) {
        return $.ajax({
          url: jenkins_url + jenkinsJobPath(jobName) + '/buildWithParameters?delay=0sec',
          type: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
        });
      }

      return $.Deferred().rejectWith(this, arguments).promise();
    });
  }

  function startAvailableJobsAutoRefresh() {
    if (availableJobsRefreshTimer) {
      clearInterval(availableJobsRefreshTimer);
    }

    availableJobsRefreshTimer = setInterval(function() {
      refreshAvailableJobsTable(false);
    }, availableJobsRefreshIntervalMs);
  }

  function triggerAvailableJob(jobName, button) {
    var triggerButton = $(button);

    if (jobName === '' || triggerButton.prop('disabled')) {
      return;
    }

    triggerButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Triggering');

    triggerAvailableJobRequest(jobName)
      .done(function() {
        toastr.success('Execution request sent for ' + jobName + '.', 'Job Triggered');
        setTimeout(function() {
          refreshAvailableJobsTable(false);
        }, 1500);
      })
      .fail(function(xhr) {
        var message = xhr && xhr.responseText ? xhr.responseText : 'Unable to trigger job.';
        toastr.error(message, 'Trigger Failed');
      })
      .always(function() {
        triggerButton.prop('disabled', false).html('<i class="fa fa-play"></i> Trigger');
      });
  }

  function formatBuildTime(timestamp) {
    timestamp = parseInt(timestamp, 10);

    if (! timestamp) {
      return '';
    }

    if (typeof moment === 'function') {
      return moment(timestamp).format('YYYY-MM-DD HH:mm:ss');
    }

    return new Date(timestamp).toLocaleString();
  }

  function renderAvailableJobLog(jobName, buildNumber, result, date, output) {
    $('#jobCreationLogContent').html(
      '<table class="table table-bordered"><tbody>' +
        '<tr><th width="120px">Header</th><th>Task</th></tr>' +
        '<tr><td>Execution Date</td><td>' + escapeHtml(date || 'Not available') + '</td></tr>' +
        '<tr><td>Job Name</td><td>' + escapeHtml(jobName) + ' <b>[' + escapeHtml(buildNumber || 'No build') + ']</b></td></tr>' +
        '<tr><td>Status</td><td>' + escapeHtml(result || 'Not available') + '</td></tr>' +
        '<tr><td>Console Log</td><td><pre>' + escapeHtml(output) + '</pre></td></tr>' +
      '</tbody></table>'
    );
    $('#jobCreationLogModal').modal('show');
  }

  function showAvailableJobLog(jobName, buildNumber, result, date, button) {
    var logButton = $(button);

    if (jobName === '') {
      return;
    }

    if (buildNumber === '' || buildNumber == null) {
      renderAvailableJobLog(jobName, '', '', '', 'This job has not been executed yet. Trigger it first, then check the logs after Jenkins starts a build.');
      return;
    }

    logButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading');

    $.ajax({
      contentType: 'application/text',
      url: jenkins_url + jenkinsJobPath(jobName) + '/' + encodeURIComponent(buildNumber) + '/consoleText',
      method: 'GET',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
      beforeSend: function() {
        $('.overlay').show();
      },
      success: function(output) {
        renderAvailableJobLog(jobName, buildNumber, result, date, output || 'Console output is empty for this build.');
      },
      error: function(xhr) {
        var message = xhr && xhr.responseText ? xhr.responseText : 'Unable to fetch console log.';
        toastr.error(message, 'Log Query Failed');
      },
      complete: function() {
        $('.overlay').hide();
        logButton.prop('disabled', false).html('<i class="fa fa-terminal"></i> Logs');
      }
    });
  }

  Dropzone.autoDiscover = false;

function loadTable () {
     $(".overlay").show();
     if (savedJobName) {
       expandAvailableJobsBox();
     }
        ensureAvailableJobFilterRegistered();
        $("#myTable").dataTable().fnDestroy();
        $('#myTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000,2000,5000],
          "pageLength": 5,
          "order": [[ 3, "desc" ], [ 1, "asc" ]],
          "ajax": {
            "url": '<?php echo base_url(); ?>jobCreation/availableJobs',
            "type": 'GET',
            "data": function(request) {
              $.extend(request, availableJobsRequestData());
            },
            "dataSrc": function(response) {
              rememberAvailableJobs(response && response.jobs ? response.jobs : []);
              return response && response.jobs ? response.jobs : [];
            },
            "error": function(xhr, textStatus) {
              if (textStatus === 'abort' || (xhr && xhr.status === 0)) {
                return;
              }

              var message = xhr && xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Failed to refresh available jobs.';
              toastr.error(message, 'Available Jobs');
            }
          },
          "columns": [
          {"data": "color"},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return escapeHtml(jobNameFromRow(row)); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return type === 'sort' || type === 'type' ? availableJobEnvironmentText(row) : environmentHelper.label(availableJobEnvironmentInfo(row)); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderJobCreationDate(jobNameFromRow(row), type); }},
          {"data": null, "defaultContent": ""}
          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){
              if(data == 'red'){
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
              } else if (data == 'blue') {
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/good.png">';
              } else  if (data == 'notbuilt'){
                return '<b>Never Built</b>';
              } else {
                 return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/loading.gif">';
              }
            } else {return ''}
          }}, {targets:4, orderable:false, searchable:false, className:'available-job-actions-cell', width:'230px', render:function(data, type, row){
            var jobName = jobNameFromRow(row);
            var build = row && row.lastBuild ? row.lastBuild : {};
            var buildNumber = build.number || '';
            var result = build.result || '';
            var date = formatBuildTime(build.timestamp);
            var disabled = row && row.buildable === false ? ' disabled' : '';
            return '<div class="btn-group btn-group-xs available-job-actions"><button type="button" class="btn btn-info editJob" data-job="' + escapeAttribute(jobName) + '"><i class="fa fa-pencil"></i> Edit</button><button type="button" class="btn btn-default inspectJenkinsJob" data-job="' + escapeAttribute(jobName) + '"><i class="fa fa-eye"></i> Inspect</button><button type="button" class="btn btn-success triggerAvailableJob" data-job="' + escapeAttribute(jobName) + '"' + disabled + '><i class="fa fa-play"></i> Trigger</button><button type="button" class="btn btn-warning showAvailableJobLog" data-job="' + escapeAttribute(jobName) + '" data-build="' + escapeAttribute(buildNumber) + '" data-result="' + escapeAttribute(result) + '" data-time="' + escapeAttribute(date) + '"><i class="fa fa-terminal"></i> Logs</button></div>';
          }}],
          "createdRow": function(row, data) {
            if (isRecentlySavedJob(jobNameFromRow(data))) {
              $(row).addClass('jobRecentlySaved');
            }
          },
          "initComplete": function() {
            if (savedJobNames.length || savedJobName) {
              expandAvailableJobsBox();
            }
          }
       });
  $(".overlay").hide();
  startAvailableJobsAutoRefresh();
}

setTimeout(function(){ loadTable() }, 1000);
ensureJobDraftsInitialized();
syncEnvironmentFromGlobal(true);
refreshJobOptionPanels();
updateJobCreationReview();

$(document).on('jobseeker:environment-change', function() {
  syncEnvironmentFromGlobal(true);
  availableJobCache = [];
  ensureAvailableJobsLoaded(true);
  updateJobFlowSelectOptions();
  renderJobPipelineGraph();
  if ($.fn.DataTable.isDataTable('#myTable')) {
    $('#myTable').DataTable().ajax.reload(null, true);
  }
  updateJobCreationReview();
});

$(document).on('click', '.triggerAvailableJob', function() {
  triggerAvailableJob($(this).data('job') || '', this);
});

$(document).on('click', '.showAvailableJobLog', function() {
  showAvailableJobLog($(this).data('job') || '', $(this).data('build') || '', $(this).data('result') || '', $(this).data('time') || '', this);
});

$(document).on('click', '.inspectJenkinsJob', function() {
  JobSeekerJobInspect.open({
    jobName: $(this).data('job') || '',
    jenkinsUrl: jenkins_url,
    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
    button: this
  });
});


});

</script>

