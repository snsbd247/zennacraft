<?php

namespace App\Modules\Shared\Repositories;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function all(): array
    {
        return [];
    }

    public function find(int $id): mixed
    {
        return null;
    }

    public function create(array $attributes): mixed
    {
        return null;
    }

    public function update(int $id, array $attributes): bool
    {
        return false;
    }

    public function delete(int $id): bool
    {
        return false;
    }
}
