<?php

namespace Danestves\LaravelPolar\Tests;

use Danestves\LaravelPolar\LaravelPolarServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use WithLaravelMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Danestves\\LaravelPolar\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );

        $this->app['view']->addNamespace('polar', __DIR__ . '/../resources/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPolarServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $app['config']->set('polar', require __DIR__ . '/../config/polar.php');

        foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
