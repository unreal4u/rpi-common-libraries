<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Functional;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use unreal4u\rpiCommonLibrary\Communications\Contract;
use unreal4u\rpiCommonLibrary\Communications\MQTT\Operations;

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

    final public function __construct($name = null)
    {
        parent::__construct($name);
        $this->logger = new Logger($name);
        $this->logger->pushHandler(new RotatingFileHandler('logs/' . $name . '.log', 14));
        $this->internalName = $name;
    }

    /**
     * For now, only MQTT is supported
     *
     * @param string $type
     * @return Contract
     */
    final public function getCommunicationImplementation(string $type): Contract
    {
        switch ($type) {
            case 'MQTT':
                return new Operations($this->logger, $this->internalName);
            default:
                throw new \LogicException('Invalid "type" passed on to factory (provided: "' . $type . '")');
        }
    }
}
