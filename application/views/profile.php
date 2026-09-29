<?php
$userId = $userInfo->userId;
$name = $userInfo->name;
$email = $userInfo->email;
$mobile = $userInfo->mobile;
$roleId = $userInfo->roleId;
$role = $userInfo->role;
$group = $userInfo->group;
?>

<style>
.git-accounts-lead { color:#5b6b7b; margin:0 0 16px; max-width:760px; }
.git-provider-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); margin-bottom:10px; }
.git-provider-card { background:#fff; border:1px solid #dde5ee; border-radius:10px; display:flex; flex-direction:column; gap:8px; padding:14px; }
.git-provider-card small { color:#6b7a89; line-height:1.35; }
.git-provider-card .btn-link { align-self:flex-start; padding-left:0; }
.git-provider-card .btn-block { white-space:normal; }
.git-provider-card-github { background:linear-gradient(160deg,#24292f,#0d1117); border-color:#0d1117; color:#f0f3f6; grid-column:span 2; }
.git-provider-card-github small { color:#b8c2cc; }
.git-provider-card-github small a, .git-provider-card-github .btn-link { color:#9ecbff; }
.git-provider-head { align-items:center; display:flex; gap:10px; }
.git-provider-head strong { display:block; font-size:15px; }
.git-provider-head span:not(.git-provider-icon) { color:#8593a1; display:block; font-size:12px; }
.git-provider-icon { align-items:center; background:#f1f4f8; border-radius:8px; color:#24292f; display:inline-flex; flex:0 0 34px; font-size:20px; height:34px; justify-content:center; }
.git-provider-card-github .git-provider-icon { background:#fff; }
.git-github-button { background:#2da44e; border:0; color:#fff; font-weight:700; }
.git-github-button:hover, .git-github-button:focus { background:#2c974b; color:#fff; }
.git-oauth-setup { background:#f8fafc; border:1px dashed #c3cfdb; border-radius:8px; margin:4px 0 10px; padding:12px 14px; }
.git-oauth-setup ol { margin:8px 0 0; padding-left:20px; }
.git-section-title { align-items:center; display:flex; font-size:16px; gap:8px; margin:22px 0 10px; }
.git-empty { background:#f8fafc; border:1px dashed #c9d4df; border-radius:8px; color:#5f6b78; padding:16px; }
.git-account-card { border:1px solid #dde5ee; border-radius:10px; margin-bottom:10px; padding:12px 14px; transition:box-shadow .3s; }
.git-account-card.is-highlighted { border-color:#3c8dbc; box-shadow:0 0 0 3px rgba(60,141,188,.18); }
.git-account-main { align-items:center; display:flex; flex-wrap:wrap; gap:12px; }
.git-account-copy { flex:1 1 280px; min-width:0; }
.git-account-copy strong { display:block; font-size:14px; }
.git-account-meta { align-items:center; color:#51606f; display:flex; flex-wrap:wrap; font-size:12px; gap:4px 10px; margin-top:3px; }
.git-account-actions { align-items:center; display:flex; flex-wrap:wrap; gap:6px; }
.git-account-delete { display:inline; margin:0; }
.git-account-public-key, .git-account-test { border-top:1px solid #edf1f5; margin-top:12px; padding-top:10px; }
.git-account-public-key label { display:block; font-size:12px; margin-bottom:4px; }
.git-public-key { font-family:Menlo,Consolas,monospace; font-size:11px; }
.git-test-result { border-radius:6px; display:none; font-size:12px; margin-top:8px; padding:8px 10px; }
.git-test-result.is-ok { background:#edf8f0; color:#1e6b33; display:block; }
.git-test-result.is-error { background:#fdf0ef; color:#9f2f28; display:block; }
.git-test-result.is-running { background:#f3f6f9; color:#4a5a6a; display:block; }
.git-add-card { background:#fbfcfd; border:1px solid #dde5ee; border-radius:10px; margin-top:18px; padding:4px 16px 14px; }
.git-form-grid { display:grid; gap:0 14px; grid-template-columns:repeat(3, minmax(0, 1fr)); }
.git-form-wide { grid-column:1 / -1; }
.git-form-span2 { grid-column:span 2; }
.git-auth-options { display:grid; gap:8px; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); }
.git-auth-option { align-items:flex-start; background:#fff; border:1px solid #dde5ee; border-radius:8px; cursor:pointer; display:flex; font-weight:normal; gap:8px; margin:0; padding:10px; }
.git-auth-option input { margin-top:3px; }
.git-auth-option strong { display:block; font-size:13px; }
.git-auth-option small { color:#6b7a89; display:block; font-size:11px; line-height:1.3; }
.git-auth-option.is-selected { background:#f0f7fc; border-color:#3c8dbc; }
.git-token-help { background:#f3f7fb; border-radius:6px; color:#3f5163; font-size:12px; padding:9px 11px; }
.git-known-hosts-actions { align-items:center; display:flex; flex-wrap:wrap; gap:10px; margin-bottom:6px; }
.git-known-hosts-actions .text-muted { font-size:12px; }
.git-fingerprints { font-family:Menlo,Consolas,monospace; font-size:11px; margin-top:6px; white-space:pre-wrap; }
.git-form-footer { align-items:center; border-top:1px solid #e6ecf2; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; margin-top:6px; padding-top:12px; }
.git-form-footer .text-muted { font-size:12px; }
@media (max-width:991px) { .git-form-grid { grid-template-columns:1fr; } .git-form-span2 { grid-column:auto; } .git-provider-card-github { grid-column:auto; } }
</style>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-user-circle"></i> My Profile
        <small>View or modify information</small>
      </h1>
    </section>
    
    <section class="content">
    
        <div class="row">
            <!-- left column -->
            <div class="col-md-3">
              <!-- general form elements -->


                <div class="box box-warning" style="padding-bottom: 0px;">
                    <div class="box-body box-profile">
                        <img class="profile-user-img img-responsive img-circle" src="<?php echo base_url(); ?>assets/dist/img/avatar.png" alt="User profile picture">
                        <h3 class="profile-username text-center"><?= $name ?></h3>

                        <p class="text-muted text-center"><?= $role ?></p>

                        <ul class="list-group">
                            <li class="list-group-item" style="padding-bottom: 30px;">
                                <b>Email</b> <a class="pull-right"><?= $email ?></a>
                            </li>
                            <li class="list-group-item" style="margin-top: 15px;">
                                <b>Mobile</b> <a class="pull-right"><?= $mobile ?></a>
                            </li>
                            <li class="list-group-item" style="margin-top: 15px;">
                                <b>Group</b> <a class="pull-right"><?= $group ?></a>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>

            <div class="col-md-9">
                <?php
                    $this->load->helper('form');
                    $error = $this->session->flashdata('error');
                    if($error)
                    {
                ?>
                <div class="alert alert-danger alert-dismissable">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php echo html_escape($error); ?>
                </div>
                <?php } ?>
                <?php  
                    $success = $this->session->flashdata('success');
                    if($success)
                    {
                ?>
                <div class="alert alert-success alert-dismissable">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php echo html_escape($success); ?>
                </div>
                <?php } ?>

                <?php  
                    $noMatch = $this->session->flashdata('nomatch');
                    if($noMatch)
                    {
                ?>
                <div class="alert alert-warning alert-dismissable">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php echo html_escape($noMatch); ?>
                </div>
                <?php } ?>
                
                <div class="row">
                    <div class="col-md-12">
                        <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>
                    </div>
                </div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="<?= ($active == "details")? "active" : "" ?>"><a href="#details" data-toggle="tab">Details</a></li>
                        <li class="<?= ($active == "changepass")? "active" : "" ?>"><a href="#changepass" data-toggle="tab">Change Password</a></li>
                        <li class="<?= ($active == "git")? "active" : "" ?>"><a href="#git" data-toggle="tab">Git Accounts</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="<?= ($active == "details")? "active" : "" ?> tab-pane" id="details">
                            <form action="<?php echo base_url() ?>profileUpdate" method="post" id="editProfile" role="form">
                                <?php $this->load->helper('form'); ?>
                                <div class="box-body" style="padding-bottom: 35px;">
                                    <div class="row">
                                        <div class="col-md-12">                                
                                            <div class="form-group">
                                                <label for="fname">Full Name</label>
                                                <input type="text" class="form-control" id="fname" name="fname" placeholder="<?php echo html_escape($name); ?>" value="<?php echo set_value('fname', $name); ?>" maxlength="128" />
                                                <input type="hidden" value="<?php echo html_escape($userId); ?>" name="userId" id="userId" />    
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="mobile">Mobile Number</label>
                                                <input type="text" class="form-control" id="mobile" name="mobile" placeholder="<?php echo html_escape($mobile); ?>" value="<?php echo set_value('mobile', $mobile); ?>" maxlength="10">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo html_escape($email); ?>" value="<?php echo set_value('email', $email); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.box-body -->
                                <div class="box-footer">
                                    <input type="submit" class="btn btn-primary" value="Submit" />
                                    <input type="reset" class="btn btn-default" value="Reset" />
                                </div>
                            </form>
                        </div>
                        <div class="<?= ($active == "changepass")? "active" : "" ?> tab-pane" id="changepass">
                            <form role="form" action="<?php echo base_url() ?>changePassword" method="post">
                                <div class="box-body">
                                    <div class="row" style="padding-bottom: 25px;">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="inputPassword1">Old Password</label>
                                                <input type="password" class="form-control" id="inputOldPassword" placeholder="Old password" name="oldPassword" maxlength="20" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="inputPassword1">New Password</label>
                                                <input type="password" class="form-control" id="inputPassword1" placeholder="New password" name="newPassword" maxlength="20" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="inputPassword2">Confirm New Password</label>
                                                <input type="password" class="form-control" id="inputPassword2" placeholder="Confirm new password" name="cNewPassword" maxlength="20" required>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.box-body -->
            
                                <div class="box-footer">
                                    <input type="submit" class="btn btn-primary" value="Submit" />
                                    <input type="reset" class="btn btn-default" value="Reset" />
                                </div>
                            </form>
                        </div>
                        <div class="<?= ($active == "git")? "active" : "" ?> tab-pane" id="git">
                            <?php
                            $gitProviderIcons = array('github' => 'fa-github', 'gitlab' => 'fa-gitlab', 'bitbucket' => 'fa-bitbucket', 'azure_devops' => 'fa-windows', 'generic' => 'fa-git');
                            $gitProviderNames = array('github' => 'GitHub', 'gitlab' => 'GitLab', 'bitbucket' => 'Bitbucket', 'azure_devops' => 'Azure DevOps', 'generic' => 'Git');
                            $gitSshKeyPages = array('github.com' => 'https://github.com/settings/ssh/new', 'gitlab.com' => 'https://gitlab.com/-/user_settings/ssh_keys', 'bitbucket.org' => 'https://bitbucket.org/account/settings/ssh-keys/');
                            $gitHighlight = (int) $this->session->flashdata('git_account_highlight');
                            ?>
                            <div class="git-accounts">
                                <p class="git-accounts-lead">Your Git accounts let the VS Code workspaces you open from JobSeeker clone, pull and push private repositories, and let you move inline jobs into Git. Scheduled builds never use them; they use a Git connector.</p>

                                <div class="git-provider-grid">
                                    <div class="git-provider-card git-provider-card-github">
                                        <div class="git-provider-head"><span class="git-provider-icon"><i class="fa fa-github"></i></span><div><strong>GitHub</strong><span>github.com</span></div></div>
                                        <?php if (! empty($githubOAuthEnabled)) { ?>
                                            <a class="btn btn-block git-github-button" href="<?php echo base_url('github/connect'); ?>"><i class="fa fa-github"></i> Continue with GitHub</a>
                                            <small>Sign in and approve access in your browser, including private repositories.</small>
                                        <?php } else { ?>
                                            <button type="button" class="btn btn-block btn-default git-provider-start" data-provider="github" data-auth="token"><i class="fa fa-key"></i> Use an access token</button>
                                            <small>Browser sign-in is off on this server. <a href="#gitOAuthSetup" data-toggle="collapse">How to turn it on</a></small>
                                        <?php } ?>
                                        <button type="button" class="btn btn-link btn-xs git-provider-start" data-provider="github" data-auth="ssh_generate">or use an SSH key</button>
                                    </div>
                                    <?php foreach (array('gitlab' => array('GitLab', 'gitlab.com'), 'bitbucket' => array('Bitbucket', 'bitbucket.org'), 'azure_devops' => array('Azure DevOps', 'dev.azure.com'), 'generic' => array('Other', 'Enterprise or self-hosted')) as $providerKey => $providerInfo) { ?>
                                    <div class="git-provider-card">
                                        <div class="git-provider-head"><span class="git-provider-icon"><i class="fa <?php echo $gitProviderIcons[$providerKey]; ?>"></i></span><div><strong><?php echo html_escape($providerInfo[0]); ?></strong><span><?php echo html_escape($providerInfo[1]); ?></span></div></div>
                                        <button type="button" class="btn btn-block btn-default git-provider-start" data-provider="<?php echo $providerKey; ?>" data-auth="<?php echo $providerKey === 'bitbucket' ? 'username_password' : 'token'; ?>"><i class="fa fa-key"></i> <?php echo $providerKey === 'bitbucket' ? 'API token' : 'Access token'; ?></button>
                                        <button type="button" class="btn btn-link btn-xs git-provider-start" data-provider="<?php echo $providerKey; ?>" data-auth="ssh_generate">or use an SSH key</button>
                                    </div>
                                    <?php } ?>
                                </div>
                                <?php if (empty($githubOAuthEnabled)) { ?>
                                <div class="collapse git-oauth-setup" id="gitOAuthSetup">
                                    <strong><i class="fa fa-info-circle"></i> Enable "Continue with GitHub" (administrator)</strong>
                                    <ol>
                                        <li>Register an OAuth App at <a href="https://github.com/settings/applications/new" target="_blank" rel="noopener">github.com/settings/applications/new</a> (or under your organization's settings).</li>
                                        <li>Set its authorization callback URL to <code><?php echo html_escape($githubOAuthCallback); ?></code>.</li>
                                        <li>Set <code>JOBSEEKER_GITHUB_OAUTH_CLIENT_ID</code> and <code>JOBSEEKER_GITHUB_OAUTH_CLIENT_SECRET</code> for the PHP service, then restart it.</li>
                                    </ol>
                                </div>
                                <?php } ?>

                                <h4 class="git-section-title">Your accounts <span class="badge"><?php echo count((array) $gitAccounts); ?></span></h4>
                                <?php if (empty($gitAccounts)) { ?>
                                    <div class="git-empty"><i class="fa fa-plug"></i> No Git accounts yet. Connect a provider above to open private repositories in VS Code.</div>
                                <?php } ?>
                                <?php foreach ((array) $gitAccounts as $gitAccount) {
                                    $accountProvider = isset($gitProviderIcons[$gitAccount->provider]) ? $gitAccount->provider : 'generic';
                                    $accountLabel = $gitAccount->label ? $gitAccount->label : $gitProviderNames[$accountProvider].($gitAccount->username ? ' · '.$gitAccount->username : '');
                                    $accountAuth = $gitAccount->auth_type === 'ssh_key' ? 'SSH key' : ($gitAccount->auth_type === 'username_password' ? 'Username + password' : 'Access token');
                                    $accountScope = $gitAccount->host.($gitAccount->path_prefix ? '/'.$gitAccount->path_prefix : '');
                                    $accountHostOnly = preg_replace('/:\d+$/', '', $gitAccount->host);
                                ?>
                                <div class="git-account-card<?php echo $gitHighlight === (int) $gitAccount->id ? ' is-highlighted' : ''; ?>" id="git-account-<?php echo (int) $gitAccount->id; ?>">
                                    <div class="git-account-main">
                                        <span class="git-provider-icon"><i class="fa <?php echo $gitProviderIcons[$accountProvider]; ?>"></i></span>
                                        <div class="git-account-copy">
                                            <strong><?php echo html_escape($accountLabel); ?></strong>
                                            <span class="git-account-meta">
                                                <code><?php echo html_escape($accountScope); ?></code><?php echo $gitAccount->path_prefix ? '' : ' <span class="text-muted">all repositories</span>'; ?>
                                                <span class="label label-default"><?php echo html_escape($accountAuth); ?></span>
                                                <?php if ($gitAccount->auth_type !== 'ssh_key' && $gitAccount->username) { ?><span><i class="fa fa-user"></i> <?php echo html_escape($gitAccount->username); ?></span><?php } ?>
                                                <span class="text-muted">Last used <?php echo html_escape($gitAccount->last_used_at ? $gitAccount->last_used_at : 'never'); ?></span>
                                            </span>
                                        </div>
                                        <div class="git-account-actions">
                                            <button type="button" class="btn btn-default btn-sm git-account-test-toggle" data-target="#git-account-test-<?php echo (int) $gitAccount->id; ?>"><i class="fa fa-plug"></i> Test</button>
                                            <button type="button" class="btn btn-default btn-sm git-account-replace" title="Replace this account's secret" data-provider="<?php echo html_escape($accountProvider); ?>" data-host="<?php echo html_escape($gitAccount->host); ?>" data-scope="<?php echo html_escape($gitAccount->path_prefix); ?>" data-label="<?php echo html_escape($gitAccount->label); ?>" data-username="<?php echo html_escape($gitAccount->username); ?>" data-auth="<?php echo html_escape($gitAccount->auth_type); ?>"><i class="fa fa-refresh"></i> Replace</button>
                                            <form action="<?php echo base_url() ?>gitAccountDelete" method="post" class="git-account-delete">
                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) $gitAccount->id; ?>">
                                                <button type="submit" class="btn btn-link btn-sm text-danger" data-account-label="<?php echo html_escape($accountLabel); ?>"><i class="fa fa-trash"></i> Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php if ($gitAccount->auth_type === 'ssh_key' && $gitAccount->public_key) { ?>
                                    <div class="git-account-public-key">
                                        <label>Public key <small class="text-muted">add it to your <?php echo html_escape($accountHostOnly); ?> account<?php if (isset($gitSshKeyPages[$accountHostOnly])) { ?> · <a href="<?php echo $gitSshKeyPages[$accountHostOnly]; ?>" target="_blank" rel="noopener">open SSH key settings</a><?php } ?></small></label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control git-public-key" readonly value="<?php echo html_escape($gitAccount->public_key); ?>">
                                            <span class="input-group-btn"><button type="button" class="btn btn-default git-copy-public-key"><i class="fa fa-clipboard"></i> Copy</button></span>
                                        </div>
                                        <?php if ($gitAccount->fingerprint) { ?><small class="text-muted"><?php echo html_escape($gitAccount->fingerprint); ?></small><?php } ?>
                                    </div>
                                    <?php } ?>
                                    <div class="git-account-test" id="git-account-test-<?php echo (int) $gitAccount->id; ?>" style="display:none;">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control git-test-repository" maxlength="1000" spellcheck="false" placeholder="<?php echo html_escape($gitAccount->auth_type === 'ssh_key' ? 'git@'.$accountHostOnly.':owner/repository.git' : 'https://'.$gitAccount->host.'/'.($gitAccount->path_prefix ? $gitAccount->path_prefix : 'owner').'/repository.git'); ?>">
                                            <span class="input-group-btn"><button type="button" class="btn btn-info git-run-test" data-account-id="<?php echo (int) $gitAccount->id; ?>"><i class="fa fa-plug"></i> Run test</button></span>
                                        </div>
                                        <small class="text-muted">A read-only <code>git ls-remote</code>; the secret is never part of the URL, arguments or response.</small>
                                        <div class="git-test-result" role="status"></div>
                                    </div>
                                </div>
                                <?php } ?>

                                <div class="git-add-card" id="gitAccountAdd">
                                    <h4 class="git-section-title"><i class="fa fa-plus-circle"></i> <span id="gitAccountFormTitle">Add an account</span></h4>
                                    <form action="<?php echo base_url() ?>gitAccountSave" method="post" autocomplete="off" id="gitAccountForm">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                        <div class="git-form-grid">
                                            <div class="form-group">
                                                <label for="gitAccountProvider">Provider</label>
                                                <select class="form-control" id="gitAccountProvider" name="provider" required>
                                                    <option value="github">GitHub</option>
                                                    <option value="gitlab">GitLab</option>
                                                    <option value="bitbucket">Bitbucket</option>
                                                    <option value="azure_devops">Azure DevOps</option>
                                                    <option value="generic">Other Git provider</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="gitAccountHost">Host</label>
                                                <input type="text" class="form-control" id="gitAccountHost" name="host" value="github.com" maxlength="255" required spellcheck="false">
                                            </div>
                                            <div class="form-group">
                                                <label for="gitAccountLabel">Name <small class="text-muted">optional</small></label>
                                                <input type="text" class="form-control" id="gitAccountLabel" name="label" maxlength="255" placeholder="Work GitHub">
                                            </div>
                                            <div class="form-group git-form-wide">
                                                <label for="gitAccountPath">Limit to an owner or repository <small class="text-muted">optional</small></label>
                                                <input type="text" class="form-control" id="gitAccountPath" name="path_prefix" maxlength="255" spellcheck="false" placeholder="my-organization or my-organization/repository">
                                                <span class="help-block">Leave empty for every repository on the host. With several accounts on one host, the most specific match wins.</span>
                                            </div>
                                            <div class="form-group git-form-wide">
                                                <label>Authentication</label>
                                                <div class="git-auth-options" role="radiogroup">
                                                    <label class="git-auth-option"><input type="radio" name="auth_type" value="token" checked> <span><strong>Access token</strong><small>Personal, project or OAuth token over HTTPS</small></span></label>
                                                    <label class="git-auth-option"><input type="radio" name="auth_type" value="ssh_generate"> <span><strong>New SSH key</strong><small>JobSeeker creates a key; you add its public key</small></span></label>
                                                    <label class="git-auth-option"><input type="radio" name="auth_type" value="ssh_key"> <span><strong>Existing SSH key</strong><small>Paste an unencrypted private key</small></span></label>
                                                    <label class="git-auth-option"><input type="radio" name="auth_type" value="username_password"> <span><strong>Username + token</strong><small>API token or password with a username</small></span></label>
                                                </div>
                                            </div>
                                            <div class="form-group git-auth-http">
                                                <label for="gitAccountUsername">Username <small class="text-muted" id="gitAccountUsernameHint">optional</small></label>
                                                <input type="text" class="form-control" id="gitAccountUsername" name="username" maxlength="255" autocomplete="off" spellcheck="false">
                                            </div>
                                            <div class="form-group git-auth-http git-form-span2">
                                                <label for="gitAccountSecret" id="gitAccountSecretLabel">Access token</label>
                                                <input type="password" class="form-control" id="gitAccountSecret" name="secret" maxlength="50000" autocomplete="new-password">
                                            </div>
                                            <div class="form-group git-form-wide git-auth-http"><div class="git-token-help" id="gitAccountProviderHelp"></div></div>
                                            <div class="form-group git-form-wide git-auth-ssh-existing" style="display:none;">
                                                <label for="gitAccountPrivateKey">Private key</label>
                                                <textarea class="form-control" id="gitAccountPrivateKey" rows="5" maxlength="50000" autocomplete="off" spellcheck="false" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                                                <span class="help-block">Unencrypted (no passphrase). The public key and fingerprint are derived when you save.</span>
                                            </div>
                                            <div class="form-group git-form-wide git-auth-ssh" style="display:none;">
                                                <label for="gitAccountKnownHosts">Trusted host keys</label>
                                                <div class="git-known-hosts-actions"><button type="button" class="btn btn-default btn-sm" id="gitFetchKnownHosts"><i class="fa fa-download"></i> Fetch from host</button><span class="text-muted" id="gitKnownHostsHint">Compare the fingerprints with the ones your provider publishes before saving.</span></div>
                                                <textarea class="form-control" id="gitAccountKnownHosts" name="known_hosts" rows="3" maxlength="50000" spellcheck="false" placeholder="github.com ssh-ed25519 AAAA..."></textarea>
                                                <div class="git-fingerprints" id="gitKnownHostsFingerprints"></div>
                                            </div>
                                        </div>
                                        <div class="git-form-footer">
                                            <span class="text-muted"><i class="fa fa-lock"></i> Secrets are encrypted at rest and never shown again. Access has no time limit; remove the account to revoke it.</span>
                                            <button type="submit" class="btn btn-primary" id="gitAccountSubmit"><i class="fa fa-save"></i> Save account</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>    
    </section>
</div>

<script src="<?php echo base_url(); ?>assets/js/editUser.js" type="text/javascript"></script>
<script type="text/javascript">
(function($) {
    var providers = {
        github: {host: 'github.com', tokenUrl: 'https://github.com/settings/personal-access-tokens/new',
            token: 'Create a <strong>fine-grained personal access token</strong> limited to the repositories you need, with <em>Contents: Read and write</em> to push. Leave the username empty.'},
        gitlab: {host: 'gitlab.com', tokenUrl: 'https://gitlab.com/-/user_settings/personal_access_tokens',
            token: 'Create a <strong>personal access token</strong> with <em>read_repository</em> and <em>write_repository</em>. Leave the username empty.'},
        bitbucket: {host: 'bitbucket.org', tokenUrl: 'https://id.atlassian.com/manage-profile/security/api-tokens',
            token: 'Use your Bitbucket username and an <strong>API token</strong> with the <code>read:repository:bitbucket</code> scope, plus <code>write:repository:bitbucket</code> to push. Bitbucket no longer accepts app passwords.'},
        azure_devops: {host: 'dev.azure.com', tokenUrl: '',
            token: 'In Azure DevOps open <em>User settings &gt; Personal access tokens</em> and create one with <strong>Code (Read &amp; write)</strong>.'},
        generic: {host: '', tokenUrl: '',
            token: 'Use an access token with repository read access, and write access if you will push.'}
    };

    function authType() {
        return $('input[name=auth_type]:checked').val() || 'token';
    }

    function syncGitAccountForm() {
        var auth = authType();
        var provider = providers[$('#gitAccountProvider').val()] || providers.generic;
        var ssh = auth === 'ssh_key' || auth === 'ssh_generate';
        $('.git-auth-option').removeClass('is-selected').has('input:checked').addClass('is-selected');
        $('.git-auth-http').toggle(!ssh);
        $('.git-auth-ssh').toggle(ssh);
        $('.git-auth-ssh-existing').toggle(auth === 'ssh_key');
        $('#gitAccountSecret').prop('required', !ssh);
        $('#gitAccountUsername').prop('required', auth === 'username_password');
        $('#gitAccountUsernameHint').text(auth === 'username_password' ? 'required' : 'optional');
        $('#gitAccountKnownHosts').prop('required', ssh);
        $('#gitAccountSecretLabel').text(auth === 'username_password' ? 'API token or password' : 'Access token');
        $('#gitAccountProviderHelp').html(provider.token + (provider.tokenUrl ? ' <a href="' + provider.tokenUrl + '" target="_blank" rel="noopener">Create one <i class="fa fa-external-link"></i></a>' : ''));
        $('#gitAccountSubmit').html(auth === 'ssh_generate' ? '<i class="fa fa-key"></i> Create key and save' : '<i class="fa fa-save"></i> Save account');
    }

    function setProvider(provider) {
        var host = $('#gitAccountHost');
        var previous = host.data('provider-default') || '';
        var next = (providers[provider] || providers.generic).host;
        $('#gitAccountProvider').val(provider);
        if (host.val() === previous || host.val() === '') {
            host.val(next);
        }
        host.data('provider-default', next);
        $('#gitKnownHostsFingerprints').empty();
        syncGitAccountForm();
    }

    function focusAddForm(title) {
        $('#gitAccountFormTitle').text(title || 'Add an account');
        $('#gitAccountAdd')[0].scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    $('#gitAccountProvider').on('change', function() { setProvider($(this).val()); });
    $('input[name=auth_type]').on('change', syncGitAccountForm);
    $('#gitAccountHost').data('provider-default', 'github.com');

    $('.git-provider-start').on('click', function() {
        setProvider($(this).data('provider'));
        $('input[name=auth_type][value="' + $(this).data('auth') + '"]').prop('checked', true);
        syncGitAccountForm();
        focusAddForm('Add a ' + $('#gitAccountProvider option:selected').text() + ' account');
        window.setTimeout(function() { ($('#gitAccountHost').val() ? (authType().indexOf('ssh') === 0 ? $('#gitFetchKnownHosts') : $('#gitAccountSecret')) : $('#gitAccountHost')).focus(); }, 350);
    });

    // Saving the same host and scope replaces that account's secret.
    $('.git-account-replace').on('click', function() {
        var data = $(this).data();
        setProvider(data.provider);
        $('#gitAccountHost').val(data.host);
        $('#gitAccountPath').val(data.scope || '');
        $('#gitAccountLabel').val(data.label || '');
        $('#gitAccountUsername').val(data.username || '');
        $('input[name=auth_type][value="' + (data.auth === 'ssh_key' ? 'ssh_key' : data.auth) + '"]').prop('checked', true);
        syncGitAccountForm();
        focusAddForm('Replace the secret for ' + data.host + (data.scope ? '/' + data.scope : ''));
    });

    $('#gitAccountForm').on('submit', function() {
        if (authType() === 'ssh_key') {
            $('#gitAccountSecret').val($('#gitAccountPrivateKey').val());
        }
    });

    $('#gitFetchKnownHosts').on('click', function() {
        var button = $(this);
        var host = $.trim($('#gitAccountHost').val() || '');
        var list = $('#gitKnownHostsFingerprints').text('');
        if (host === '') {
            list.text('Enter the provider host first.');
            return;
        }
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Fetching...');
        $.getJSON(<?php echo json_encode(base_url('gitKnownHosts')); ?>, {host: host})
            .done(function(response) {
                $('#gitAccountKnownHosts').val(response.known_hosts);
                list.text('Fingerprints to verify:\n' + (response.fingerprints || []).join('\n'));
                if (host === 'github.com') {
                    list.append($('<div>').html('<a href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints" target="_blank" rel="noopener">Compare with GitHub\'s published fingerprints</a>'));
                }
            })
            .fail(function(xhr) { list.text((xhr.responseJSON && xhr.responseJSON.message) || 'The host keys could not be fetched.'); })
            .always(function() { button.prop('disabled', false).html('<i class="fa fa-download"></i> Fetch from host'); });
    });

    $('.git-account-test-toggle').on('click', function() {
        var panel = $($(this).data('target')).slideToggle(150);
        window.setTimeout(function() { panel.find('.git-test-repository').focus(); }, 160);
    });

    $('.git-run-test').on('click', function() {
        var button = $(this);
        var panel = button.closest('.git-account-test');
        var result = panel.find('.git-test-result').removeClass('is-ok is-error is-running');
        var repository = $.trim(panel.find('.git-test-repository').val() || '');
        if (repository === '') {
            result.addClass('is-error').text('Enter the URL of a repository this account should reach.');
            return;
        }
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
        result.addClass('is-running').text('Contacting the provider...');
        $.ajax({url: <?php echo json_encode(base_url('gitAccountTest')); ?>, type: 'POST', dataType: 'json', data: {account_id: button.data('account-id'), repository_url: repository, branch: ''}})
            .done(function(response) { result.removeClass('is-running').addClass('is-ok').text(response.message + (response.latencyMs != null ? ' (' + response.latencyMs + ' ms)' : '')); })
            .fail(function(xhr) { result.removeClass('is-running').addClass('is-error').text((xhr.responseJSON && xhr.responseJSON.message) || 'The Git connection test failed.'); })
            .always(function() { button.prop('disabled', false).html('<i class="fa fa-plug"></i> Run test'); });
    });
    $('.git-test-repository').on('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).closest('.git-account-test').find('.git-run-test').click();
        }
    });

    $('.git-copy-public-key').on('click', function() {
        var button = $(this);
        var field = button.closest('.input-group').find('.git-public-key');
        var done = function() { button.html('<i class="fa fa-check"></i> Copied'); window.setTimeout(function() { button.html('<i class="fa fa-clipboard"></i> Copy'); }, 1500); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(field.val()).then(done);
        } else {
            field.trigger('select');
            document.execCommand('copy');
            done();
        }
    });

    $('.git-account-delete button').on('click', function(event) {
        if (!window.confirm('Remove ' + $(this).data('account-label') + '? Workspaces that use it lose access immediately.')) {
            event.preventDefault();
        }
    });

    syncGitAccountForm();
    var highlighted = $('.git-account-card.is-highlighted');
    if (highlighted.length) {
        highlighted[0].scrollIntoView({block: 'center'});
    }
})(jQuery);
</script>
