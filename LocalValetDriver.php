<?php

/**
 * LocalValetDriver — Support for Clean URLs with Trailing Slashes
 * Only used for local development with Laravel Valet.
 */
class LocalValetDriver extends Valet\Drivers\ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return true;
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        if (file_exists($staticFilePath = $sitePath . '/' . $uri) && !is_dir($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        // Remove trailing slash for matching
        $uri = rtrim($uri, '/');

        // Allow /api/ routes to stay as .php if that's what's requested
        if (strpos($uri, '/api/') === 0 && file_exists($sitePath . $uri)) {
            return $sitePath . $uri;
        }

        // Check if a .php file exists for the URI (e.g. /iuran/ -> iuran.php)
        if (file_exists($sitePath . $uri . '.php')) {
            return $sitePath . $uri . '.php';
        }

        // Special handling for directories (like /admin/)
        if (is_dir($sitePath . $uri) && file_exists($sitePath . $uri . '/index.php')) {
            return $sitePath . $uri . '/index.php';
        }

        // Default to index.php for the home page or any unknown route
        return $sitePath . '/index.php';
    }
}
