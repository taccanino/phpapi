<?php

namespace dto;

class ExampleDTO
{
    public function __construct(public ExampleDTO2 $example2, public string $lastname) {}
}
