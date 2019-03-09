<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Functional;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;
use unreal4u\rpiCommonLibrary\Communications\Contract;
use unreal4u\rpiCommonLibrary\Communications\MQTT\Operations;
use unreal4u\rpiCommonLibrary\JobContract;

abstract class Base extends Command implements JobContract {
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Internal name used for logging, mqtt client, etc.
     * @var string
     */
    private $internalName;

    /**
     * @var Lock
     */
    private $lock;

    /**
     * @var \DateTimeImmutable
     */
    private $jobStartedAt;

    /**
     * Base constructor.
     */
    final public function __construct()
    {
        $name = get_class($this);
        $simpleName = substr(strrchr($name, '\\'), 1);
        parent::__construct($simpleName);
        $this->logger = new Logger($simpleName);
        $this->logger->pushHandler(new RotatingFileHandler('logs/' . $simpleName . '.log', 14));
        $this->logger->info('++++ Initialized program ++++', ['internalName' => $name, 'simpleName' => $simpleName]);

        // Assign the original class to the internal caller
        $this->internalName = $name;
    }

    final public function __destruct()
    {
        // Let us know in the logs when and whether we have shut down gracefully
        $this->logger->info('++++ Terminating program ++++', ['internalName' => $this->internalName]);
    }

    /**
     * To be called before the job starts
     *
     * @return Base
     * @throws \Exception
     */
    private function initializeJob(): self
    {
        $factory = new Factory(new FlockStore(sys_get_temp_dir()));
        $this->lock = $factory->createLock($this->internalName);
        $this->lock->acquire(true);
        $this->jobStartedAt = new \DateTimeImmutable('now');

        return $this;
    }

    /**
     * To be called after the job ends, can be overwritten
     *
     * @return Base
     */
    private function finishJob(): self
    {
        $this->lock->release();

        return $this;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return self
     * @throws \Exception
     */
    final public function execute(InputInterface $input, OutputInterface $output): self
    {
        $this->initializeJob();
        $this->setUp();
        $this->runJob();
        return $this->finishJob();
    }

    /**
     * For now, only MQTT is supported, other communication types will some day follow
     *
     * @param string $type
     * @return Contract
     */
    final public function communicationsFactory(string $type): Contract
    {
        if (strtoupper($type) === 'MQTT') {
            return new Operations($this->logger, $this->internalName);
        }

        // Other communication types will some day follow...
        throw new \LogicException('Invalid "type" passed on to factory (provided: "' . $type . '")');
    }
}
