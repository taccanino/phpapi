<?php

namespace utils;

class Route
{
    private string $regex;
    public string $path;
    public $callback;

    public function __construct(
        public string $method,
        string $path,
        callable $callback,
        public array $parameters = [],
        public array $body = [],
        public array $middlewares = []
    ) {
        $this->path = rtrim($path, '/');
        $this->regex = $this->createRegex();
        $this->callback = $callback;
    }

    // Method to create regex for matching the route
    private function createRegex(): string
    {
        // Handle required parameters in path
        $regex = preg_replace_callback('/{(\w+)}/', function ($matches) {
            $name = $matches[1];
            $parameterConfig = $this->parameters[$name] ?? [];

            // Use the provided regex pattern or default to matching non-slash characters
            $pattern = $parameterConfig['regex'] ?? '[^/]+';

            // Return the capturing group for the parameter
            return "(?P<{$name}>{$pattern})";
        }, $this->path);

        // Ensure the path does not have double slashes
        $regex = "#^" . rtrim($regex, '/') . "$#";

        return $regex;
    }

    // Method to handle and validate request body based on HTTP method
    private function handleRequestBody(): array
    {
        $body = getRequestBody();

        // Validate and convert body parameters according to configuration
        foreach ($this->body as $name => $config) {
            // Check if the required field is missing
            if (!array_key_exists($name, $body)) {
                throw new \Exception("Missing required field: $name", ErrorEnum::ROUTE_MISSING_REQUIRED_FIELD->value);
            }

            // Validate against regex if configured
            if (isset($config['regex']) && !preg_match("/" . $config['regex'] . "/", is_array($body[$name]) ? json_encode($body[$name]) : $body[$name])) {
                throw new \Exception("Invalid value for field: $name", ErrorEnum::ROUTE_INVALID_FIELD_VALUE->value);
            }

            // Convert the type if specified
            if (isset($config['type'])) {
                $body[$name] = $this->convertType($body[$name], $config['type']);
            }
        }

        // Remove fields that are not defined in the configuration
        return array_intersect_key($body, $this->body);
    }

    // Helper method for converting types based on configuration
    private function convertType(mixed $value, string $type): mixed
    {
        // Convert JSON string to an array/object
        if ($type === 'json') {
            if (!is_string($value))
                return $value;
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON format: " . json_last_error_msg(), ErrorEnum::ROUTE_INVALID_JSON_FORMAT->value);
            }
            return $decoded;
        }

        // Standard type conversion
        settype($value, $type);
        return $value;
    }

    // Method to handle the route when invoked
    public function __invoke(string $method, string $urlPath): mixed
    {
        // If the HTTP method does not match, return false
        if ($this->method !== $method) {
            return false;
        }

        // Match the URL path with the route's regex pattern
        if (!preg_match($this->regex, $urlPath, $data)) {
            return false;
        }

        // Remove the first element of the data array (the full match)
        array_shift($data);

        // Handle type conversion for matched data
        foreach ($this->parameters as $name => $config) {
            if (isset($data[$name]) && isset($config['type'])) {
                settype($data[$name], $config['type']);
            }
        }

        // Filter out numeric keys
        $data = array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);

        // Prepare data to pass to middlewares and callback
        $data = ['params' => $data];

        // Handle and validate the request body, adding it to the data array
        if (!empty($this->body)) {
            $data['body'] = $this->handleRequestBody();
        }

        // Pass data through middlewares
        $modifiedData = $data;
        foreach ($this->middlewares as $middleware) {
            $modifiedData = $middleware($modifiedData);
        }

        // Invoke the callback with the modified data
        return ($this->callback)($modifiedData);
    }
}

// Helper function to get the request body based on the HTTP method and content type
function getRequestBody(): array
{
    // Read the raw input data
    $rawInput = file_get_contents('php://input');

    // Detect the content type and parse accordingly
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Handle JSON input
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode($rawInput, true);

        // Check for JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format: ' . json_last_error_msg(), ErrorEnum::ROUTE_INVALID_JSON_FORMAT->value);
        }
        return $data ?? [];
    }

    return $_POST; // PHP automatically handles multipart form-data into $_POST
}
