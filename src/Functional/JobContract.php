<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary;

/**
 * Interface JobContract
 * @package unreal4u\rpiCommonLibrary
 */
interface JobContract {
    /**
     * Runs the actual job that needs to be executed
     *
     * @return bool Returns true if job was successful, false otherwise
     */
    public function runJob(): bool;

    /**
     * If method runJob returns false, this will return an array with errors that may have happened during execution
     *
     * @return array
     */
    public function retrieveErrors(): array;
}
