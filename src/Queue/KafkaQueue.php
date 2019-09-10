<?php

namespace Laravel\Serverless\Kubeless\Queue;


use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Kafka\Consumer;
use Kafka\Producer;


class KafkaQueue extends Queue implements QueueContract
{
    protected $producer;
    protected $consumer;

    /** @var string default topic name */
    protected $default;

    public function __construct(Producer $producer, Consumer $consumer, string $default)
    {
        $this->producer = $producer;
        $this->consumer = $consumer;

        $this->default = $default;
    }

    public function size($queue = null)
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->producer->send([[
            'topic' => $this->getQueue($queue),
            'value' => $payload
        ]]);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        throw new \RuntimeException('Not Implemented');
    }

    public function pop($queue = null)
    {
        throw new \RuntimeException('Not Implemented');
    }

    protected function getQueue($queue = null)
    {
        return $queue ?? $this->default;
    }
}
