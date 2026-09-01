<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

trait JobCreationEmailTrait
{
    private function createExtendedEmailPublisher($dom, $publishers) {
      $publisher = $dom->createElement('hudson.plugins.emailext.ExtendedEmailPublisher');
      $attrPublisher = new DOMAttr('plugin', 'email-ext@2.68');
      $publisher->setAttributeNode($attrPublisher);
      $publishers->appendChild($publisher);
      $this->appendEmailConsoleLogging($dom, $publisher);

      $configuredTriggers = $dom->createElement('configuredTriggers');
      $publisher->appendChild($configuredTriggers);

      return array('publisher' => $publisher, 'configuredTriggers' => $configuredTriggers);
    }

    private function appendEmailConsoleLogging($dom, $publisher) {
      $preSendScript = <<<'GROOVY'
def from = msg.getFrom() == null ? "Not configured" : msg.getFrom().collect { it.toString() }.join(", ")
def recipients = msg.getAllRecipients() == null ? "Not configured" : msg.getAllRecipients().collect { it.toString() }.join(", ")
logger.println("[JobSeeker Email] From: " + from)
logger.println("[JobSeeker Email] To: " + recipients)
logger.println("[JobSeeker Email] Subject: " + msg.getSubject())
GROOVY;

      $this->appendTextElement($dom, $publisher, 'presendScript', $preSendScript);
      $this->appendTextElement($dom, $publisher, 'postsendScript', 'logger.println("[JobSeeker Email] Delivery completed.")');
    }

    private function appendTextElement($dom, $parent, $name, $value) {
      $element = $dom->createElement($name);
      $element->appendChild($dom->createTextNode((string) $value));
      $parent->appendChild($element);

      return $element;
    }

    private function emailTemplateRecipientList($template) {
      $recipients = trim((string) $template->to);
      $cc = trim((string) $template->cc);

      if($cc !== '') {
        foreach(explode(',', $cc) as $ccRecipient) {
          $ccRecipient = trim($ccRecipient);
          if($ccRecipient !== '') {
            $recipients .= ($recipients === '' ? '' : ', ') . 'cc:' . $ccRecipient;
          }
        }
      }

      return $recipients;
    }

