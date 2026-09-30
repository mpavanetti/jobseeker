<?php
$this->load->helper('form');
$userId = $userInfo->userId;
$name = trim((string) $userInfo->name);
$email = trim((string) $userInfo->email);
$mobile = trim((string) $userInfo->mobile);
$role = trim((string) $userInfo->role);
$group = trim((string) $userInfo->group);
$activeTab = in_array($active, array('details', 'changepass', 'git'), TRUE) ? $active : 'details';
$nameParts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
$initials = '';
if (! empty($nameParts)) {
    $initials = strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
    }
}
$initials = $initials !== '' ? $initials : 'JS';
$memberSince = ! empty($userInfo->createdDtm) && strtotime($userInfo->createdDtm)
    ? date('M Y', strtotime($userInfo->createdDtm))
    : 'Not available';
$phoneHref = preg_replace('/[^0-9+]/', '', $mobile);
$gitAccountCount = count((array) $gitAccounts);
?>

<link href="<?php echo base_url(); ?>assets/dist/css/profile.css?v=1" rel="stylesheet" type="text/css">

<div class="content-wrapper profile-page">
    <section class="content-header profile-page-heading">
        <h1><i class="fa fa-user-circle-o"></i> My Profile <small>Manage your account settings</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url('dashboard'); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">My Profile</li>
        </ol>
    </section>

    <section class="content profile-content">
        <div class="profile-hero">
            <div class="profile-avatar" aria-hidden="true"><span data-profile-initials><?php echo html_escape($initials); ?></span></div>
            <div class="profile-hero-copy">
                <div class="profile-hero-title-row">
                    <h2 data-profile-name><?php echo html_escape($name); ?></h2>
                    <span class="profile-role-badge"><i class="fa fa-shield"></i> <?php echo html_escape($role ?: 'Member'); ?></span>
                </div>
                <p><?php echo html_escape($group ?: 'No team assigned'); ?><?php if ($memberSince !== 'Not available') { ?> <span aria-hidden="true">·</span> Member since <?php echo html_escape($memberSince); ?><?php } ?></p>
                <div class="profile-contact-list">
                    <a href="mailto:<?php echo html_escape($email); ?>" data-profile-email-link><i class="fa fa-envelope-o"></i> <span data-profile-email><?php echo html_escape($email); ?></span></a>
                    <?php if ($mobile !== '') { ?><a href="tel:<?php echo html_escape($phoneHref); ?>" data-profile-phone-link><i class="fa fa-phone"></i> <span data-profile-phone><?php echo html_escape($mobile); ?></span></a><?php } ?>
                </div>
            </div>
            <button type="button" class="btn btn-primary profile-hero-action" data-profile-tab="#details"><i class="fa fa-pencil"></i> Edit details</button>
        </div>

        <div class="profile-alerts" aria-live="polite">
            <?php $error = $this->session->flashdata('error'); if ($error) { ?>
                <div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-label="Dismiss">×</button><i class="fa fa-exclamation-circle"></i> <?php echo html_escape($error); ?></div>
            <?php } ?>
            <?php $success = $this->session->flashdata('success'); if ($success) { ?>
                <div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-label="Dismiss">×</button><i class="fa fa-check-circle"></i> <?php echo html_escape($success); ?></div>
            <?php } ?>
            <?php $noMatch = $this->session->flashdata('nomatch'); if ($noMatch) { ?>
                <div class="alert alert-warning alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-label="Dismiss">×</button><i class="fa fa-exclamation-triangle"></i> <?php echo html_escape($noMatch); ?></div>
            <?php } ?>
            <?php echo validation_errors('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-label="Dismiss">×</button><i class="fa fa-exclamation-circle"></i> ', '</div>'); ?>
        </div>

        <div class="row profile-grid">
            <aside class="col-md-3">
                <div class="profile-summary-card">
                    <div class="profile-card-heading">
                        <span class="profile-card-icon"><i class="fa fa-id-card-o"></i></span>
                        <div><h3>Account overview</h3><p>Your workspace identity</p></div>
                    </div>
                    <dl class="profile-facts">
                        <div><dt>Role</dt><dd><?php echo html_escape($role ?: 'Member'); ?></dd></div>
                        <div><dt>Team</dt><dd><?php echo html_escape($group ?: 'Not assigned'); ?></dd></div>
                        <div><dt>Member since</dt><dd><?php echo html_escape($memberSince); ?></dd></div>
                        <div><dt>Git accounts</dt><dd><?php echo (int) $gitAccountCount; ?> connected</dd></div>
                    </dl>
                    <div class="profile-security-callout">
                        <span><i class="fa fa-lock"></i></span>
                        <div><strong>Security check</strong><p>Use a unique password with at least 8 characters.</p><button type="button" class="btn btn-link" data-profile-tab="#changepass">Review password</button></div>
                    </div>
                </div>
            </aside>

            <div class="col-md-9">
                <div class="nav-tabs-custom profile-tabs">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="<?php echo $activeTab === 'details' ? 'active' : ''; ?>"><a href="#details" data-toggle="tab" role="tab"><i class="fa fa-user-o"></i><span>Personal details</span></a></li>
                        <li class="<?php echo $activeTab === 'changepass' ? 'active' : ''; ?>"><a href="#changepass" data-toggle="tab" role="tab"><i class="fa fa-lock"></i><span>Password</span></a></li>
                        <li class="<?php echo $activeTab === 'git' ? 'active' : ''; ?>"><a href="#git" data-toggle="tab" role="tab"><i class="fa fa-code-fork"></i><span>Git accounts</span><span class="profile-tab-count"><?php echo (int) $gitAccountCount; ?></span></a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="<?php echo $activeTab === 'details' ? 'active' : ''; ?> tab-pane" id="details">
                            <div class="profile-section-heading">
                                <div><span class="profile-section-kicker">Personal information</span><h3>How others see you</h3><p>These details identify you throughout JobSeeker.</p></div>
                                <span class="profile-save-state" id="profileSaveState"><i class="fa fa-check-circle"></i> Up to date</span>
                            </div>
                            <form action="<?php echo base_url('profileUpdate'); ?>" method="post" id="editProfile" role="form">
                                <input type="hidden" value="<?php echo html_escape($userId); ?>" name="userId" id="userId">
                                <div class="profile-form-grid">
                                    <div class="form-group profile-form-wide">
                                        <label for="fname">Full name</label>
                                        <div class="profile-input-wrap"><i class="fa fa-user-o"></i><input type="text" class="form-control" id="fname" name="fname" value="<?php echo html_escape(set_value('fname', $name)); ?>" maxlength="128" autocomplete="name" required></div>
                                        <span class="help-block">Use the name your teammates will recognize.</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email address</label>
                                        <div class="profile-input-wrap"><i class="fa fa-envelope-o"></i><input type="email" class="form-control" id="email" name="email" value="<?php echo html_escape(set_value('email', $email)); ?>" maxlength="128" autocomplete="email" required></div>
                                        <span class="help-block">Used to sign in and receive account messages.</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="mobile">Phone number <span class="profile-label-note">with country code</span></label>
                                        <div class="profile-input-wrap"><i class="fa fa-phone"></i><input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo html_escape(set_value('mobile', $mobile)); ?>" maxlength="30" inputmode="tel" autocomplete="tel" placeholder="+1 415 555 2671" aria-describedby="mobileHelp mobilePreview" required></div>
                                        <span class="help-block" id="mobileHelp">Include <strong>+</strong> and your country calling code for an international number.</span>
                                        <span class="profile-phone-preview" id="mobilePreview" aria-live="polite"></span>
                                    </div>
                                </div>
                                <div class="profile-form-actions">
                                    <span><i class="fa fa-info-circle"></i> Role and team assignments are managed by an administrator.</span>
                                    <div><button type="reset" class="btn btn-default"><i class="fa fa-undo"></i> Reset</button><button type="submit" class="btn btn-primary profile-primary-action" id="profileSaveButton"><i class="fa fa-check"></i> Save changes</button></div>
                                </div>
                            </form>
                        </div>

                        <div class="<?php echo $activeTab === 'changepass' ? 'active' : ''; ?> tab-pane" id="changepass">
                            <div class="profile-section-heading">
                                <div><span class="profile-section-kicker">Account security</span><h3>Change your password</h3><p>Choose a strong password you do not use anywhere else.</p></div>
                                <span class="profile-secure-badge"><i class="fa fa-shield"></i> Encrypted</span>
                            </div>
                            <form role="form" action="<?php echo base_url('changePassword'); ?>" method="post" id="changePasswordForm">
                                <input type="email" name="username" value="<?php echo html_escape($email); ?>" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true">
                                <div class="profile-password-layout">
                                    <div>
                                        <div class="form-group">
                                            <label for="inputOldPassword">Current password</label>
                                            <div class="input-group profile-password-input"><input type="password" class="form-control" id="inputOldPassword" name="oldPassword" maxlength="64" autocomplete="current-password" required><span class="input-group-btn"><button class="btn btn-default profile-password-toggle" type="button" aria-label="Show current password" data-password-target="#inputOldPassword"><i class="fa fa-eye"></i></button></span></div>
                                        </div>
                                        <div class="form-group">
                                            <label for="inputPassword1">New password</label>
                                            <div class="input-group profile-password-input"><input type="password" class="form-control" id="inputPassword1" name="newPassword" minlength="8" maxlength="64" autocomplete="new-password" aria-describedby="passwordStrength passwordRequirements" required><span class="input-group-btn"><button class="btn btn-default profile-password-toggle" type="button" aria-label="Show new password" data-password-target="#inputPassword1"><i class="fa fa-eye"></i></button></span></div>
                                            <div class="profile-password-meter" id="passwordStrength" aria-live="polite"><span></span><span></span><span></span><span></span><strong>Enter a new password</strong></div>
                                        </div>
                                        <div class="form-group">
                                            <label for="inputPassword2">Confirm new password</label>
                                            <div class="input-group profile-password-input"><input type="password" class="form-control" id="inputPassword2" name="cNewPassword" minlength="8" maxlength="64" autocomplete="new-password" required><span class="input-group-btn"><button class="btn btn-default profile-password-toggle" type="button" aria-label="Show confirmed password" data-password-target="#inputPassword2"><i class="fa fa-eye"></i></button></span></div>
                                            <span class="profile-password-match" id="passwordMatch" aria-live="polite"></span>
                                        </div>
                                    </div>
                                    <div class="profile-password-tips" id="passwordRequirements">
                                        <span class="profile-card-icon"><i class="fa fa-lightbulb-o"></i></span>
                                        <h4>A stronger password has</h4>
                                        <ul><li data-password-rule="length"><i class="fa fa-circle-o"></i> At least 8 characters</li><li data-password-rule="case"><i class="fa fa-circle-o"></i> Upper and lowercase letters</li><li data-password-rule="number"><i class="fa fa-circle-o"></i> A number</li><li data-password-rule="symbol"><i class="fa fa-circle-o"></i> A symbol</li></ul>
                                    </div>
                                </div>
                                <div class="profile-form-actions"><span><i class="fa fa-sign-out"></i> You may need to sign in again on other devices.</span><div><button type="reset" class="btn btn-default">Clear</button><button type="submit" class="btn btn-primary profile-primary-action"><i class="fa fa-lock"></i> Update password</button></div></div>
                            </form>
                        </div>
                        <div class="<?php echo $activeTab === 'git' ? 'active' : ''; ?> tab-pane" id="git">
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

<script src="<?php echo base_url(); ?>assets/js/profile.js?v=1" type="text/javascript"></script>
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
