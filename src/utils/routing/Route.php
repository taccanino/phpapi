<?php

namespace utils\routing;

use utils\errors\ErrorEnum;

class Route
{
    private $callback;
    public function __construct(
        private string $method,
        private string $pathTemplate,
        callable $callback,
        private array $middlewares = [],
        private array $pathParamsTemplate = [],
        private array $queryParamsTemplate = [],
        private array $bodyParamsTemplate = [],
        private array $headerParamsTemplate = [],
        private array $cookieParamsTemplate = [],
    ) {
        $this->callback = $callback;

        //remove trailing slash
        if (substr($this->pathTemplate, -1) === '/')
            $this->pathTemplate = substr($this->pathTemplate, 0, -1);
    }

    public function __invoke(string $method, string $path, array $queryParams, array $headerParams, array $cookieParams, array $bodyParams): mixed
    {
        if (!$this->matchMethod($method))
            throw new \Exception('Wrong method', ErrorEnum::ROUTE_WRONG_METHOD->value);

        if (!$this->matchPath($path))
            throw new \Exception('Wrong path', ErrorEnum::ROUTE_WRONG_PATH->value);

        $pathParams = $this->extractPathParams($path);

        $builtPathParams = $this->buildParams($pathParams, $this->pathParamsTemplate);
        $builtQueryParams = $this->buildParams($queryParams, $this->queryParamsTemplate);
        $builtHeaderParams = $this->buildParams($headerParams, $this->headerParamsTemplate);
        $builtCookieParams = $this->buildParams($cookieParams, $this->cookieParamsTemplate);
        $builtBodyParams = $this->buildParams($bodyParams, $this->bodyParamsTemplate);

        $builtParams = [
            'path' => $builtPathParams,
            'query' => $builtQueryParams,
            'header' => $builtHeaderParams,
            'cookie' => $builtCookieParams,
            'body' => $builtBodyParams
        ];

        foreach ($this->middlewares as $middleware) {
            $builtParams = $middleware($builtParams);
        }

        return ($this->callback)($builtParams);
    }

    private function matchMethod($method): bool
    {
        return $this->method === $method;
    }

    private function matchPath($path): bool
    {
        $pattern = preg_replace('/\{\w+\}/', '([^/]+)', $this->pathTemplate);

        // Add delimiters and make sure the entire string is matched
        $pattern = '#^' . $pattern . '$#';

        // Check if the actual path matches the pattern
        return preg_match($pattern, $path) === 1;
    }

    private function extractPathParams($path): array
    {
        $pathParams = [];
        $pathParts = explode('/', $path);
        $pathTemplateParts = explode('/', $this->pathTemplate);

        for ($i = 0; $i < count($pathParts); $i++) {
            if (strpos($pathTemplateParts[$i], '{') === 0) {
                $pathParams[substr($pathTemplateParts[$i], 1, -1)] = $pathParts[$i];
            }
        }

        return $pathParams;
    }

    private function buildParams(array $params, array $paramsTemplate): array
    {
        /**
         * paramsTemplate is an associative array of string values.
         * Precisely these string values are the scalar type (int, float, string, bool, array) or the fully qualified name of a class.
         * The keys are the names of the parameters.
         * In the associative array params, the keys are the names of the parameters and the values are the actual values.
         * For types int, float, string, bool, the actual value must be of the same type.
         * For type array, and class, it must be json decoded and this must be done recursively for nested arrays/classes.
         * This function returns an associative array of the same structure as paramsTemplate, but with the actual values.
         */
        $builtParams = [];

        foreach ($paramsTemplate as $paramName => $paramType) {
            if (!array_key_exists($paramName, $params))
                throw new \Exception('Missing parameter', ErrorEnum::ROUTE_MISSING_PARAM->value);

            if (is_array($params[$paramName]))
                $params[$paramName] = json_encode($params[$paramName]);

            $builtParams[$paramName] = $this->buildParam($params[$paramName], $paramType);
        }

        return $builtParams;
    }

    private function buildParam(string $param, string $paramType): mixed
    {
        if ($paramType === 'int') {
            if (preg_match('/^\d+$/', $param) !== 1)
                throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
            return (int) $param;
        }

        if ($paramType === 'float') {
            if (preg_match('/^\d+(\.\d+)?$/', $param) !== 1)
                throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
            return (float) $param;
        }

        if ($paramType === 'string') {
            return $param;
        }

        if ($paramType === 'bool') {
            if ($param === 'true' || $param === '1')
                return true;
            if ($param === 'false' || $param === '0')
                return false;
            throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
        }

        if ($paramType === 'array') {
            return json_decode($param, true);
        }

        if (class_exists($paramType)) {
            //check if the class has a constructor with the same parameters as the json decoded array and build it
            $reflection = new \ReflectionClass($paramType);
            $constructor = $reflection->getConstructor();
            if ($constructor === null)
                throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
            $parameters = $constructor->getParameters();
            $builtParams = [];

            $param = json_decode($param, true);

            foreach ($parameters as $parameter) {
                //if the parameter has a default value it means it is optional
                $classParamType = $parameter->getType();
                if ($classParamType === null)
                    throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
                if (!array_key_exists($parameter->getName(), $param) && !$parameter->isOptional())
                    throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);

                $parameterToPass = $param[$parameter->getName()];
                if ($classParamType->getName() !== 'string')
                    $parameterToPass = json_encode($parameterToPass);

                $builtParams[$parameter->getName()] = $this->buildParam($parameterToPass, $classParamType->getName());
            }

            $object = $reflection->newInstanceArgs($builtParams);
            if ($object === null)
                throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
            return $object;
        }

        throw new \Exception('Wrong type', ErrorEnum::ROUTE_WRONG_PARAM_TYPE->value);
    }
}