    private function appendEditableEmailTrigger($dom, $configuredTriggers, $publisher, $triggerName, $templateName, $attachBuildLog) {
      $this->load->model('emailSettings_model', 'model');
      $templates = $this->model->fetchName($templateName);

      if (empty($templates)) {
        return;
      }

      $template = $templates[0];
      $trigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.'.$triggerName);
      $configuredTriggers->appendChild($trigger);

      $email = $dom->createElement('email');
      $trigger->appendChild($email);
      $this->appendTextElement($dom, $email, 'recipientList', $this->emailTemplateRecipientList($template));
      $this->appendTextElement($dom, $email, 'subject', $template->subject);

      $body = $dom->createElement('body');
      $body->appendChild($dom->createCDATASection((string) $template->msg));
      $email->appendChild($body);

      $recipientProviders = $dom->createElement('recipientProviders');
      $email->appendChild($recipientProviders);
      $recipientProviders->appendChild($dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider'));

      $this->appendTextElement($dom, $email, 'attachmentsPattern', '');
      $this->appendTextElement($dom, $email, 'attachBuildLog', $this->normalizeAttachBuildLog($attachBuildLog));
      $this->appendTextElement($dom, $email, 'compressBuildLog', 'false');
      $this->appendTextElement($dom, $email, 'replyTo', '$PROJECT_DEFAULT_REPLYTO');
      $this->appendTextElement($dom, $email, 'contentType', 'text/html');

      if ($publisher->getElementsByTagName('from')->length === 0) {
        $this->appendTextElement($dom, $publisher, 'from', $template->from);
      }
    }

    private function normalizeAttachBuildLog($value) {
      return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    private function appendDefaultFailureEmailTrigger($dom, $configuredTriggers, $recipients, $environment = '') {
      $failureTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.FailureTrigger');
      $configuredTriggers->appendChild($failureTrigger);

      $email = $dom->createElement('email');
      $failureTrigger->appendChild($email);

      $recipientList = $dom->createElement('recipientList', $recipients);
      $email->appendChild($recipientList);

      $subject = $dom->createElement('subject', $this->failureEmailSubject('[FAILED] ${PROJECT_NAME} #${BUILD_NUMBER}', $environment));
      $email->appendChild($subject);

      $body = $dom->createElement('body');
      $body->appendChild($dom->createCDATASection($this->defaultFailureEmailBody($environment)));
      $email->appendChild($body);

      $recipientProviders = $dom->createElement('recipientProviders');
      $email->appendChild($recipientProviders);

      $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
      $recipientProviders->appendChild($recipientProvidersPlugin);

      $attachments = $dom->createElement('attachmentsPattern', '');
      $email->appendChild($attachments);

      $attachBuildLog = $dom->createElement('attachBuildLog', 'false');
      $email->appendChild($attachBuildLog);

      $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
      $email->appendChild($compressBuildLog);

      $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
      $email->appendChild($replyTo);

      $contentType = $dom->createElement('contentType', 'text/html');
      $email->appendChild($contentType);
    }

    private function environmentEmailPalette($environment) {
      switch ($this->normalizeJobSeekerEnvironment($environment)) {
        case 'DEV':
          return array('start' => '#0f4c81', 'end' => '#2563eb', 'text' => '#dbeafe');
        case 'QA':
          return array('start' => '#047857', 'end' => '#14b8a6', 'text' => '#ccfbf1');
        case 'UAT':
          return array('start' => '#7c3aed', 'end' => '#0ea5e9', 'text' => '#e0f2fe');
        case 'PREPROD':
        case 'HML':
          return array('start' => '#b45309', 'end' => '#f59e0b', 'text' => '#fff7ed');
        case 'PROD':
          return array('start' => '#7f1d1d', 'end' => '#dc2626', 'text' => '#fee2e2');
        case 'LOCAL':
          return array('start' => '#334155', 'end' => '#64748b', 'text' => '#e2e8f0');
        default:
          return array('start' => '#4A00E0', 'end' => '#8E2DE2', 'text' => '#ede9fe');
      }
    }

    private function emailEnvironmentLabel($environment) {
      $environment = $this->normalizeJobSeekerEnvironment($environment);
      return $environment === '' || $environment === '0' || $environment === 'ALL' ? 'Runtime Environment' : $environment;
    }

    private function failureEmailEnvironmentHeader($environment) {
      $palette = $this->environmentEmailPalette($environment);
      $environmentLabel = htmlspecialchars($this->emailEnvironmentLabel($environment), ENT_QUOTES, 'UTF-8');

      return '<div style="background:'.$palette['start'].'; background:linear-gradient(to right, '.$palette['start'].', '.$palette['end'].'); color:#ffffff; padding:20px 24px;">'
        .'<p style="margin:0 0 6px; font-size:12px; letter-spacing:.04em; text-transform:uppercase; color:'.$palette['text'].';">FAILED - '.$environmentLabel.'</p>'
        .'<h1 style="margin:0; font-size:23px; line-height:1.3;">'.$environmentLabel.' - ${PROJECT_NAME} #${BUILD_NUMBER} failed</h1>'
        .'<p style="margin:8px 0 0; font-size:14px; line-height:1.4; color:'.$palette['text'].';">${CAUSE}</p>'
        .'</div>';
    }

    private function failureEmailSubject($subject, $environment) {
      $environmentLabel = $this->emailEnvironmentLabel($environment);
      return '['.$environmentLabel.'] '.trim((string) $subject);
    }

    private function failureEmailBodyWithEnvironment($body, $environment) {
      $banner = $this->failureEmailEnvironmentHeader($environment);
      $body = (string) $body;

      if (preg_match('/<body\b[^>]*>/i', $body)) {
        return preg_replace_callback('/<body\b[^>]*>/i', function($matches) use ($banner) {
          return $matches[0].$banner;
        }, $body, 1);
      }

      return $banner.$body;
    }

    private function defaultFailureEmailBody($environment = '') {
      return str_replace(
        array('@@JOBSEEKER_ENVIRONMENT_EMAIL_HEADER@@', '@@JOBSEEKER_EMAIL_ENVIRONMENT@@'),
        array($this->failureEmailEnvironmentHeader($environment), htmlspecialchars($this->emailEnvironmentLabel($environment), ENT_QUOTES, 'UTF-8')),
        <<<'HTML'
<html>
  <body style="margin:0; padding:0; background:#f3f4f6; color:#17202a; font-family:Arial, Helvetica, sans-serif;">
    <div style="max-width:780px; margin:0 auto; padding:24px;">
      <div style="background:#ffffff; border:1px solid #d8dee9; border-radius:6px; overflow:hidden;">
        @@JOBSEEKER_ENVIRONMENT_EMAIL_HEADER@@
        <div style="padding:24px;">
          <p style="margin:0 0 18px; font-size:15px; line-height:1.55;">Jenkins marked this JobSeeker build as failed. Start with the highlighted error excerpt, then open the console log if the surrounding context is needed.</p>
          <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 20px;"><tr>
            <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}" style="display:block; white-space:nowrap; padding:9px 13px; background:#1f2937; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Open build</a></td>
            <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}console" style="display:block; white-space:nowrap; padding:9px 13px; background:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Console log</a></td>
            <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}consoleText" style="display:block; white-space:nowrap; padding:9px 13px; background:#475569; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Raw log</a></td>
            <td style="padding:0 0 8px;"><a href="${PROJECT_URL}" style="display:block; white-space:nowrap; padding:9px 13px; background:#e5e7eb; color:#111827; text-decoration:none; border-radius:4px; font-size:13px;">Job page</a></td>
          </tr></table>
          <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
            <tr><th align="left" style="width:150px; padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Job</th><td style="padding:8px; border:1px solid #d8dee9;">${PROJECT_NAME}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Environment</th><td style="padding:8px; border:1px solid #d8dee9; font-weight:bold;">@@JOBSEEKER_EMAIL_ENVIRONMENT@@</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build</th><td style="padding:8px; border:1px solid #d8dee9;">#${BUILD_NUMBER}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Status</th><td style="padding:8px; border:1px solid #d8dee9; color:#991b1b; font-weight:bold;">FAILED</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build ID</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="BUILD_ID"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build tag</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="BUILD_TAG"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Node</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="NODE_NAME"} / executor ${ENV,var="EXECUTOR_NUMBER"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Workspace</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;">${ENV,var="WORKSPACE"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Cause</th><td style="padding:8px; border:1px solid #d8dee9;">${CAUSE}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build URL</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;"><a href="${BUILD_URL}" style="color:#2563eb;">${BUILD_URL}</a></td></tr>
          </table>
          <h2 style="margin:20px 0 8px; font-size:16px;">Error Focus</h2>
          <pre style="white-space:pre-wrap; word-break:break-word; background:#111827; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG_REGEX, regex="(?i)(traceback|[a-z_][a-z0-9_]*(error|exception):|error|exception|fatal|command not found|no such file|permission denied|returned non-zero exit status|script returned exit code|build step .* marked build as failure)", linesBefore=5, linesAfter=0, maxTailMatches=6, maxLineLength=360, showTruncatedLines=false, escapeHtml=true, matchedLineHtmlStyle="color:#fecaca; font-weight:bold;", defaultValue="No explicit error lines were detected in the captured console output."}</pre>
          <h2 style="margin:20px 0 8px; font-size:16px;">Recent Console Output</h2>
          <pre style="white-space:pre-wrap; word-break:break-word; background:#0f172a; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG, maxLines=160, maxLineLength=500, escapeHtml=true}</pre>
        </div>
      </div>
    </div>
  </body>
</html>
HTML
  );
    }
}