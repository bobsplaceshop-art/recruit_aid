<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Platform\Repositories\EmailTemplateRepository;

/**
 * Resolves {{variable}} placeholders in a DB-backed email template
 * and wraps the result in the branded EmailLayout.
 */
final class EmailRenderer
{
    public function __construct(
        private EmailTemplateRepository $templates,
        private EmailLayout $layout
    ) {}

    /**
     * @param array<string, scalar|null> $variables
     * @return array{subject:string, html:string, text:string}|null
     */
    public function render(string $templateKey, array $variables, string $locale = 'en'): ?array
    {
        $template = $this->templates->findByKey($templateKey, $locale);
        if ($template === null) return null;

        $subject = $this->interpolate($template['subject'], $variables);
        $bodyHtml = $this->interpolate($template['body_html'] ?? '', $variables);
        $bodyText = $this->interpolate($template['body_text'] ?? strip_tags($bodyHtml), $variables);

        $html = $this->layout->wrap($bodyHtml);

        return [
            'subject' => $subject,
            'html'    => $html,
            'text'    => $bodyText,
        ];
    }

    /** @param array<string, scalar|null> $variables */
    private function interpolate(string $template, array $variables): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($variables) {
            $key = $m[1];
            $value = $variables[$key] ?? '';
            return (string) $value;
        }, $template);
    }
}
