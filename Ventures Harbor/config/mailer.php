<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function renderEmailTemplate($innerHtml, $options = []) {
    $heading   = $options['heading'] ?? 'Ventures Harbor';
    $preheader = $options['preheader'] ?? '';
    $year      = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$heading}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$preheader}</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f9;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 12px 32px rgba(15,23,42,0.10);">
          <tr>
            <td style="background:linear-gradient(135deg,#0f172a 0%,#173463 55%,#3983F6 100%);padding:36px 40px;text-align:center;">
              <div style="display:inline-block;width:52px;height:52px;background:#ffffff;border-radius:14px;margin-bottom:14px;">
                <img src="cid:vhlogo" width="40" height="40" alt="Ventures Harbor" style="display:block;margin:6px;border:0;">
              </div>
              <div style="font-family:'Outfit',Arial,sans-serif;font-weight:700;font-size:20px;letter-spacing:0.06em;color:#ffffff;white-space:nowrap;">VENTURES <span style="color:#7dd3fc;">HARBOR</span></div>
            </td>
          </tr>
          <tr>
            <td style="padding:40px 40px 8px 40px;">
              {$innerHtml}
            </td>
          </tr>
          <tr>
            <td style="padding:28px 40px 36px 40px;">
              <div style="border-top:1px solid #e5ebf2;padding-top:20px;text-align:center;">
                <p style="margin:0 0 6px 0;font-size:12.5px;color:#94a3b8;">You're receiving this email because of activity on your Ventures Harbor account.</p>
                <p style="margin:0;font-size:12.5px;color:#b6c0cf;">&copy; {$year} Ventures Harbor. All rights reserved.</p>
              </div>
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

function renderOtpBlock($otp) {
    return '<div style="margin:22px 0;text-align:center;">'
        . '<div style="display:inline-block;background:#f2f6fb;border:1px dashed #3983F6;border-radius:12px;padding:16px 28px;font-family:\'Outfit\',Arial,sans-serif;font-size:32px;font-weight:700;letter-spacing:0.35em;color:#0f172a;">'
        . htmlspecialchars($otp)
        . '</div></div>';
}

/** Branded call-to-action button used inside email bodies. */
function renderEmailButton($text, $url) {
    return '<div style="text-align:center;margin:26px 0 10px 0;">'
        . '<a href="' . htmlspecialchars($url) . '" target="_blank" '
        . 'style="display:inline-block;background:#3983F6;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 32px;border-radius:10px;">'
        . htmlspecialchars($text) . '</a></div>';
}

/* ── OUTGOING MAIL ──
 *
 * Everything the platform sends — OTPs, password resets, payment receipts,
 * expiry reminders — goes out through this one account.
 *
 * Two mailboxes, on purpose: noreply@ authenticates and sends, support@ is the
 * monitored one a reply comes back to (VH_MAIL_REPLY_TO below).
 *
 * To send as a domain address Hostinger provides:
 *
 *   1. hPanel → Emails → create the MAILBOX. It must be a mailbox, not a
 *      forwarder: a forwarder is an alias with no password, so it can receive
 *      and pass mail on but can never authenticate to SMTP to send.
 *   2. Copy that mailbox's SMTP details from hPanel (Hostinger shows them per
 *      mailbox — the host is smtp.hostinger.com for Hostinger Email and
 *      smtp.titan.email for a Titan mailbox; don't guess, read it there).
 *   3. Set the five constants below to it.
 *
 * VH_MAIL_FROM must be the authenticated mailbox or one of its aliases. Sending
 * "from" an address the server didn't authenticate as is what gets mail rejected
 * or filed as spam, and it is the usual reason a switch like this looks broken.
 *
 * Deliverability rests on SPF, DKIM and DMARC records for the domain. Hostinger
 * adds them automatically when the domain uses Hostinger nameservers; if DNS is
 * hosted elsewhere they have to be copied across by hand, or mail will send but
 * land in spam.
 *
 * Failures are never silent: sendEmail() returns false and appends the recipient,
 * subject and SMTP error to logs/email_fallback.log, and the OTP endpoints hand
 * the code back in `devOtp` so an account is never stranded by a mail outage.
 */
