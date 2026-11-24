<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    private const SUPPORTED = ['pt', 'en'];

    public function handle(Request $request, Closure $next)
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $candidates = [
            $request->header('X-Locale'),
            $request->user()?->locale,
            $this->fromAcceptLanguage($request->header('Accept-Language')),
        ];

        foreach ($candidates as $candidate) {
            $locale = $this->normalize($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $this->normalize(config('app.fallback_locale'))
            ?? $this->normalize(config('app.locale'))
            ?? self::SUPPORTED[0];
    }

    private function fromAcceptLanguage(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $candidates = [];

        foreach (explode(',', $header) as $index => $part) {
            $segments = array_map('trim', explode(';', $part));
            $tag = $segments[0] ?? '';
            $quality = 1.0;

            foreach (array_slice($segments, 1) as $parameter) {
                if (str_starts_with(strtolower($parameter), 'q=')) {
                    $quality = (float) substr($parameter, 2);
                }
            }

            $locale = $this->normalize($tag);

            if ($locale !== null && $quality > 0) {
                $candidates[] = ['locale' => $locale, 'quality' => $quality, 'index' => $index];
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b['quality'], $a['index']] <=> [$a['quality'], $b['index']]);

        return $candidates[0]['locale'] ?? null;
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $locale = strtolower(str_replace('_', '-', trim($value)));

        if ($locale === '') {
            return null;
        }

        $language = explode('-', $locale, 2)[0];

        return in_array($language, self::SUPPORTED, true) ? $language : null;
    }
}
