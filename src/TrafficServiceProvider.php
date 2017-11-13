<?php


namespace Kodix\Traffic;


use Illuminate\Support\ServiceProvider;

class TrafficServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'traffic');
    }

    public function register()
    {
        $this->app->bind('kodix.traffic', function () {
            return new Manager(
                config('services.traffic.login'),
                config('services.traffic.password'),
                config('services.traffic.secret')
            );
        });
    }

    public function provides()
    {
        return ['kodix.traffic'];
    }
}