<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Job Seeker | Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/png" href="<?php echo base_url(); ?>assets/images/bi.png" sizes="16x16">
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
        max-width: 440px;
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
      .password-account {
        background: #f7f9fa;
        border: 1px solid #dce3e7;
        border-radius: 4px;
        color: #455a64;
        margin-bottom: 20px;
        padding: 10px 12px;
      }
      .password-field .input-group-btn .btn {
        height: 46px;
        width: 46px;
      }
      .password-requirement,
      .password-match {
        color: #607d8b;
        display: block;
        font-size: 12px;
        margin-top: 5px;
      }
      .password-match.is-valid {
        color: #00875a;
      }
      .password-match.is-invalid {
        color: #c62828;
      }
      .password-submit {
        font-weight: 600;
        margin-top: 8px;
      }
    </style>
  </head>
  <body class="password-page">
    <main class="password-shell">
      <a class="password-brand" href="<?php echo base_url(); ?>">Job Seeker</a>
      <section class="password-panel" aria-labelledby="resetPasswordTitle">
        <h1 id="resetPasswordTitle">Choose a new password</h1>
        <p class="password-intro">This reset link can be used once and expires 60 minutes after it was requested.</p>

        <?php $this->load->helper('form'); ?>
        <?php echo validation_errors('<div class="alert alert-danger alert-dismissable" role="alert">', ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">x</span></button></div>'); ?>
        <?php $error = $this->session->flashdata('error'); ?>
        <?php if ($error) { ?>
        <div class="alert alert-danger alert-dismissable" role="alert">
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">x</span></button>
          <?php echo html_escape($error); ?>
        </div>
        <?php } ?>

        <div class="password-account"><i class="fa fa-user"></i> <?php echo html_escape($email); ?></div>
        <form action="<?php echo base_url(); ?>createPasswordUser" method="post" id="newPasswordForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="activation_code" value="<?php echo html_escape($activation_code); ?>">

          <div class="form-group password-field">
            <label for="password">New password</label>
            <div class="input-group">
              <input type="password" id="password" class="form-control input-lg" name="password" minlength="8" maxlength="64" autocomplete="new-password" aria-describedby="passwordRequirement" autofocus required>
              <span class="input-group-btn">
                <button type="button" class="btn btn-default password-visibility" data-password-target="#password" aria-label="Show new password" title="Show password"><i class="fa fa-eye"></i></button>
              </span>
            </div>
            <small id="passwordRequirement" class="password-requirement">Use at least 8 characters.</small>
          </div>

          <div class="form-group password-field">
            <label for="cpassword">Confirm new password</label>
            <div class="input-group">
              <input type="password" id="cpassword" class="form-control input-lg" name="cpassword" minlength="8" maxlength="64" autocomplete="new-password" aria-describedby="passwordMatch" required>
              <span class="input-group-btn">
                <button type="button" class="btn btn-default password-visibility" data-password-target="#cpassword" aria-label="Show confirmed password" title="Show password"><i class="fa fa-eye"></i></button>
              </span>
            </div>
            <small id="passwordMatch" class="password-match" aria-live="polite"></small>
          </div>

          <button type="submit" class="btn btn-primary btn-lg btn-block password-submit"><i class="fa fa-lock"></i> Update password</button>
        </form>
      </section>
    </main>

    <script src="<?php echo base_url(); ?>assets/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    <script>
      $(function() {
        $('.password-visibility').on('click', function() {
          var $button = $(this);
          var $input = $($button.data('password-target'));
          var showPassword = $input.attr('type') === 'password';
          $input.attr('type', showPassword ? 'text' : 'password');
          $button.attr('aria-label', showPassword ? 'Hide password' : 'Show password');
          $button.attr('title', showPassword ? 'Hide password' : 'Show password');
          $button.find('i').toggleClass('fa-eye', !showPassword).toggleClass('fa-eye-slash', showPassword);
        });

        function updatePasswordMatch() {
          var password = $('#password').val();
          var confirmation = $('#cpassword').val();
          var $message = $('#passwordMatch');
          $message.removeClass('is-valid is-invalid');
          if (!confirmation) {
            $message.text('');
          } else if (password === confirmation) {
            $message.addClass('is-valid').text('Passwords match.');
          } else {
            $message.addClass('is-invalid').text('Passwords do not match.');
          }
        }

        $('#password, #cpassword').on('input', updatePasswordMatch);
      });
    </script>
  </body>
</html>
