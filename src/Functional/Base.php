<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Functional;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;
use unreal4u\rpiCommonLibrary\Communications\Contract;
use unreal4u\rpiCommonLibrary\Communications\MQTT\Operations;
use unreal4u\rpiCommonLibrary\JobContract;

abstract class Base extends Command {
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
     * Base constructor.
     * @param string $name Must be the classname of the job we are instantiating
     */
    final public function __construct($name = null)
    {
        $simpleName = substr(strrchr($name, '\\'), 1);
        parent::__construct($simpleName);
        $this->logger = new Logger($simpleName);
        $this->logger->pushHandler(new RotatingFileHandler('logs/' . $simpleName . '.log', 14));
        // Assign the original class to the internal caller
        $this->internalName = $name;
    }

    /**
     * To be called before the job starts
     *
     * @return Base
     */
    private function initializeJob(): self
    {
        $factory = new Factory(new FlockStore(sys_get_temp_dir()));
        $this->lock = $factory->createLock($this->internalName);
        $this->lock->acquire(true);

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
     * @param JobContract $job
     * @return Base
     */
    final public function runJob(JobContract $job): self
    {
        $errors = [];
        $this->initializeJob();

        // Run the actual job
        try {
            $job->runJob();
        } catch (\Exception $e) {
            $errors = $e->getMessage();
        } finally {
            if ($errors === []) {
                $errors = $job->retrieveErrors();
            }

            if ($errors !== []) {
                $this->logger->error('Failed running job', $errors);
            }
        }

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
