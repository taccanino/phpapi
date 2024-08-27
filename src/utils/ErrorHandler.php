<?php

namespace utils;

class ErrorHandler
{
    private bool $exceptionHandled = false;
    private const GENERIC_ERROR = "An error occurred. Please try again later.";

    public function __construct(private EnvLoader $envLoader, private Logger $logger)
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);

        // Set custom error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);

        // Register shutdown function to handle fatal errors
        register_shutdown_function([$this, 'shutdownFunction']);
    }

    public function handleError(int $severity, string $message, ?string $file, ?int $line)
    {
        // Convert errors to ErrorException to handle them like exceptions
        if (!(error_reporting() & $severity))
            return; // Error severity not in error reporting level

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(?\Throwable $exception)
    {
        if ($exception === null)
            return;

        if ($this->exceptionHandled)
            return; // Avoid logging again if an exception has already been handled

        $this->exceptionHandled = true; // Set the flag to indicate that an exception has been handled

        // Log the exception details
        $this->logException($exception);
        http_response_code(500);

        // Display a user-friendly message if in production
        if ($this->isDevelopment())
            echo ErrorHandler::formatErrorOutput(sprintf(
                "Uncaught %s - \"%s\" in %s on line %d - %s",
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            ));
        else
            echo ErrorHandler::formatErrorOutput(ErrorHandler::GENERIC_ERROR);

        // Terminate the script
        exit(1);
    }

    private function logException(\Throwable $exception)
    {
        $logMessage = sprintf(
            "%s: %s in %s on line %d\nStack trace:\n%s\n\n",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        $this->logger->log($logMessage);
    }

    public function shutdownFunction()
    {
        // Only process if no exception has been handled yet
        if ($this->exceptionHandled)
            return;

        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]))
            return;

        $this->logException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));

        http_response_code(500);

        // Display a user-friendly message if in production
        if ($this->isDevelopment())
            echo ErrorHandler::formatErrorOutput(sprintf(
                "Fatal error: %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            ));
        else
            echo ErrorHandler::formatErrorOutput(ErrorHandler::GENERIC_ERROR);
    }

    private function isDevelopment()
    {
        return $this->envLoader->get('APP_ENV') === 'development';
    }

    private static function formatErrorOutput(string $error): string
    {
        return json_encode(['error' => $error]);
    }
}
