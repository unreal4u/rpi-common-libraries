<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use unreal4u\rpiCommonLibrary\Communications\Contract;
use unreal4u\rpiCommonLibrary\Communications\MQTT\Operations;

abstract class Base extends Command implements JobContract
{
    use LockableTrait;

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
     * Handy to know in the logs with which instance we are having logs of
     * @var string
     */
    private $uniqueIdentifier;

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
        // This simpleName is also used to build the actual command name, maybe change this later on?
        parent::__construct($simpleName);
        $this->logger = new Logger($simpleName);
        $this->logger->pushHandler(
            new RotatingFileHandler($this->getLogDirectory() . 'logs/' . $simpleName . '.log', 14)
        );
        $this->uniqueIdentifier = uniqid('', true);
        // Assign the original class to the internal caller
        $this->internalName = $name;
    }

    /**
     * Returns the absolute path to the project directory
     *
     * @return string
     */
    private function getLogDirectory(): string
    {
        return substr(__DIR__, 0, strpos(__DIR__, 'vendor/'));
    }

    /**
     * Gets called each time the program exits normally
     */
    final public function __destruct()
    {
        // Let us know in the logs when and whether we have shut down gracefully
        $this->logger->info('++++ Terminating program ++++', [
            'internalName' => $this->internalName,
            'uniqueIdentifier' => $this->getUniqueIdentifier(),
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    final public function getUniqueIdentifier(): string
    {
        return $this->uniqueIdentifier;
    }

    /**
     * To be called before the job starts
     *
     * @return Base
     * @throws \Exception
     */
    private function initializeJob(): self
    {
        $this->logger->info('Trying to acquire lock', ['uniqueIdentifier' => $this->getUniqueIdentifier()]);
        if ($this->lock($this->internalName) === false) {
            $this->logger->info('Lock could not be acquired!', ['uniqueIdentifier' => $this->getUniqueIdentifier()]);
            die(1);
        }

        $this->jobStartedAt = new \DateTimeImmutable('now');
        $this->logger->info('++++ Initialized program ++++', [
            'internalName' => $this->internalName,
            'uniqueIdentifier' => $this->getUniqueIdentifier(),
            'startDate' => $this->jobStartedAt,
        ]);

        return $this;
    }

    /**
     * To be called after the job ends, can not be overwritten
     *
     * @return Base
     */
    private function finishJob(): self
    {
        $this->release();

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
