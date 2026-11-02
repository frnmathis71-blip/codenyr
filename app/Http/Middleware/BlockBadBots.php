<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses AI training crawlers and scraping tools outright: robots.txt asks them to stay away,
 * but several ignore it. AI search assistants (ChatGPT search, Perplexity…) stay allowed,
 * since they bring visitors.
 */
class BlockBadBots
{
    public const AI_TRAINING_BOTS = ['GPTBot', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'Bytespider', 'meta-externalagent', 'FacebookBot', 'Diffbot', 'omgili', 'ImagesiftBot', 'cohere-ai', 'cohere-training-data-crawler', 'Timpibot', 'PanguBot', 'AI2Bot', 'Kangaroo Bot', 'img2dataset', 'Scrapy'];

    private const SCRAPERS = ['python-requests', 'aiohttp', 'Go-http-client', 'HeadlessChrome', 'PhantomJS', 'libwww-perl', 'zgrab', 'masscan', 'Nikto', 'sqlmap'];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up', 'robots.txt')) {
            return $next($request);
        }
        $agent = (string) $request->userAgent();
        if ($agent === '' || str_contains($agent, '<') || $this->matches($agent, [...self::AI_TRAINING_BOTS, ...self::SCRAPERS])) {
            abort(403);
        }

        return $next($request);
    }

    /** @param array<int, string> $needles */
    private function matches(string $agent, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (stripos($agent, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
