<?php

namespace App\Services;

class DocumentVariables
{
    /** @param array<string, mixed> $context
     * @param  array<string, string>  $errors
     */
    public function resolve(string $text, array $context, array &$errors): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/u', function (array $match) use ($context, &$errors): string {
            $aliases = ['seller' => 'company.legal_name', 'client' => 'client.name', 'project' => 'project.name', 'amount' => 'quote.total', 'delay' => 'quote.estimated_delay', 'payment' => 'quote.conditions', 'services' => 'scope'];
            $path = $aliases[$match[1]] ?? $match[1];
            $value = data_get($context, $path);
            if (! is_scalar($value) || trim((string) $value) === '') {
                $errors['variable_'.str_replace('.', '_', $path)] = 'Variable absente ou inconnue : '.$match[1].'. Complétez sa source ou corrigez le modèle.';

                return $match[0];
            }

            return (string) $value;
        }, $text) ?? $text;
    }
}
