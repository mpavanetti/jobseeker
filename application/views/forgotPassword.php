<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Job Seeker | Forgot Password</title>
    <link rel="shortcut icon" type="image/png" href="<?php echo base_url(); ?>assets/images/bi.png" sizes="16x16">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>assets/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700">
    <style>
      .password-page {
        align-items: center;
        background: #263238 url(<?php echo base_url(); ?>assets/images/wallpaper/wallpaper2.jpg) no-repeat center center fixed;
        background-size: cover;
        display: flex;
        min-height: 100vh;
        padding: 24px 16px;
      }
      .password-shell {
        margin: 0 auto;
        max-width: 420px;
        width: 100%;
      }
      .password-brand {
        color: #ffffff;
        display: block;
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 18px;
        text-align: center;
        text-decoration: none;
      }
      .password-brand:hover,
      .password-brand:focus {
        color: #ffffff;
        text-decoration: none;
      }
      .password-panel {
        background: #ffffff;
        border-radius: 6px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .28);
        padding: 28px;
      }
      .password-panel h1 {
        color: #263238;
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 8px;
      }
      .password-intro {
        color: #607d8b;
        line-height: 1.5;
        margin-bottom: 22px;
      }
      .password-panel label {
        color: #37474f;
      }
      .password-submit {
        font-weight: 600;
        margin-top: 6px;
      }
      .password-return {
        display: inline-block;
        margin-top: 18px;
      }
      .password-panel .alert {
        padding-right: 34px;
      }
    </style>
  </head>
  <body class="password-page">
    <main class="password-shell">
      <a class="password-brand" href="<?php echo base_url(); ?>">Job Seeker</a>
      <section class="password-panel" aria-labelledby="forgotPasswordTitle">
        <h1 id="forgotPasswordTitle">Forgot your password?</h1>
        <p class="password-intro">Enter your account email. If it is registered, we will send a one-time reset link that expires in 60 minutes.</p>

        <?php $this->load->helper('form'); ?>
        <?php echo validation_errors('<div class="alert alert-danger alert-dismissable" role="alert">', ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">x</span></button></div>'); ?>
        <?php
        $alerts = array(
          'error' => 'danger',
          'send' => 'success',
          'notsend' => 'danger',
          'unable' => 'danger',
          'invalid' => 'warning'
        );
        foreach ($alerts as $flashKey => $alertClass) {
          $flashMessage = $this->session->flashdata($flashKey);
          if (!$flashMessage) {
            continue;
          }
        ?>
        <div class="alert alert-<?php echo $alertClass; ?> alert-dismissable" role="alert">
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">x</span></button>
          <?php echo html_escape($flashMessage); ?>
        </div>
        <?php } ?>

        <form action="<?php echo base_url(); ?>resetPasswordUser" method="post">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <div class="form-group has-feedback">
            <label for="login_email">Email address</label>
            <input type="email" id="login_email" class="form-control input-lg" placeholder="you@example.com" name="login_email" value="<?php echo html_escape(set_value('login_email')); ?>" autocomplete="email" maxlength="128" autofocus required>
            <span class="glyphicon glyphicon-envelope form-control-feedback" style="top: 30px;"></span>
          </div>
          <button type="submit" class="btn btn-primary btn-lg btn-block password-submit"><i class="fa fa-envelope-o"></i> Send reset link</button>
        </form>
        <a class="password-return" href="<?php echo base_url(); ?>"><i class="fa fa-arrow-left"></i> Back to sign in</a>
      </section>
    </main>

    <script src="<?php echo base_url(); ?>assets/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  </body>
</html>
