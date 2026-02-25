<?php

namespace App\Services;

use App\Contracts\EmailTemplateDefinition;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateService
{
    /**
     * Discover all template definitions from app/Mail/Templates/
     *
     * @return Collection<int, class-string<EmailTemplateDefinition>>
     */
    public function discoverTemplates(): Collection
    {
        $classes = collect();
        $path = app_path('Mail/Templates');

        if (! is_dir($path)) {
            return $classes;
        }

        foreach (glob("{$path}/*.php") as $file) {
            $className = 'App\\Mail\\Templates\\'.pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($className) && in_array(EmailTemplateDefinition::class, class_implements($className) ?: [])) {
                $classes->push($className);
            }
        }

        return $classes;
    }

    /**
     * Sync discovered templates to database (create missing, preserve customized)
     *
     * @return array{created: int, skipped: int}
     */
    public function syncTemplates(): array
    {
        $templates = $this->discoverTemplates();
        $created = 0;
        $skipped = 0;

        foreach ($templates as $templateClass) {
            $key = $templateClass::getKey();

            $exists = EmailTemplate::where('key', $key)->exists();

            if (! $exists) {
                EmailTemplate::create([
                    'key' => $key,
                    'name' => $templateClass::getName(),
                    'subject' => $templateClass::getDefaultSubject(),
                    'body' => $templateClass::getDefaultBody(),
                    'is_active' => true,
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Get template by key
     */
    public function getTemplate(string $key): ?EmailTemplate
    {
        return EmailTemplate::where('key', $key)->first();
    }

    /**
     * Render template with data
     *
     * @param  array<string, string>  $data
     * @return array{subject: string, body: string}
     */
    public function render(string $key, array $data): array
    {
        $template = $this->getTemplate($key);

        if (! $template) {
            throw new \InvalidArgumentException("Email template not found: {$key}");
        }

        return [
            'subject' => $template->renderSubject($data),
            'body' => $template->renderBody($data),
        ];
    }

    /**
     * Send email using template
     *
     * @param  array<string, string>  $data
     */
    public function send(string $key, string $to, array $data): void
    {
        $template = $this->getTemplate($key);

        if (! $template) {
            Log::warning("Email template not found: {$key}");

            return;
        }

        if (! $template->is_active) {
            Log::info("Email template is inactive, skipping: {$key}");

            return;
        }

        $rendered = $this->render($key, $data);

        try {
            Mail::to($to)->send(new TemplatedMail(
                template: $template,
                data: $data,
                renderedSubject: $rendered['subject'],
                renderedBody: $rendered['body'],
            ));
        } catch (\Exception $e) {
            Log::error("Failed to send templated email [{$key}]: ".$e->getMessage());
        }
    }

    /**
     * Send email using a definition instance (with context data built automatically)
     */
    public function sendFromDefinition(EmailTemplateDefinition $definition, string $to): void
    {
        $data = $definition->buildData();
        $data['url'] = $definition->getContextualUrl();

        $this->send($definition::getKey(), $to, $data);
    }

    /**
     * Send test email with sample data
     */
    public function sendTest(string $key, string $to): void
    {
        $template = $this->getTemplate($key);

        if (! $template) {
            throw new \InvalidArgumentException("Email template not found: {$key}");
        }

        $sampleData = $template->getSampleData();
        $sampleData['url'] = url('/');

        $rendered = [
            'subject' => '[TEST] '.$template->renderSubject($sampleData),
            'body' => $template->renderBody($sampleData),
        ];

        Mail::to($to)->send(new TemplatedMail(
            template: $template,
            data: $sampleData,
            renderedSubject: $rendered['subject'],
            renderedBody: $rendered['body'],
        ));
    }

    /**
     * Reset a template to its default subject and body from the definition
     */
    public function resetToDefault(EmailTemplate $template): bool
    {
        $defaultSubject = $template->getDefaultSubject();
        $defaultBody = $template->getDefaultBody();

        if (! $defaultSubject || ! $defaultBody) {
            return false;
        }

        $template->update([
            'subject' => $defaultSubject,
            'body' => $defaultBody,
        ]);

        return true;
    }

    /**
     * Get all available template definitions with their current database state
     *
     * @return Collection<int, array{class: class-string<EmailTemplateDefinition>, key: string, name: string, description: string, template: EmailTemplate|null}>
     */
    public function getAllTemplatesWithDefinitions(): Collection
    {
        $definitions = $this->discoverTemplates();

        return $definitions->map(function (string $class) {
            return [
                'class' => $class,
                'key' => $class::getKey(),
                'name' => $class::getName(),
                'description' => $class::getDescription(),
                'template' => $this->getTemplate($class::getKey()),
            ];
        });
    }
}
