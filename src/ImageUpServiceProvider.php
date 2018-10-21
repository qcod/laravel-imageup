<?php

namespace QCod\ImageUp;

use Illuminate\Support\ServiceProvider;

class ImageUpServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/imageup.php' => config_path('imageup.php')
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/../config/imageup.php',
            'imageup'
        );
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
    }
}
