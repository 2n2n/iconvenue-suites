<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\ImageHelper;

class ImageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('image-helper', function () {
            return new ImageHelper();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register global helper functions only if they don't exist
        if (!function_exists(__NAMESPACE__.'\\image_url')) {
            /**
             * Get image URL with fallback
             */
            function image_url(?string $path, ?string $fallback = null): string
            {
                return \App\Helpers\ImageHelper::getImageUrl($path, $fallback);
            }
        }

        if (!function_exists(__NAMESPACE__.'\\image_urls')) {
            /**
             * Get multiple image URLs
             */
            function image_urls(?array $paths, ?string $fallback = null): array
            {
                return \App\Helpers\ImageHelper::getImageUrls($paths, $fallback);
            }
        }

        if (!function_exists(__NAMESPACE__.'\\image_exists')) {
            /**
             * Check if image exists
             */
            function image_exists(?string $path): bool
            {
                return \App\Helpers\ImageHelper::imageExists($path);
            }
        }
    }
}