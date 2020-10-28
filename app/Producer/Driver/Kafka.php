<?php

declare(strict_types=1);

namespace App\Producer\Driver;

use Psr\Container\ContainerInterface;
use RdKafka\Conf as RdKafkaConf;
use RdKafka\Producer as RdKafkaProducer;
use RdKafka\ProducerTopic as RdKafkaProducerTopic;
use Throwable;

class Kafka extends DriverAbstract
{
    /**
     * @var string
     */
    protected $name = 'kafka';

    /**
     * @var RdKafkaProducer
     */
    protected $rdkafkaProducer;

    /**
     * @var RdKafkaProducerTopic
     */
    protected $rdkafkaProducerTopic;

    public function __construct(ContainerInterface $container, array $config = [])
    {
        parent::__construct($container, $config);

        $conf = new RdKafkaConf();
        $this->rdkafkaProducer = new RdKafkaProducer($conf);
        $this->rdkafkaProducer->addBrokers($config['brokers']);
        $this->rdkafkaProducer->flush((int) $config['flush_timeout']);

        $this->rdkafkaProducerTopic = $this->rdkafkaProducer->newTopic($config['topic']);
    }

    /**
     * 生产消息.
     */
    public function produce(string $payload)
    {
        try {
            $this->rdkafkaProducerTopic->produce(\RD_KAFKA_PARTITION_UA, 0, $payload);
            $this->rdkafkaProducer->poll(0);
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量生产消息.
     */
    public function batchProduce(array $payloads)
    {
        try {
            foreach ($payloads as $payload) {
                $this->rdkafkaProducerTopic->produce(\RD_KAFKA_PARTITION_UA, 0, $payload);
                $this->rdkafkaProducer->poll(0);
            }
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * 停止.
     */
    public function stop()
    {
        $this->rdkafkaProducer->flush(-1);
    }
}
