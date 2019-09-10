<?php

namespace Laravel\Serverless\Kubeless;

use Illuminate\Support\ServiceProvider;

class ServerlessServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $manager = $this->app->make('queue');

        $manager->addConnector('nats', function () {
            return new Queue\Connectors\NatsConnector();
        });

        $manager->addConnector('kafka', function () {
            return new Queue\Connectors\KafkaConnector();
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/nats.php', 'queue.connections.nats'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/kafka.php', 'queue.connections.kafka'
        );

        $this->commands([
            Console\InstallCommand::class,
            Console\Runtime\BuildCommand::class,
            Console\Runtime\PushCommand::class,
        ]);
    }

    public function provides()
    {
        return [

        ];
    }
}
