<?php
/**
 * Mail Configuration
 * Uses Gmail SMTP to send emails.
 */

// ===== Gmail SMTP Settings =====
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'saugat2424@gmail.com');
define('MAIL_PASSWORD', 'kmydhlkakhzfskxh');
define('MAIL_FROM_NAME', 'Herald Clinical Sanctuary');
define('MAIL_ENCRYPTION', 'tls');

// ===== Application URL (used in reset links inside emails) =====
// Change this to your actual domain in production
define('APP_URL', 'http://localhost/Doctor_Appointment_System');