const VH_SMTP_HOST      = 'smtp.hostinger.com';
const VH_SMTP_PORT      = 465;                      // 465 = implicit SSL, 587 = STARTTLS
const VH_SMTP_USER      = 'noreply@venturesharbor.com';
const VH_SMTP_PASS      = 'Natram@2005';                       // ← noreply@ mailbox password from hPanel
const VH_MAIL_FROM      = 'noreply@venturesharbor.com';
const VH_MAIL_FROM_NAME = 'Ventures Harbor';

/* Where a reply goes. Everything here is machine-sent, so the From address is a
 * mailbox nobody reads — but people reply to OTP and receipt emails anyway, and
 * without this those replies land in noreply@ and are never seen. Set to the
 * monitored mailbox so a reply reaches a person. Blank it to send no Reply-To.
 *
 * This is the address a HUMAN answers; VH_SMTP_USER is the one the server logs
 * in as. They are deliberately different mailboxes. Keep it in step with the
 * Support Email in Admin → Settings, which is what the app shows users on-screen.
 */
const VH_MAIL_REPLY_TO      = 'support@venturesharbor.com';
const VH_MAIL_REPLY_TO_NAME = 'Ventures Harbor Support';

/* Mail moved off Gmail (venturesharbor@gmail.com) to the domain's own mailboxes
 * once the Hostinger account was confirmed sending. The old app password has
 * been removed from this file rather than left commented out — a credential in
 * a comment still ships in the deploy zip and still works until it is revoked in
 * the Google account it belongs to.
 */

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Named outright rather than left to surface as a generic "535
        // authentication failed" ten lines into the log, which is what an empty
        // password otherwise looks like.
        if (VH_SMTP_PASS === '') {
            throw new Exception('SMTP password is not set — fill in VH_SMTP_PASS in config/mailer.php.');
        }

        $mail->isSMTP();
        $mail->Host       = VH_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = VH_SMTP_USER;
        $mail->Password   = VH_SMTP_PASS;
        // Follows the port rather than being set separately: 587 with implicit
        // SSL fails to connect at all, which is the classic way this breaks when
        // someone moves off port 465.
        $mail->SMTPSecure = VH_SMTP_PORT === 587
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = VH_SMTP_PORT;

        // Recipients
        $mail->setFrom(VH_MAIL_FROM, VH_MAIL_FROM_NAME);
        if (VH_MAIL_REPLY_TO !== '') {
            $mail->addReplyTo(VH_MAIL_REPLY_TO, VH_MAIL_REPLY_TO_NAME);
        }
        $mail->addAddress($to);

        // Content
        // CharSet MUST be set, and set BEFORE Subject/Body. PHPMailer's default
        // is iso-8859-1 (PHPMailer.php: `public $CharSet = self::CHARSET_ISO88591`),
        // so it stamped `charset=iso-8859-1` on a body that has always been UTF-8.
        // The mail client then decoded each UTF-8 byte as one Latin-1 character and
        // every non-ASCII glyph in our templates came out as mojibake:
        //   ₹ (E2 82 B9) → â‚¹      — (E2 80 94) → â€"      é (C3 A9) → Ã©
        // — i.e. every rupee amount, every em dash and any accented name in a
        // cancellation or receipt email. The `<meta charset="UTF-8">` inside the
        // HTML body does NOT save it: mail clients honour the MIME header, not the
        // meta tag. Encoding is base64 for the same reason — the default 8bit
        // relies on the relay advertising 8BITMIME, and one that doesn't will
        // mangle the high bytes in a different way instead.
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $logoPath = __DIR__ . '/../assets/img/logo-mark.png';
        if (strpos($body, 'cid:vhlogo') !== false && is_readable($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'vhlogo', 'logo-mark.png', 'base64', 'image/png');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/email_fallback.log';
        $logMsg = date('[Y-m-d H:i:s]') . " To: $to | Subject: $subject | SMTP Error: " . $mail->ErrorInfo . "\n\n";
        file_put_contents($logFile, $logMsg, FILE_APPEND);
        return false;
    }
}
