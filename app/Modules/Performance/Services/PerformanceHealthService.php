<?php

namespace App\Modules\Performance\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PerformanceHealthService
{
    public function all(): array
    {
        return [
            'cache' => $this->cache(),
            'database' => $this->database(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
        ];
    }

    public function cache(): array
    {
        try {
            $key = 'performance.health.cache';
            Cache::put($key, 'ok', 30);

            return [
                'status' => Cache::get($key) === 'ok' ? 'ok' : 'failed',
                'driver' => config('cache.default'),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'driver' => config('cache.default'),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function database(): array
    {
        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'connection' => config('database.default'),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function queue(): array
    {
        return [
            'status' => 'configured',
            'connection' => config('queue.default'),
            'failed_jobs_table' => config('queue.failed.table', 'failed_jobs'),
            'default_queue' => config('queue.connections.'.config('queue.default').'.queue', 'default'),
        ];
    }

    public function storage(): array
    {
        try {
            $disk = config('filesystems.default', 'local');
            $path = 'performance-health/probe.txt';
            Storage::disk($disk)->put($path, 'ok');
            $healthy = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);

            return [
                'status' => $healthy ? 'ok' : 'failed',
                'disk' => $disk,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'disk' => config('filesystems.default', 'local'),
                'error' => $exception->getMessage(),
            ];
        }
    }
}
