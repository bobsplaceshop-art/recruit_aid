<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Row;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Email transport service. Wraps PHPMailer.
 * In development, points at Mailpit (127.0.0.1:1025, no auth).
 * In production, points at configured SMTP.
 */
final class EmailService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
        private Logger $logger
    ) {
    }

    /**
     * Send an email.
     *
     * @param string|array<string, string> $to  Single recipient, or [email => name]
     */
    public function send(string|array $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Row::str($this->config, 'host', '127.0.0.1');
            $mail->Port       = Row::int($this->config, 'port', 1025);
            $mail->SMTPAuth   = Row::bool($this->config, 'auth', false);
            $mail->Username   = Row::str($this->config, 'username');
            $mail->Password   = Row::str($this->config, 'password');
            $enc              = Row::str($this->config, 'encryption');
            if ($enc !== '') {
                $mail->SMTPSecure = $enc;
            }
            $mail->SMTPAutoTLS = false;
            $mail->Timeout    = Row::int($this->config, 'timeout', 20);
            $mail->CharSet    = 'UTF-8';

            $fromEmail = Row::str($this->config, 'from_email', 'no-reply@recruitaid.test');
            $fromName  = Row::str($this->config, 'from_name', 'Recruit Aid');
            $mail->setFrom($fromEmail, $fromName);

            $replyTo = Row::str($this->config, 'reply_to');
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            // Recipients
            if (is_string($to)) {
                $mail->addAddress($to);
            } else {
                // array<string, string> — email => name
                foreach ($to as $email => $name) {
                    $mail->addAddress($email, $name);
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
     * @param string|array<string, string> $to
     * @param string               $template  e.g. 'password-reset' (no .php)
     * @param array<string, mixed> $variables Variables extracted into the template
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

    /**
     * Send an email using a DB-backed template (core_email_templates).
     * The template is rendered through EmailRenderer (placeholders +
     * branded layout), then dispatched.
     *
     * @param string|array<string, string> $to
     * @param array<string, scalar|null>   $variables
     */
    public function sendDbTemplate(
        string|array $to,
        string $templateKey,
        array $variables = [],
        string $locale = 'en'
    ): bool {
        /** @var EmailRenderer|null $renderer */
        $renderer = null;
        try {
            $app = \CorporateAid\Core\App::instance();
            $renderer = $app->container()->get(EmailRenderer::class);
        } catch (\Throwable $e) {
            $this->logger->error('EmailRenderer not resolvable', ['error' => $e->getMessage()]);
            return false;
        }

        $rendered = $renderer->render($templateKey, $variables, $locale);
        if ($rendered === null) {
            $this->logger->error('Email template not found', ['template' => $templateKey, 'locale' => $locale]);
            return false;
        }

        return $this->send($to, $rendered['subject'], $rendered['html'], $rendered['text']);
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html));
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
