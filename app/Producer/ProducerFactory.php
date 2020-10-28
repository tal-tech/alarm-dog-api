<?php

declare(strict_types=1);

namespace App\Producer;

use App\Exception\AppException;
use App\Producer\Driver\DriverAbstract;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ProducerFactory
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var DriverAbstract[]
     */
    protected $drivers;

    /**
     * @var array
     */
    protected $config;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->config = $this->container->get(ConfigInterface::class)->get('producer');

        $this->initDefaultDriver();
    }

    /**
     * 获取生产者.
     *
     * @return DriverAbstract
     */
    public function get(string $name = 'default')
    {
        if (! array_key_exists($name, $this->drivers)) {
            return $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * 所有生产者.
     *
     * @return DriverAbstract[]
     */
    public function gets()
    {
        $drivers = $this->drivers;
        unset($drivers['default']);

        return $drivers;
    }

    /**
     * 初始化默认driver.
     *
     * @return DriverAbstract
     */
    protected function initDefaultDriver()
    {
        return $this->createDriver();
    }

    /**
     * @param string $name
     * @return DriverAbstract
     */
    protected function createDriver(string $name = null)
    {
        $driverName = $name ?? $this->config['default_driver'];

        if (! array_key_exists($driverName, $this->config['drivers'])) {
            throw new AppException(sprintf('Producer driver %s not found', $driverName));
        }

        $driverConfig = $this->config['drivers'][$driverName];
        $driver = make($driverConfig['class'], [
            'config' => $driverConfig['config'],
        ]);

        $this->drivers[$driverName] = $driver;
        if (is_null($name)) {
            $this->drivers['default'] = $driver;
        }

        return $driver;
    }
}
