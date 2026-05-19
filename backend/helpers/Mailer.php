<?php
/**
 * Mailer Helper
 * Sends emails using PHPMailer via Gmail SMTP.
 * Provides a reusable sendMail() function and a beautiful
 * HTML template for password-reset emails.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using the configured SMTP settings.
 *
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient display name
 * @param string $subject   Email subject line
 * @param string $htmlBody  Full HTML content of the email
 * @param string $plainBody Plain-text fallback (auto-stripped if empty)
 * @return array ['success' => bool, 'message' => string]
 */
function sendMail($toEmail, $toName, $subject, $htmlBody, $plainBody = '')
{
    $mail = new PHPMailer(true);

    try {
        // ----- SMTP Configuration -----
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // ----- Sender / Recipient -----
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // ----- Content -----
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags(str_replace('<br>', "\n", $htmlBody));
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}

/**
 * Build a beautiful HTML email for password reset.
 *
 * @param string $userName  The user's display name
 * @param string $resetLink The full URL to the reset-password page with token
 * @return string           Complete HTML document for the email body
 */
function buildPasswordResetEmail($userName, $resetLink)
{
    $year = date('Y');
    $expiryMinutes = 60; // matches the 1-hour token lifetime

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Reset</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f23;font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#0f0f23;padding:40px 20px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;">

          <!-- Header with Logo -->
          <tr>
            <td align="center" style="padding:30px 0 20px;">
              <div style="width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:inline-flex;align-items:center;justify-content:center;">
                <span style="font-size:28px;color:#ffffff;">🛡️</span>
              </div>
              <h1 style="margin:12px 0 0;color:#ffffff;font-size:22px;font-weight:600;letter-spacing:-0.5px;">
                Herald Clinical Sanctuary
              </h1>
            </td>
          </tr>

          <!-- Main Content Card -->
          <tr>
            <td>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                     style="background:linear-gradient(145deg,#1a1a3e,#16163a);border-radius:16px;border:1px solid rgba(99,102,241,0.2);overflow:hidden;">
                <!-- Accent Bar -->
                <tr>
                  <td style="height:4px;background:linear-gradient(90deg,#6366f1,#8b5cf6,#a78bfa);"></td>
                </tr>

                <!-- Body -->
                <tr>
                  <td style="padding:40px 36px;">
                    <h2 style="margin:0 0 8px;color:#ffffff;font-size:24px;font-weight:700;">
                      Password Reset Request
                    </h2>
                    <p style="margin:0 0 24px;color:#a5a5c0;font-size:15px;line-height:1.6;">
                      Hello <strong style="color:#c4b5fd;">{$userName}</strong>,
                    </p>
                    <p style="margin:0 0 24px;color:#a5a5c0;font-size:15px;line-height:1.6;">
                      We received a request to reset your password for your Herald Clinical Sanctuary account.
                      Click the button below to create a new password.
                    </p>

                    <!-- CTA Button -->
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        <td align="center" style="padding:8px 0 28px;">
                          <a href="{$resetLink}"
                             style="display:inline-block;padding:14px 40px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:12px;letter-spacing:0.3px;box-shadow:0 4px 20px rgba(99,102,241,0.4);">
                            Reset My Password
                          </a>
                        </td>
                      </tr>
                    </table>

                    <!-- Info Box -->
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                           style="background:rgba(99,102,241,0.08);border-radius:12px;border:1px solid rgba(99,102,241,0.15);">
                      <tr>
                        <td style="padding:16px 20px;">
                          <p style="margin:0;color:#8b8baa;font-size:13px;line-height:1.5;">
                            ⏱️ This link will expire in <strong style="color:#a78bfa;">{$expiryMinutes} minutes</strong>.<br>
                            🔒 If you didn't request this, please ignore this email — your password will remain unchanged.
                          </p>
                        </td>
                      </tr>
                    </table>

                    <!-- Fallback Link -->
                    <p style="margin:24px 0 0;color:#6b6b8a;font-size:12px;line-height:1.5;">
                      If the button doesn't work, copy and paste this link into your browser:<br>
                      <a href="{$resetLink}" style="color:#818cf8;word-break:break-all;text-decoration:none;">
                        {$resetLink}
                      </a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding:28px 20px 10px;">
              <p style="margin:0;color:#4a4a6a;font-size:12px;line-height:1.5;">
                © {$year} Herald Clinical Sanctuary. All rights reserved.<br>
                This is an automated message — please do not reply.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
