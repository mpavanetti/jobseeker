<?php
$recipientName = isset($data['name']) && trim((string) $data['name']) !== '' ? trim((string) $data['name']) : 'there';
$resetLink = isset($data['reset_link']) ? (string) $data['reset_link'] : '';
$requestedAt = isset($data['requested_at']) ? (string) $data['requested_at'] : '';
$clientIp = isset($data['client_ip']) ? (string) $data['client_ip'] : '';
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your Job Seeker password</title>
  </head>
  <body style="background:#eef1f4;color:#263238;font-family:Arial,Helvetica,sans-serif;margin:0;padding:0;">
    <div style="display:none;font-size:1px;color:#eef1f4;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
      Your one-time Job Seeker password reset link expires in 60 minutes.
    </div>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#eef1f4;border-collapse:collapse;width:100%;">
      <tr>
        <td align="center" style="padding:32px 16px;">
          <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;max-width:600px;width:100%;">
            <tr>
              <td style="background:#263238;border-radius:6px 6px 0 0;color:#ffffff;padding:20px 28px;">
                <a href="<?php echo html_escape(base_url()); ?>" style="color:#ffffff;font-size:20px;font-weight:bold;text-decoration:none;">Job Seeker</a>
                <div style="color:#b0bec5;font-size:12px;margin-top:4px;">Account security</div>
              </td>
            </tr>
            <tr>
              <td style="background:#ffffff;border:1px solid #d9e0e5;border-top:0;padding:32px 28px;">
                <h1 style="color:#1f2d33;font-size:24px;line-height:32px;margin:0 0 16px;">Reset your password</h1>
                <p style="font-size:15px;line-height:24px;margin:0 0 14px;">Hi <?php echo html_escape($recipientName); ?>,</p>
                <p style="font-size:15px;line-height:24px;margin:0 0 24px;">We received a request to reset the password for your Job Seeker account. Use the button below to choose a new password.</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">
                  <tr>
                    <td style="background:#157a8a;border-radius:4px;">
                      <a href="<?php echo html_escape($resetLink); ?>" style="color:#ffffff;display:inline-block;font-size:15px;font-weight:bold;padding:12px 20px;text-decoration:none;" target="_blank">Reset password</a>
                    </td>
                  </tr>
                </table>
                <p style="color:#52646d;font-size:14px;line-height:22px;margin:0 0 18px;"><strong>This link is one-time use and expires in 60 minutes.</strong> If another reset email is requested, this link will stop working.</p>
                <?php if ($requestedAt !== '' || $clientIp !== '') { ?>
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f7f9fa;border-collapse:collapse;border-left:3px solid #f0ad4e;margin:0 0 20px;width:100%;">
                  <tr>
                    <td style="color:#52646d;font-size:13px;line-height:21px;padding:12px 14px;">
                      <?php if ($requestedAt !== '') { ?><strong>Requested:</strong> <?php echo html_escape($requestedAt); ?><br><?php } ?>
                      <?php if ($clientIp !== '') { ?><strong>IP address:</strong> <?php echo html_escape($clientIp); ?><?php } ?>
                    </td>
                  </tr>
                </table>
                <?php } ?>
                <p style="font-size:14px;line-height:22px;margin:0 0 18px;">If you did not request a password reset, no action is needed and your password will remain unchanged.</p>
                <p style="color:#6b7c85;font-size:12px;line-height:19px;margin:0;">Button not working? Open this address:<br><a href="<?php echo html_escape($resetLink); ?>" style="color:#157a8a;overflow-wrap:anywhere;word-break:break-all;" target="_blank"><?php echo html_escape($resetLink); ?></a></p>
              </td>
            </tr>
            <tr>
              <td style="color:#718089;font-size:12px;line-height:18px;padding:18px 28px;text-align:center;">
                This automated message was sent by Job Seeker.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
