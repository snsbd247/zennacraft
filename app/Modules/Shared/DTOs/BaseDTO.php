<?php

namespace App\Modules\Shared\DTOs;

abstract class BaseDTO
{
    public function __construct(public array $attributes = [])
    {
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
