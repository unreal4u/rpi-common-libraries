<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Communications;

use Psr\Log\LoggerInterface;

abstract class Communications implements Contract
{
    protected $logger;

    protected $internalName;

    final public function __construct(LoggerInterface $logger, string $internalName)
    {
        $this->logger = $logger;
        $this->internalName = $internalName;

        $this->checkPrerequisites();
    }
}
