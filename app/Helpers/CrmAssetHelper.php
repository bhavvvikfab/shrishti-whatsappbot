<?php

if (! function_exists('crm_asset')) {
    /**
     * Build a stable public asset URL from APP_URL + PUBLIC_PATH.
     * Avoids /public/public/... when the PWA/home-screen opens under /public/.
     */
    function crm_asset(string $path, ?int $version = null): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $public = trim((string) env('PUBLIC_PATH', ''), '/');

        if ($public !== '' && str_starts_with($path, $public.'/')) {
            $path = substr($path, strlen($public) + 1);
        }

        $relative = $public !== '' ? $public.'/'.$path : $path;
        $url = rtrim((string) config('app.url'), '/').'/'.$relative;

        if ($version) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$version;
        }

        return $url;
    }
}
