<?php

namespace Laravel\Serverless\Kubeless\Queue;

use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Nats\Connection;

class NatsQueue extends Queue implements QueueContract
{
    protected $nats;

    protected $default;

    public function __construct(Connection $connection, string $default)
    {
        $this->nats = $connection;
        $this->default = $default;
    }

    public function size($queue = null)
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        // todo: honour maxPayload
        $this->nats->publish($this->getQueue($queue), $payload);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        throw new \RuntimeException('Not Supported');
    }

    public function pop($queue = null)
    {
        throw new \RuntimeException('Not Implemented');
    }

    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'attempts' => 0,
        ]);
    }

    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }
}
