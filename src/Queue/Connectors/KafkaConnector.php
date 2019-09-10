<?php

namespace Laravel\Serverless\Kubeless\Queue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Facades\Log;

use Laravel\Serverless\Kubeless\Queue\KafkaQueue;

use Kafka\Producer;
use Kafka\ProducerConfig;
use Kafka\Consumer;
use Kafka\ConsumerConfig;


class KafkaConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new KafkaQueue(
            $this->makeProducer($config),
            $this->makeConsumer($config),
            $config['queue']
        );
    }

    private function makeProducer(array $config) : Producer
    {
        $producerConfig = ProducerConfig::getInstance();
        $producerConfig->setMetadataBrokerList(
            $config['brokers']
        );

        $producerConfig->setIsAsyn(false);
        $producerConfig->setRequiredAck(1);
        $producerConfig->setLogger(
            Log::getFacadeRoot()
        );
        return new Producer();
    }

    private function makeConsumer(array $config) : Consumer
    {
        $consumerConfig = ConsumerConfig::getInstance();
        $consumerConfig->setMetadataBrokerList(
            $config['brokers']
        );
        $consumerConfig->setGroupId(
            $config['consumer_group_id']
        );
        $consumerConfig->setTopics([
            $config['queue']
        ]);
        $consumerConfig->setLogger(
            Log::getFacadeRoot()
        );
        return new Consumer();
    }
}
