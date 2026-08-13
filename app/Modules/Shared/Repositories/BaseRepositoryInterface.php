<?php

namespace App\Modules\Shared\Repositories;

interface BaseRepositoryInterface
{
    public function all(): array;

    public function find(int $id): mixed;

    public function create(array $attributes): mixed;

    public function update(int $id, array $attributes): bool;

    public function delete(int $id): bool;
}
