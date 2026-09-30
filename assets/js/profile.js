(function($) {
  'use strict';

  $(function() {
    var profileForm = $('#editProfile');
    var passwordForm = $('#changePasswordForm');
    var profileIsDirty = false;
    var initialProfileState = profileForm.length ? profileForm.serialize() : '';

    function phoneDetails(value) {
      var raw = $.trim(String(value || ''));
      var international = raw.indexOf('+') === 0 || raw.indexOf('00') === 0;
      var digits = raw.replace(/\D/g, '');

      if (raw.indexOf('00') === 0) {
        digits = digits.substring(2);
      }

      return {
        raw: raw,
        digits: digits,
        international: international,
        normalized: (international ? '+' : '') + digits
      };
    }

    function validPhone(value) {
      var phone = phoneDetails(value);
      return /^(?:\+|00)?[0-9][0-9\s().-]*[0-9]$/.test(phone.raw) &&
        phone.raw.length <= 30 && phone.digits.length >= 7 && phone.digits.length <= 15 &&
        (!phone.international || phone.digits.charAt(0) !== '0');
    }

    function initials(value) {
      var parts = $.trim(String(value || '')).split(/\s+/).filter(Boolean);
      if (!parts.length) {
        return 'JS';
      }

      return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
    }

    function updatePhonePreview() {
      var phone = phoneDetails($('#mobile').val());
      var preview = $('#mobilePreview').removeClass('is-error');

      if (!phone.raw) {
        preview.text('');
      } else if (!validPhone(phone.raw)) {
        preview.addClass('is-error').text('Use 7–15 digits; spaces, brackets, and dashes are welcome.');
      } else {
        preview.text('Will be saved as ' + phone.normalized);
      }
    }

    function updateProfilePreview() {
      var currentName = $.trim($('#fname').val()) || 'Your name';
      var currentEmail = $.trim($('#email').val());
      var currentPhone = $.trim($('#mobile').val());
      var normalizedPhone = phoneDetails(currentPhone).normalized;

      $('[data-profile-name]').text(currentName);
      $('[data-profile-initials]').text(initials(currentName));
      $('[data-profile-email]').text(currentEmail || 'Email address');
      $('[data-profile-email-link]').attr('href', currentEmail ? 'mailto:' + currentEmail : '#');
      $('[data-profile-phone]').text(currentPhone);
      $('[data-profile-phone-link]').attr('href', normalizedPhone ? 'tel:' + normalizedPhone : '#');
      updatePhonePreview();
    }

    function updateDirtyState() {
      profileIsDirty = profileForm.length && profileForm.serialize() !== initialProfileState;
      $('#profileSaveState')
        .toggleClass('is-dirty', profileIsDirty)
        .html(profileIsDirty ? '<i class="fa fa-pencil"></i> Unsaved changes' : '<i class="fa fa-check-circle"></i> Up to date');
      updateProfilePreview();
    }

    if ($.validator) {
      $.validator.addMethod('internationalPhone', validPhone, 'Enter a valid phone number with 7 to 15 digits.');

      profileForm.validate({
        rules: {
          fname: { required: true, maxlength: 128 },
          email: {
            required: true,
            email: true,
            maxlength: 128,
            remote: {
              url: baseURL + 'checkEmailExists',
              type: 'post',
              data: { userId: function() { return $('#userId').val(); } }
            }
          },
          mobile: { required: true, internationalPhone: true }
        },
        messages: {
          fname: { required: 'Add the name your teammates should see.' },
          email: { required: 'Add your email address.', email: 'Enter a valid email address.', remote: 'That email address is already in use.' },
          mobile: { required: 'Add a phone number.' }
        },
        errorPlacement: function(error, element) {
          error.insertAfter(element.closest('.profile-input-wrap').length ? element.closest('.profile-input-wrap') : element);
        }
      });

      passwordForm.validate({
        rules: {
          oldPassword: { required: true, maxlength: 64 },
          newPassword: { required: true, minlength: 8, maxlength: 64 },
          cNewPassword: { required: true, minlength: 8, maxlength: 64, equalTo: '#inputPassword1' }
        },
        messages: {
          oldPassword: { required: 'Enter your current password.' },
          newPassword: { required: 'Choose a new password.', minlength: 'Use at least 8 characters.' },
          cNewPassword: { required: 'Confirm your new password.', equalTo: 'The passwords do not match.' }
        },
        errorPlacement: function(error, element) {
          error.insertAfter(element.closest('.profile-password-input'));
        }
      });
    }

    profileForm.on('input change', 'input', updateDirtyState);
    profileForm.on('reset', function() {
      window.setTimeout(updateDirtyState, 0);
    });
    profileForm.on('submit', function() {
      if (!$.validator || profileForm.valid()) {
        profileIsDirty = false;
        $('#profileSaveButton').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');
      }
    });

    $('[data-profile-tab]').on('click', function() {
      var target = $(this).data('profile-tab');
      $('.profile-tabs > .nav-tabs a[href="' + target + '"]').tab('show');
      var tabs = $('.profile-tabs');
      if (tabs.length) {
        $('html, body').animate({ scrollTop: Math.max(0, tabs.offset().top - 70) }, 180);
      }
    });

    $('.profile-password-toggle').on('click', function() {
      var button = $(this);
      var input = $(button.data('password-target'));
      var reveal = input.attr('type') === 'password';
      input.attr('type', reveal ? 'text' : 'password');
      button.attr('aria-label', (reveal ? 'Hide' : 'Show') + ' password');
      button.find('i').toggleClass('fa-eye', !reveal).toggleClass('fa-eye-slash', reveal);
    });

    function setPasswordRule(name, met) {
      var item = $('[data-password-rule="' + name + '"]').toggleClass('is-met', met);
      item.find('i').toggleClass('fa-circle-o', !met).toggleClass('fa-check-circle', met);
    }

    function updatePasswordFeedback() {
      var password = $('#inputPassword1').val() || '';
      var confirmation = $('#inputPassword2').val() || '';
      var hasLength = password.length >= 8;
      var hasCase = /[a-z]/.test(password) && /[A-Z]/.test(password);
      var hasNumber = /\d/.test(password);
      var hasSymbol = /[^A-Za-z0-9]/.test(password);
      var score = password ? 1 : 0;
      var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
      var meter = $('#passwordStrength');
      var match = $('#passwordMatch').removeClass('is-match is-mismatch');

      if (hasLength) { score++; }
      if (hasCase) { score++; }
      if (hasNumber || hasSymbol) { score++; }
      if (password.length >= 12 && hasCase && hasNumber && hasSymbol) { score = 4; }
      score = Math.min(4, score);

      setPasswordRule('length', hasLength);
      setPasswordRule('case', hasCase);
      setPasswordRule('number', hasNumber);
      setPasswordRule('symbol', hasSymbol);
      meter.removeClass('strength-1 strength-2 strength-3 strength-4').addClass(score ? 'strength-' + score : '');
      meter.find('strong').text(score ? labels[score] + ' password' : 'Enter a new password');

      if (!confirmation) {
        match.text('');
      } else if (confirmation === password) {
        match.addClass('is-match').html('<i class="fa fa-check-circle"></i> Passwords match');
      } else {
        match.addClass('is-mismatch').html('<i class="fa fa-exclamation-circle"></i> Passwords do not match');
      }
    }

    passwordForm.on('input', 'input[type="password"], input[type="text"]', updatePasswordFeedback);
    passwordForm.on('reset', function() { window.setTimeout(updatePasswordFeedback, 0); });

    $(window).on('beforeunload.profile', function(event) {
      if (!profileIsDirty) {
        return undefined;
      }
      event.preventDefault();
      event.returnValue = '';
      return '';
    });

    updateProfilePreview();
    updatePasswordFeedback();
  });
})(jQuery);
