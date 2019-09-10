<?php

namespace Laravel\Serverless\Kubeless\Queue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Laravel\Serverless\Kubeless\Queue\NatsQueue;
use Nats\Connection;
use Nats\ConnectionOptions;

class NatsConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $parsedServer = parse_url($config['server']);

        $error_reporting = error_reporting();
        error_reporting($error_reporting & !E_NOTICE);

        $connection   = new Connection(new ConnectionOptions([
            'host' => $parsedServer['host'] ?? 'localhost',
            'port' => $parsedServer['port'] ?? 4222,
            'user' => $parsedServer['user'] ?? null,
            'pass' => $parsedServer['pass'] ?? null,
            'reconnect' => true
        ]));

        $connection->setDebug($config['debug']);
        $connection->connect();
        error_reporting($error_reporting);

        return new NatsQueue($connection, $config['queue']);
    }
}
