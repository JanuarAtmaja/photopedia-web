<?php
// api/send-email.php — Endpoint kirim email via SMTP PHPMailer
// POST /api/send-email
// Body: { "to": "user@email.com", "photo_url": "https://...", "session_id": "...", "name": "Budi" }

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/SMTP.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Method not allowed'], 405);
}

// Rate limit: max 5 email per menit per IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rate_limit('email_' . $ip, 5, 60);

$body = get_json_body();

$to        = sanitize($body['to'] ?? '');
$photoUrl  = sanitize($body['photo_url'] ?? '');
$sessionId = sanitize($body['session_id'] ?? '');
$name      = sanitize($body['name'] ?? 'Pengguna');

// Validasi
if (!$to || !is_valid_email($to)) {
    respond_json(['error' => 'Email tidak valid'], 422);
}
if (!$photoUrl) {
    respond_json(['error' => 'URL foto tidak ditemukan'], 422);
}

// Bangun HTML email
$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foto Photopedia Kamu Siap!</title>
</head>
<body style="margin:0;padding:0;background:#EDE8F5;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;margin-top:32px;margin-bottom:32px;box-shadow:0 4px 24px rgba(75,63,160,0.12);">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#4B3FA0 0%,#6B5FD0 100%);padding:40px 32px;text-align:center;">
      <h1 style="color:#ffffff;font-size:28px;margin:0;font-weight:700;letter-spacing:-0.5px;">📸 Photopedia</h1>
      <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:15px;">Foto kamu sudah siap, {$name}!</p>
    </div>
    <!-- Body -->
    <div style="padding:40px 32px;">
      <p style="color:#1E1B4B;font-size:16px;line-height:1.6;margin:0 0 24px;">
        Hei <strong>{$name}</strong>! 🎉<br><br>
        Terima kasih sudah pakai <strong>Photopedia</strong>. Foto kamu sudah siap diunduh!
      </p>
      <!-- Foto Preview -->
      <div style="text-align:center;margin-bottom:32px;">
        <img src="{$photoUrl}" alt="Foto Photopedia" style="max-width:100%;border-radius:12px;border:3px solid #EDE8F5;box-shadow:0 4px 16px rgba(75,63,160,0.15);" />
      </div>
      <!-- CTA Button -->
      <div style="text-align:center;margin-bottom:32px;">
        <a href="{$photoUrl}" target="_blank" 
           style="display:inline-block;background:linear-gradient(135deg,#4B3FA0,#6B5FD0);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:50px;font-weight:600;font-size:15px;letter-spacing:0.3px;">
          ⬇️ Unduh Foto HD
        </a>
      </div>
      <p style="color:#6B7280;font-size:13px;text-align:center;margin:0;">
        Link ini aktif selama 24 jam. Segera unduh fotomu ya!
      </p>
    </div>
    <!-- Footer -->
    <div style="background:#F8F7FC;padding:24px 32px;text-align:center;border-top:1px solid #EDE8F5;">
      <p style="color:#A78BFA;font-size:13px;margin:0;font-weight:600;">Photopedia &copy; 2025</p>
      <p style="color:#9CA3AF;font-size:12px;margin:4px 0 0;">Dibuat dengan 💜 untuk Gen-Z</p>
    </div>
  </div>
</body>
</html>
HTML;

// Kirim via SMTP PHPMailer
$mail = new PHPMailer(true);

try {
    // Kredensial SMTP
    $smtpHost = env('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = env('SMTP_PORT', 465);
    $smtpUser = env('SMTP_USERNAME', 'januarvino79@gmail.com');
    $smtpPass = env('SMTP_PASSWORD', 'cmnretbsstyikbfr'); // App password dari user
    $smtpName = env('SMTP_FROM_NAME', 'Photopedia');

    // Server settings
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
    if ($smtpPort == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
    }
    $mail->Port       = $smtpPort;

    // Tambahkan opsi ini agar lolos dari masalah sertifikat SSL (opsional, berguna di server lokal)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Penerima
    $mail->setFrom($smtpUser, $smtpName);
    $mail->addAddress($to, $name);

    // Konten
    $mail->CharSet  = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = 'Photopedia - Your Photo is Ready!';
    $mail->Body    = $emailHtml;
    $mail->AltBody = "Hei {$name}! Foto kamu sudah siap diunduh. Silakan buka link berikut: {$photoUrl}";

    $mail->send();
    respond_json(['success' => true, 'message' => 'Email sent via SMTP']);
} catch (Exception $e) {
    error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    respond_json([
        'error'   => 'Gagal mengirim email via SMTP',
        'detail'  => $mail->ErrorInfo
    ], 502);
}
