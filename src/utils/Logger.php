<?php

namespace utils;

class Logger
{
    private string $logFile;

    public function __construct(EnvLoader $envLoader)
    {
        $this->logFile = $envLoader->get('LOG_FILE');
        if ($this->logFile === null)
            throw new \Exception('The LOG_FILE environment variable is not set', ErrorEnum::LOGGER_LOG_FILE_NOT_SET->value);

        ini_set('error_log', $this->logFile);
    }

    public function log(string $message)
    {
        $logMessage = sprintf(
            "[%s] %s",
            date('Y-m-d H:i:s'),
            $message
        );

        error_log($logMessage, 3, $this->logFile);
    }
}
