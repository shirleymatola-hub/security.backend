<?php

if (! function_exists('frontend_url')) {
    /**
     * URL absoluto de uma página do frontend React (ex.: links em convites).
     */
    function frontend_url(string $path = '/'): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/' . ltrim($path, '/');
    }
}
