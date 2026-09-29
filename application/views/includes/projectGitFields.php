<?php
/**
 * Git settings shared by the create and edit project forms. Git is optional;
 * a Git project has one repository, one build credential and a branch per
 * environment (or one branch for all of them).
 *
 * Expects: $repositoryUrl, $credentialKey, $branchDefaults (environment =>
 * ['branch' => ...]), $projectEnvironments, $gitCredentialCatalog,
 * $globalGitBranchDefaults, $personalGitAccounts, $githubOAuthEnabled.
 */
$gitCredentialCatalog = isset($gitCredentialCatalog) && is_array($gitCredentialCatalog) ? $gitCredentialCatalog : array();
$globalGitBranchDefaults = isset($globalGitBranchDefaults) && is_array($globalGitBranchDefaults) ? $globalGitBranchDefaults : array('DEV' => 'develop', 'DEFAULT' => 'main');
$branchDefaults = isset($branchDefaults) && is_array($branchDefaults) ? $branchDefaults : array();
$credentialKey = isset($credentialKey) ? (string) $credentialKey : '';
$repositoryUrl = isset($repositoryUrl) ? trim((string) $repositoryUrl) : '';
$gitEnabled = $repositoryUrl !== '';
$projectGitEnvironments = array();
foreach (isset($projectEnvironments) ? (array) $projectEnvironments : array() as $environmentRow) {
  $environmentKey = strtoupper(trim((string) $environmentRow->Environment));
  if ($environmentKey !== '' && $environmentKey !== 'DEFAULT') {
    $projectGitEnvironments[$environmentKey] = array('label' => (string) $environmentRow->Environment, 'active' => (int) $environmentRow->IsActive === 1);
  }
}
// Only a fallback row saved means "one branch everywhere".
$singleBranch = isset($branchDefaults['DEFAULT']) && count($branchDefaults) === 1;
$branchValue = function($environment) use ($branchDefaults) {
  return isset($branchDefaults[$environment]) ? $branchDefaults[$environment]['branch'] : '';
};
$authLabels = array('token' => 'token', 'api_key' => 'token', 'username_password' => 'password', 'ssh_key' => 'deploy key', 'none' => 'no auth');
?>
<section class="project-git<?php echo $gitEnabled ? ' is-enabled' : ''; ?>" id="projectGitSettings">
  <label class="project-git-switch">
    <input type="checkbox" id="projectGitEnabled" name="gitEnabled" value="1" <?php echo $gitEnabled ? 'checked' : ''; ?>>
    <span class="project-git-switch-track" aria-hidden="true"></span>
    <span class="project-git-switch-text">
      <strong><i class="fa fa-code-fork"></i> Source code in Git</strong>
      <small>Link one repository. Jobs bound to this project clone it and run the branch set for their environment, read when each build starts.</small>
    </span>
  </label>
  <?php if ($gitEnabled) { ?>
    <div class="project-git-note is-error project-git-off-warning" id="projectGitOffWarning" hidden><i class="fa fa-exclamation-triangle"></i><span>Saving with Git off removes the repository from this project. Jobs bound to it stop building until you link one again.</span></div>
  <?php } ?>

  <div class="project-git-body" id="projectGitBody"<?php echo $gitEnabled ? '' : ' hidden'; ?>>
    <div class="project-git-block">
      <h4 class="project-git-block-title"><span>1</span> Repository</h4>
      <div class="context-form-grid">
        <div class="context-field project-git-field-url">
          <label for="gitpath">Repository URL</label>
          <input id="gitpath" type="text" name="gitpath" value="<?php echo html_escape($repositoryUrl); ?>" class="form-control" placeholder="https://github.com/organization/repository.git" maxlength="1000" autocomplete="off" spellcheck="false">
          <span class="context-help">HTTPS or SSH, without credentials in the URL.</span>
        </div>
        <div class="context-field project-git-field-credential">
          <label for="gitCredentialKey">Build access</label>
          <select id="gitCredentialKey" name="gitCredentialKey" class="form-control">
            <option value="">Public repository, no credential</option>
            <?php if (! empty($gitCredentialCatalog)) { ?>
              <optgroup label="Git connectors">
                <?php foreach ($gitCredentialCatalog as $catalogKey => $catalogEntry) {
                  $hosts = array_values(array_unique(array_filter(array_map(function($scope) { return $scope['host']; }, $catalogEntry['scopes']))));
                  $auths = array_values(array_unique(array_map(function($scope) use ($authLabels) { return isset($authLabels[$scope['authType']]) ? $authLabels[$scope['authType']] : $scope['authType']; }, $catalogEntry['scopes'])));
                ?>
                  <option value="<?php echo html_escape($catalogKey); ?>" <?php echo $credentialKey === $catalogKey ? 'selected' : ''; ?>><?php echo html_escape($catalogKey.' · '.implode(', ', $hosts).' · '.implode(', ', $auths)); ?></option>
                <?php } ?>
              </optgroup>
            <?php } ?>
            <?php if ($credentialKey !== '' && ! isset($gitCredentialCatalog[$credentialKey])) { ?>
              <option value="<?php echo html_escape($credentialKey); ?>" selected><?php echo html_escape($credentialKey.' · not an active shared Git connector'); ?></option>
            <?php } ?>
            <optgroup label="Add">
              <option value="__new_token">New access token&hellip;</option>
              <option value="__new_key">New deploy key&hellip;</option>
            </optgroup>
          </select>
          <span class="context-help">How builds clone it, in every environment. People open and push with their own Git account.</span>
        </div>
      </div>

      <div class="project-git-new" id="projectGitNewToken" hidden>
        <div class="context-form-grid">
          <div class="context-field project-git-field-token">
            <label for="projectGitToken">Access token</label>
            <input id="projectGitToken" type="password" class="form-control" autocomplete="new-password" spellcheck="false">
            <span class="context-help" id="projectGitTokenHelp">A read-only token for this repository. It is encrypted and only builds receive it.</span>
          </div>
          <div class="context-field project-git-field-username">
            <label for="projectGitTokenUser">Username <small class="text-muted">if required</small></label>
            <input id="projectGitTokenUser" type="text" class="form-control" autocomplete="off" spellcheck="false">
          </div>
        </div>
        <div class="project-git-new-actions">
          <button type="button" class="btn btn-primary btn-sm" id="projectGitSaveToken"><i class="fa fa-lock"></i> Save token</button>
          <button type="button" class="btn btn-default btn-sm project-git-new-cancel">Cancel</button>
        </div>
      </div>

      <div class="project-git-new" id="projectGitNewKey" hidden>
        <p class="project-git-new-lead">JobSeeker creates a key pair and keeps the private key encrypted. You add the public key to the repository as a <strong>read-only deploy key</strong>. It works with HTTPS and SSH URLs.</p>
        <div class="project-git-hostkeys" id="projectGitHostKeys"></div>
        <div class="project-git-new-actions">
          <button type="button" class="btn btn-default btn-sm" id="projectGitFetchHostKeys"><i class="fa fa-shield"></i> Fetch host keys</button>
          <button type="button" class="btn btn-primary btn-sm" id="projectGitCreateKey" disabled><i class="fa fa-key"></i> Create deploy key</button>
          <button type="button" class="btn btn-default btn-sm project-git-new-cancel">Cancel</button>
        </div>
      </div>

      <div class="project-git-deploy-key" id="projectGitDeployKey" hidden>
        <strong><i class="fa fa-key"></i> Add this public key to the repository</strong>
        <div class="project-git-deploy-key-row">
          <textarea class="form-control" id="projectGitPublicKey" rows="2" readonly spellcheck="false"></textarea>
          <button type="button" class="btn btn-default" id="projectGitCopyKey" title="Copy the public key"><i class="fa fa-clipboard"></i> Copy</button>
        </div>
        <span class="context-help" id="projectGitDeployKeyHelp"></span>
      </div>

      <div class="project-git-access" id="projectGitAccess">
        <div class="project-git-access-status" id="projectGitAccessStatus" aria-live="polite"></div>
        <button type="button" class="btn btn-default btn-sm" id="projectGitCheck"><i class="fa fa-plug"></i> Check access</button>
      </div>
      <div class="project-git-notes" id="projectGitNotes" aria-live="polite"></div>
    </div>

    <div class="project-git-block">
      <h4 class="project-git-block-title"><span>2</span> Branches</h4>
      <div class="project-git-mode" role="radiogroup" aria-label="Branching">
        <label class="project-git-mode-option">
          <input type="radio" name="gitBranchMode" value="environment" <?php echo $singleBranch ? '' : 'checked'; ?>>
          <span><strong>A branch per environment</strong><small>For example <code>develop</code> in DEV and <code>main</code> in PROD</small></span>
        </label>
        <label class="project-git-mode-option">
          <input type="radio" name="gitBranchMode" value="single" <?php echo $singleBranch ? 'checked' : ''; ?>>
          <span><strong>One branch everywhere</strong><small>Trunk-based: every environment runs the same branch or tag</small></span>
        </label>
      </div>
      <datalist id="projectGitBranchOptions"></datalist>
      <div class="project-git-branches">
        <div class="project-git-branch-row project-git-fallback" data-environment="DEFAULT">
          <label for="projectGitBranchDEFAULT"><strong class="project-git-branch-label" data-single="Every environment" data-multi="Other environments">Other environments</strong></label>
          <input id="projectGitBranchDEFAULT" type="text" class="form-control input-sm project-git-branch" name="gitBranch[DEFAULT]" value="<?php echo html_escape($branchValue('DEFAULT')); ?>" maxlength="200" autocomplete="off" spellcheck="false" list="projectGitBranchOptions">
          <span class="project-git-branch-state"></span>
        </div>
        <?php foreach ($projectGitEnvironments as $environmentKey => $environment) { ?>
          <div class="project-git-branch-row project-git-environment-row" data-environment="<?php echo html_escape($environmentKey); ?>">
            <label for="projectGitBranch<?php echo html_escape($environmentKey); ?>"><strong><?php echo html_escape($environment['label']); ?></strong><?php if (! $environment['active']) { ?> <small class="text-muted">inactive</small><?php } ?></label>
            <input id="projectGitBranch<?php echo html_escape($environmentKey); ?>" type="text" class="form-control input-sm project-git-branch" name="gitBranch[<?php echo html_escape($environmentKey); ?>]" value="<?php echo html_escape($branchValue($environmentKey)); ?>" maxlength="200" autocomplete="off" spellcheck="false" list="projectGitBranchOptions">
            <span class="project-git-branch-state"></span>
          </div>
        <?php } ?>
      </div>
      <div class="project-git-branch-summary" id="projectGitBranchSummary" aria-live="polite" hidden></div>
      <p class="project-git-footnote"><i class="fa fa-info-circle"></i> <span id="projectGitBranchFootnote">Empty fields show what they inherit. A job can still pin its own branch or tag.</span></p>
    </div>
  </div>
</section>

<script>
window.projectGitConfig = <?php echo json_encode(array(
  'catalog' => (object) $gitCredentialCatalog,
  'globalBranches' => $globalGitBranchDefaults,
  'personalAccounts' => isset($personalGitAccounts) ? array_values((array) $personalGitAccounts) : array(),
  'githubOAuthEnabled' => ! empty($githubOAuthEnabled),
  'branchesUrl' => base_url('jobCreation/gitRemoteBranches'),
  'credentialUrl' => base_url('Context/projectGitCredential'),
  'knownHostsUrl' => base_url('gitKnownHosts'),
  'connectorsUrl' => base_url('dbSettings?create=1&type=git_repository'),
  'profileGitUrl' => base_url('profile/git'),
  'githubConnectUrl' => base_url('github/connect'),
  'environments' => array_keys($projectGitEnvironments)
), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/project-git.js?v=5"></script>
