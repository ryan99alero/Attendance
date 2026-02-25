<?php

namespace App\Contracts;

interface EmailTemplateDefinition
{
    /**
     * Unique key like 'vacation.request.submitted'
     */
    public static function getKey(): string;

    /**
     * Display name like 'Vacation Request Submitted'
     */
    public static function getName(): string;

    /**
     * Description for admins explaining when this email is sent
     */
    public static function getDescription(): string;

    /**
     * Available variables with table.column naming
     *
     * @return array<string, array{label: string, example: string}>
     */
    public static function getAvailableVariables(): array;

    /**
     * Default subject line with {{variable}} placeholders
     */
    public static function getDefaultSubject(): string;

    /**
     * Default body content with {{variable}} placeholders
     */
    public static function getDefaultBody(): string;

    /**
     * Generate contextual URL for this email type
     */
    public function getContextualUrl(): string;

    /**
     * Get sample data for test email rendering
     *
     * @return array<string, string>
     */
    public static function getSampleData(): array;

    /**
     * Build the data array from the context objects
     *
     * @return array<string, string>
     */
    public function buildData(): array;
}
