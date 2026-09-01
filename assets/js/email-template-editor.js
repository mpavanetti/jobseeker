(function(window, $) {
  'use strict';

  // Jenkins Email-ext tokens such as ${BUILD_URL} or ${ENV,var="ENVIRONMENT"} must
  // survive a round-trip through the editor untouched.
  var JENKINS_TOKEN = /\$\{[^}]+\}/g;

  function looksLikeFullDocument(value) {
    return /^\s*<(?:!doctype\b|html\b)/i.test(value || '');
  }

  function initializeEmailTemplateEditor() {
    var textarea = document.getElementById('msg');
    if (!textarea || !window.CKEDITOR || textarea.getAttribute('data-email-editor') === 'ready') {
      return;
    }
    textarea.setAttribute('data-email-editor', 'ready');

    var editor = CKEDITOR.replace('msg', {
      // Preserve the authored email HTML verbatim - no tag, attribute or inline
      // style stripping by the Advanced Content Filter.
      allowedContent: true,
      // Most stored templates are complete <html> documents; keep the doctype,
      // <head> and <body> wrapper instead of silently dropping them on save.
      fullPage: looksLikeFullDocument(textarea.value),
      extraPlugins: 'colorbutton,colordialog,justify,font,find',
      removePlugins: 'scayt,wsc',
      protectedSource: [JENKINS_TOKEN],
      height: 460,
      toolbar: [
        { name: 'document', items: ['Source', '-', 'Maximize', 'Preview'] },
        { name: 'clipboard', items: ['Undo', 'Redo', '-', 'PasteText', 'PasteFromWord'] },
        { name: 'editing', items: ['Find', 'Replace', 'SelectAll', 'RemoveFormat'] },
        { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
        { name: 'colors', items: ['TextColor', 'BGColor'] },
        { name: 'styles', items: ['Font', 'FontSize', 'Format'] },
        { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
        { name: 'links', items: ['Link', 'Unlink'] },
        { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] }
      ]
    });

    // Keep the underlying textarea in sync so existing jQuery validation and the
    // normal form submit continue to work unchanged.
    editor.on('change', function() { editor.updateElement(); });
    editor.on('blur', function() { editor.updateElement(); });

    var form = textarea.form ||
      document.getElementById('InsertDbSettings') ||
      document.getElementById('UpdateDbSettings');
    if (form) {
      form.addEventListener('submit', function() { editor.updateElement(); }, true);
    }

    window.JobSeekerEmailTemplateEditor = editor;
  }

  $(initializeEmailTemplateEditor);
})(window, jQuery);
