<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Communications;

use Psr\Log\LoggerInterface;

/**
 * Common interface for all external communications: whether be it mail, MQTT or Telegram Bot API
 */
interface Contract {
    /**
     * CommunicationsContract constructor.
     *
     * @param LoggerInterface $logger
     * @param string $internalName
     */
    public function __construct(LoggerInterface $logger, string $internalName);

    /**
     * Checks for prerequisites before being able to use this communication channel
     *
     * @return Contract
     */
    public function checkPrerequisites(): self;

    /**
     * Sending a message should always be possible given that we have a subject and a payload
     *
     * @param string $topicName Subject or topicname or something similar
     * @param string $payload The body of the message itself
     * @return bool Returns true on success, false otherwise
     */
    public function sendMessage(string $topicName, string $payload): bool;
}
