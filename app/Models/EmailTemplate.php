<?php

namespace App\Models;

use App\Contracts\EmailTemplateDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Render the subject with variable replacement
     *
     * @param  array<string, string>  $data
     */
    public function renderSubject(array $data): string
    {
        return $this->replaceVariables($this->subject, $data);
    }

    /**
     * Render the body with variable replacement
     *
     * @param  array<string, string>  $data
     */
    public function renderBody(array $data): string
    {
        $body = $this->body;

        // Handle conditional blocks {{#variable}}...{{/variable}}
        $body = preg_replace_callback(
            '/\{\{#([^}]+)\}\}(.*?)\{\{\/\1\}\}/s',
            function ($matches) use ($data) {
                $variable = $matches[1];
                $content = $matches[2];

                // Only show content if variable exists and is not empty
                if (isset($data[$variable]) && ! empty($data[$variable])) {
                    return $this->replaceVariables($content, $data);
                }

                return '';
            },
            $body
        );

        return $this->replaceVariables($body, $data);
    }

    /**
     * Replace {{variable}} placeholders with values
     *
     * @param  array<string, string>  $data
     */
    protected function replaceVariables(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace("{{{$key}}}", (string) $value, $text);
        }

        // Remove any unreplaced variables
        $text = preg_replace('/\{\{[^}]+\}\}/', '', $text);

        return $text;
    }

    /**
     * Get the definition class for this template
     */
    public function getDefinitionClass(): ?string
    {
        $templateClasses = $this->discoverTemplateClasses();

        foreach ($templateClasses as $class) {
            if ($class::getKey() === $this->key) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Discover all template definition classes
     *
     * @return array<class-string<EmailTemplateDefinition>>
     */
    protected function discoverTemplateClasses(): array
    {
        $classes = [];
        $path = app_path('Mail/Templates');

        if (! is_dir($path)) {
            return $classes;
        }

        foreach (glob("{$path}/*.php") as $file) {
            $className = 'App\\Mail\\Templates\\'.pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($className) && in_array(EmailTemplateDefinition::class, class_implements($className))) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * Get available variables for this template from its definition
     *
     * @return array<string, array{label: string, example: string}>
     */
    public function getAvailableVariables(): array
    {
        $class = $this->getDefinitionClass();

        if ($class) {
            return $class::getAvailableVariables();
        }

        return [];
    }

    /**
     * Get sample data for test emails
     *
     * @return array<string, string>
     */
    public function getSampleData(): array
    {
        $class = $this->getDefinitionClass();

        if ($class) {
            return $class::getSampleData();
        }

        return [];
    }

    /**
     * Get the description from the definition
     */
    public function getDescription(): ?string
    {
        $class = $this->getDefinitionClass();

        if ($class) {
            return $class::getDescription();
        }

        return null;
    }

    /**
     * Get default subject from definition
     */
    public function getDefaultSubject(): ?string
    {
        $class = $this->getDefinitionClass();

        if ($class) {
            return $class::getDefaultSubject();
        }

        return null;
    }

    /**
     * Get default body from definition
     */
    public function getDefaultBody(): ?string
    {
        $class = $this->getDefinitionClass();

        if ($class) {
            return $class::getDefaultBody();
        }

        return null;
    }
}
