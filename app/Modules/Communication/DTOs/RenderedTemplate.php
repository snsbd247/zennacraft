<?php

namespace App\Modules\Communication\DTOs;

class RenderedTemplate
{
    public function __construct(
        public readonly string $key,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $variables = [],
    ) {}
}
