<?php

namespace utils;

class Database
{
    public function __construct(private EnvLoader $envLoader, private ?ICache $cache = null) {}
}
