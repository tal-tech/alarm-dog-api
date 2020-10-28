<?php

declare(strict_types=1);

namespace App\Command\IgnoreTask;

use App\Model\Config;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class IgnoreTaskList extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('ignore-task:list');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('List the configure of the self-monitor taskids');
        $this->addOption('id', 'I', InputOption::VALUE_OPTIONAL, '要查询的任务ID', null);
    }

    public function handle()
    {
        $id = (int) $this->input->getOption('id');

        $ids = Config::getItems(Config::KEY_IGNORE_SM_TASKIDS);
        sort($ids);

        if ($id !== 0) {
            if (in_array($id, $ids)) {
                $this->info(sprintf('id [%s] is in the taskids', $id));
            } else {
                $this->comment(sprintf('id [%s] is not in the taskids', $id));
            }
        }
        $this->line(sprintf('<info>all taskids is: <comment>%s</comment></info>', implode(', ', $ids)));
    }
}
