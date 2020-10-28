<?php

declare(strict_types=1);

namespace App\Producer\Driver;

use App\Support\GuzzleCreator;
use Exception;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Throwable;

class MqProxy extends DriverAbstract
{
    public const PRODUCE_URI_KAFKA = '/v1/kafka/send';

    public const PRODUCE_URI_BATCH_KAFKA = '/v1/kafka/send_batch';

    public const CREATE_TOPIC_URI_KAFKA = '/v1/kafka/create_topic';

    /**
     * @var string
     */
    protected $name = 'mqproxy';

    /**
     * Guzzle客户端.
     *
     * @var Client
     */
    protected $client;

    public function __construct(ContainerInterface $container, array $config = [])
    {
        parent::__construct($container, $config);

        mt_srand(time());
        $config['guzzle']['options']['base_uri'] = $config['proxy'][mt_rand(0, count($config['proxy']) - 1)];
        $this->client = GuzzleCreator::create($config['guzzle']);

        unset($config['guzzle']);
        $this->config = $config;
    }

    /**
     * 生产消息.
     */
    public function produce(string $payload)
    {
        $postData = [
            'queue' => $this->config['topic'],
            'payload' => $payload,
        ];

        $this->sendRequest(self::PRODUCE_URI_KAFKA, $postData);
    }

    /**
     * 批量生产消息.
     */
    public function batchProduce(array $payloads)
    {
        if ($this->config['batch_size']) {
            foreach (array_chunk($payloads, $this->config['batch_size']) as $chunk) {
                $this->batchProduceAll($chunk);
            }
        } else {
            $this->batchProduceAll($payloads);
        }
    }

    /**
     * Create kafka topic.
     *
     * @param string $name
     * @param int $partitions
     * @param int $replication
     * @param array $config
     * @return array
     */
    public function createTopic($name, $partitions, $replication, $config = null)
    {
        $postData = [
            'name' => $name,
            'partitions' => intval($partitions),
            'replication' => intval($replication),
        ];
        if (! is_null($config)) {
            $postData['config'] = $config;
        }

        return $this->sendRequest(self::CREATE_TOPIC_URI_KAFKA, $postData);
    }

    /**
     * 批量生产消息.
     */
    protected function batchProduceAll(array $payloads)
    {
        $messages = [];
        foreach ($payloads as $payload) {
            $messages[] = [
                'queue' => $this->config['topic'],
                'payload' => $payload,
            ];
        }
        $postData = [
            'messages' => $messages,
        ];
        $this->sendRequest(self::PRODUCE_URI_BATCH_KAFKA, $postData);
    }

    /**
     * 发送请求
     *
     * @return array
     */
    protected function sendRequest(string $uri, array $json)
    {
        try {
            $resp = $this->client->post(
                $uri,
                [
                    'json' => $json,
                    'headers' => $this->genGatewayHeaders(),
                ]
            );

            if (($statusCode = $resp->getStatusCode()) != 200) {
                throw new Exception('status code is ' . $statusCode);
            }

            $body = (string) $resp->getBody()->getContents();
            $json = json_decode($body, true);
            if (! is_array($json) || ! isset($json['code'])) {
                throw new Exception('invalid resp body: ' . $body);
            }

            if ($json['code'] != 0) {
                $msg = $json['msg'] ?? 'unknown';
                throw new Exception("occur error: {$msg}({$json['code']})");
            }

            return $json;
        } catch (Throwable $e) {
            // 因guzzle不支持重新设置base_uri，此处异常直接抛出
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * 生成网关认证Header.
     *
     * @return array
     */
    protected function genGatewayHeaders()
    {
        $timestamp = time();

        return [
            'X-Auth-Appid' => $this->config['appid'],
            'X-Auth-TimeStamp' => $timestamp,
            'X-Auth-Sign' => md5($this->config['appid'] . '&' . $timestamp . $this->config['appkey']),
        ];
    }
}
