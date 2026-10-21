<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Invoice;
use App\Models\Quote;
use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Renders an assistant reply (Markdown) as safe HTML where quote and invoice numbers,
 * project names and client names link to their page in the admin. Links the model
 * wrote itself are kept only when they point inside the application.
 */
class AssistantMarkup
{
    public static function render(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $targets = self::targets();

        $dom = new DOMDocument;
        // The XML declaration makes libxml read the fragment as UTF-8.
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="assistant-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//a') ?: []) as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $href = (string) $link->getAttribute('href');
            if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
                if (! str_starts_with($href, rtrim((string) config('app.url'), '/').'/')) {
                    $link->parentNode?->replaceChild($dom->createTextNode($link->textContent), $link);
                }
            }
        }

        if ($targets !== []) {
            $labels = array_keys($targets);
            usort($labels, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $pattern = '/(?<![\p{L}\p{N}])('.implode('|', array_map(fn (string $label): string => preg_quote($label, '/'), $labels)).')(?![\p{L}\p{N}])/iu';
            foreach (iterator_to_array($xpath->query('//text()[not(ancestor::a)]') ?: []) as $node) {
                if (! $node instanceof DOMText || ! preg_match($pattern, $node->data)) {
                    continue;
                }
                $fragment = $dom->createDocumentFragment();
                foreach (preg_split($pattern, $node->data, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $part) {
                    if ($index % 2 === 1 && isset($targets[mb_strtolower($part)])) {
                        $link = $dom->createElement('a');
                        $link->setAttribute('href', $targets[mb_strtolower($part)]);
                        $link->appendChild($dom->createTextNode($part));
                        $fragment->appendChild($link);
                    } elseif ($part !== '') {
                        $fragment->appendChild($dom->createTextNode($part));
                    }
                }
                $node->parentNode?->replaceChild($fragment, $node);
            }
        }

        $root = $dom->getElementById('assistant-root');
        $out = '';
        foreach ($root === null ? [] : $root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /** @return array<string, string> Computed once per request. */
    private static function targets(): array
    {
        return once(fn (): array => self::collectTargets());
    }

    /** @return array<string, string> */
    private static function collectTargets(): array
    {
        $targets = [];
        // Clients first, so a project named exactly like its client links to the project.
        foreach (Client::with(['projects' => fn ($q) => $q->whereNull('archived_at')->latest('id')])->latest('id')->limit(300)->get(['id', 'name']) as $client) {
            if (mb_strlen($client->name) >= 4 && ($project = $client->projects->first())) {
                $targets[mb_strtolower($client->name)] = route('admin.client-projects.show', ['clientProject' => $project->id]);
            }
        }
        foreach (ClientProject::whereNull('archived_at')->latest('id')->limit(300)->get(['id', 'name']) as $project) {
            if (mb_strlen($project->name) >= 4) {
                $targets[mb_strtolower($project->name)] = route('admin.client-projects.show', ['clientProject' => $project->id]);
            }
        }
        foreach (Quote::whereNotNull('number')->latest('id')->limit(500)->get(['id', 'number']) as $quote) {
            $targets[mb_strtolower((string) $quote->number)] = route('admin.billing.quotes.edit', ['id' => $quote->id]);
        }
        foreach (Invoice::whereNotNull('number')->latest('id')->limit(500)->get(['id', 'number']) as $invoice) {
            $targets[mb_strtolower((string) $invoice->number)] = route('admin.billing.invoices.edit', ['id' => $invoice->id]);
        }

        return $targets;
    }
}
