<?php

declare(strict_types=1);

namespace App\Producer\Driver;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class DriverAbstract
{
    /**
     * @var string
     */
    protected $name = __CLASS__;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->container = $container;
        $this->formatter = $container->get(FormatterInterface::class);
        $this->logger = $container->get(LoggerFactory::class)->get($this->getName());
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 生产消息.
     */
    abstract public function produce(string $payload);

    /**
     * 批量生产消息.
     */
    abstract public function batchProduce(array $payloads);

    /**
     * 停止操作
     * 对于需要信号处理的场景进行处理.
     */
    public function stop()
    {
    }

    public function pack($data)
    {
        return json_encode($data);
    }

    public function unpack($data)
    {
        return json_decode($data, true) ?? null;
    }
}
