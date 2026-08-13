<?php

namespace App\Modules\Shared\Actions;

abstract class BaseAction
{
    abstract public function execute(array $payload = []): mixed;
}
