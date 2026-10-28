<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Email transport service. Wraps PHPMailer.
 * In development, points at Mailpit (127.0.0.1:1025, no auth).
 * In production, points at configured SMTP.
 */
final class EmailService
{
    public function __construct(
        private array $config,
        private Logger $logger
    ) {
    }

    /**
     * Send an email.
     *
     * @param string|array $to       Single recipient or [email => name]
     * @param string       $subject
     * @param string       $htmlBody
     * @param string|null  $textBody Optional plain-text alternative
     * @return bool
     */
    public function send(string|array $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = (string) ($this->config['host'] ?? '127.0.0.1');
            $mail->Port       = (int) ($this->config['port'] ?? 1025);
            $mail->SMTPAuth   = (bool) ($this->config['auth'] ?? false);
            $mail->Username   = (string) ($this->config['username'] ?? '');
            $mail->Password   = (string) ($this->config['password'] ?? '');
            $enc              = (string) ($this->config['encryption'] ?? '');
            if ($enc !== '') {
                $mail->SMTPSecure = $enc;
            }
            $mail->SMTPAutoTLS = false;
            $mail->Timeout    = (int) ($this->config['timeout'] ?? 20);
            $mail->CharSet    = 'UTF-8';

            $fromEmail = (string) ($this->config['from_email'] ?? 'no-reply@recruitaid.test');
            $fromName  = (string) ($this->config['from_name'] ?? 'Recruit Aid');
            $mail->setFrom($fromEmail, $fromName);

            if (!empty($this->config['reply_to'])) {
                $mail->addReplyTo((string) $this->config['reply_to']);
            }

            // Recipients
            if (is_string($to)) {
                $mail->addAddress($to);
            } elseif (is_array($to)) {
                foreach ($to as $key => $value) {
                    if (is_int($key)) {
                        $mail->addAddress(is_scalar($value) ? (string) $value : '');
                    } else {
                        $mail->addAddress((string) $key, is_scalar($value) ? (string) $value : '');
                    }
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? $this->htmlToText($htmlBody);

            $mail->send();
            return true;
        } catch (MailException $e) {
            $this->logger->error('Email send failed', [
                'to' => is_array($to) ? array_keys($to) : $to,
                'subject' => $subject,
                'error' => $mail->ErrorInfo ?: $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an email using a template file from resources/emails/.
     *
     * @param string $template  e.g. 'password-reset' (no .php)
     * @param array  $variables Variables extracted into the template
     */
    public function sendTemplate(string|array $to, string $subject, string $template, array $variables = []): bool
    {
        $path = base_path('resources/emails/' . $template . '.php');
        if (!is_file($path)) {
            $this->logger->error('Email template not found', ['template' => $template]);
            return false;
        }

        ob_start();
        extract($variables, EXTR_SKIP);
        require $path;
        $html = (string) ob_get_clean();

        return $this->send($to, $subject, $html);
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html));
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
