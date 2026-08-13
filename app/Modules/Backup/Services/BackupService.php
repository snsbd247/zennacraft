<?php

namespace App\Modules\Backup\Services;

use App\Modules\Backup\Models\BackupLog;
use App\Modules\Backup\Models\BackupRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class BackupService
{
    protected string $disk = 'local';

    public function history(int $perPage = 15): LengthAwarePaginator
    {
        return BackupRun::with(['createdBy', 'logs'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function recentLogs(int $limit = 25)
    {
        return BackupLog::with('backupRun')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function summary(): array
    {
        $latest = BackupRun::latest('created_at')->first();

        return [
            'total' => BackupRun::count(),
            'completed' => BackupRun::where('status', 'completed')->count(),
            'failed' => BackupRun::where('status', 'failed')->count(),
            'restore_ready' => BackupRun::where('restore_ready', true)->count(),
            'latest' => $latest,
            'storage_used' => BackupRun::sum('total_size'),
        ];
    }

    public function createManualBackup(array $scopes = ['database', 'files']): BackupRun
    {
        $scopes = $this->normalizeScopes($scopes);

        $backup = BackupRun::create([
            'backup_type' => 'manual',
            'backup_scope' => $this->scopeLabel($scopes),
            'status' => 'running',
            'disk' => $this->disk,
            'created_by' => auth()->guard('staff')->id(),
            'started_at' => now(),
            'metadata' => [
                'connection' => config('database.default'),
                'driver' => DB::connection()->getDriverName(),
                'scopes' => $scopes,
            ],
        ]);

        $directory = now()->format('Y/m/d').'/backup-'.$backup->id.'-'.now()->format('His');
        $backup->update(['directory' => $directory]);

        $this->log($backup, 'info', 'Backup started.', ['scopes' => $scopes]);

        try {
            $artifacts = [];

            if (in_array('database', $scopes, true)) {
                $artifacts['database'] = $this->backupDatabase($backup, $directory);
            }

            if (in_array('files', $scopes, true)) {
                $artifacts['files'] = $this->backupFiles($backup, $directory);
            }

            $manifestPath = $this->writeManifest($backup, $directory, $scopes, $artifacts);
            $artifacts['manifest'] = $manifestPath;
            $checksum = $this->combinedChecksum($artifacts);
            $totalSize = $this->totalArtifactSize($artifacts);

            $backup->update([
                'database_path' => $artifacts['database'] ?? null,
                'files_path' => $artifacts['files'] ?? null,
                'manifest_path' => $manifestPath,
                'total_size' => $totalSize,
                'checksum' => $checksum,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->log($backup, 'info', 'Backup artifacts created.', [
                'total_size' => $totalSize,
                'checksum' => $checksum,
            ]);

            return $this->validateBackup($backup->refresh());
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'validation_status' => 'failed',
                'restore_ready' => false,
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            $this->log($backup, 'error', 'Backup failed.', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function validateBackup(BackupRun $backup): BackupRun
    {
        $messages = [];
        $valid = $backup->status === 'completed';

        if (! $valid) {
            $messages[] = 'Backup is not completed.';
        }

        foreach ($this->expectedArtifacts($backup) as $label => $path) {
            if (! $path || ! Storage::disk($backup->disk)->exists($path)) {
                $valid = false;
                $messages[] = ucfirst($label).' artifact is missing.';
                continue;
            }

            if (Storage::disk($backup->disk)->size($path) <= 0) {
                $valid = false;
                $messages[] = ucfirst($label).' artifact is empty.';
            }
        }

        if ($backup->files_path && ! $this->zipIsReadable(Storage::disk($backup->disk)->path($backup->files_path))) {
            $valid = false;
            $messages[] = 'Media zip archive is not readable.';
        }

        if ($backup->manifest_path) {
            $manifest = json_decode((string) Storage::disk($backup->disk)->get($backup->manifest_path), true);

            if (! is_array($manifest)) {
                $valid = false;
                $messages[] = 'Manifest JSON is invalid.';
            }
        }

        $currentChecksum = $this->combinedChecksum($this->expectedArtifacts($backup));

        if ($backup->checksum && $currentChecksum !== $backup->checksum) {
            $valid = false;
            $messages[] = 'Artifact checksum does not match backup history.';
        }

        if ($messages === []) {
            $messages[] = 'Backup artifacts validated successfully.';
        }

        $backup->update([
            'validation_status' => $valid ? 'passed' : 'failed',
            'validation_message' => implode(' ', $messages),
            'restore_ready' => $valid,
        ]);

        $this->log($backup, $valid ? 'info' : 'warning', 'Backup validation completed.', [
            'validation_status' => $valid ? 'passed' : 'failed',
            'restore_ready' => $valid,
        ]);

        return $backup->refresh();
    }

    public function healthChecks(): array
    {
        $latest = BackupRun::latest('created_at')->first();

        return [
            $this->check(
                'Backup Storage Writable',
                $this->backupStorageWritable(),
                $this->backupStorageWritable() ? 'Writable' : 'Not writable',
                'Backups are stored on the private local disk.'
            ),
            $this->check(
                'Database Reachable',
                $this->databaseReachable(),
                $this->databaseReachable() ? DB::connection()->getDriverName() : 'Unavailable',
                'Database backup requires a reachable configured database connection.'
            ),
            $this->check(
                'Zip Extension',
                class_exists(ZipArchive::class),
                class_exists(ZipArchive::class) ? 'Available' : 'Missing',
                'Media/file backups use ZipArchive.'
            ),
            $this->check(
                'Public Media Path',
                is_dir(storage_path('app/public')),
                storage_path('app/public'),
                'Public media disk is the source for file backups.'
            ),
            $this->check(
                'Latest Backup',
                (bool) $latest,
                $latest ? $latest->created_at->format('Y-m-d H:i:s').' / '.$latest->status : 'No backups yet',
                'At least one validated backup should exist before production launch.'
            ),
            $this->check(
                'Restore Readiness',
                (bool) ($latest?->restore_ready),
                $latest?->restore_ready ? 'Ready' : 'Not ready',
                'Restore readiness requires completed artifacts and successful validation.'
            ),
        ];
    }

    public function restoreReadiness(BackupRun $backup): array
    {
        return [
            'database_artifact' => $backup->database_path && Storage::disk($backup->disk)->exists($backup->database_path),
            'files_artifact' => $backup->files_path && Storage::disk($backup->disk)->exists($backup->files_path),
            'manifest_artifact' => $backup->manifest_path && Storage::disk($backup->disk)->exists($backup->manifest_path),
            'validation_status' => $backup->validation_status,
            'restore_ready' => $backup->restore_ready,
        ];
    }

    protected function backupDatabase(BackupRun $backup, string $directory): string
    {
        $driver = DB::connection()->getDriverName();
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $path = $directory.'/database.'.$extension;
        $absolutePath = Storage::disk($this->disk)->path($path);
        $this->ensureDirectory(dirname($absolutePath));

        if ($driver === 'sqlite') {
            $database = (string) config('database.connections.'.config('database.default').'.database');

            if ($database === ':memory:' || ! is_file($database)) {
                throw new \RuntimeException('SQLite database file is not available for backup.');
            }

            if (! copy($database, $absolutePath)) {
                throw new \RuntimeException('Unable to copy SQLite database backup.');
            }

            $this->log($backup, 'info', 'SQLite database copied.', ['path' => $path]);

            return $path;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Database backup currently supports sqlite, mysql, and mariadb connections.');
        }

        file_put_contents($absolutePath, $this->mysqlDump());
        $this->log($backup, 'info', 'SQL database dump created.', ['path' => $path]);

        return $path;
    }

    protected function backupFiles(BackupRun $backup, string $directory): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for media backup.');
        }

        $path = $directory.'/media.zip';
        $absolutePath = Storage::disk($this->disk)->path($path);
        $this->ensureDirectory(dirname($absolutePath));

        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create media backup archive.');
        }

        $source = storage_path('app/public');
        $fileCount = 0;

        if (is_dir($source)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relative = 'public/'.str_replace('\\', '/', Str::after($file->getPathname(), $source.DIRECTORY_SEPARATOR));
                $zip->addFile($file->getPathname(), $relative);
                $fileCount++;
            }
        }

        $zip->addFromString('backup-media-manifest.json', json_encode([
            'source' => 'storage/app/public',
            'file_count' => $fileCount,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $zip->close();
        $this->log($backup, 'info', 'Media files archived.', ['path' => $path, 'file_count' => $fileCount]);

        return $path;
    }

    protected function writeManifest(BackupRun $backup, string $directory, array $scopes, array $artifacts): string
    {
        $path = $directory.'/manifest.json';
        $manifest = [
            'application' => config('app.name', 'Zenna Craft'),
            'environment' => app()->environment(),
            'backup_id' => $backup->id,
            'backup_type' => $backup->backup_type,
            'backup_scope' => $backup->backup_scope,
            'scopes' => $scopes,
            'database_driver' => DB::connection()->getDriverName(),
            'created_at' => now()->toIso8601String(),
            'artifacts' => collect($artifacts)->mapWithKeys(fn (string $artifactPath, string $key) => [
                $key => [
                    'path' => $artifactPath,
                    'size' => Storage::disk($this->disk)->exists($artifactPath) ? Storage::disk($this->disk)->size($artifactPath) : 0,
                    'sha256' => Storage::disk($this->disk)->exists($artifactPath) ? hash_file('sha256', Storage::disk($this->disk)->path($artifactPath)) : null,
                ],
            ])->toArray(),
        ];

        Storage::disk($this->disk)->put($path, json_encode($manifest, JSON_PRETTY_PRINT));
        $this->log($backup, 'info', 'Backup manifest written.', ['path' => $path]);

        return $path;
    }

    protected function mysqlDump(): string
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $database = $connection->getDatabaseName();
        $dump = "-- Zenna Craft database backup\n";
        $dump .= "-- Generated at ".now()->toDateTimeString()."\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        $tables = collect($connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(fn (object $row) => array_values((array) $row)[0] ?? null)
            ->filter()
            ->values();

        foreach ($tables as $table) {
            $createRow = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
            $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;

            if (! $createSql) {
                continue;
            }

            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump .= $createSql.";\n\n";

            foreach ($connection->table($table)->cursor() as $row) {
                $values = collect((array) $row)->map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    if (is_bool($value)) {
                        return $value ? '1' : '0';
                    }

                    return $pdo->quote((string) $value);
                })->implode(', ');

                $columns = collect((array) $row)
                    ->keys()
                    ->map(fn (string $column) => '`'.$column.'`')
                    ->implode(', ');

                $dump .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }

            $dump .= "\n";
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $dump;
    }

    protected function normalizeScopes(array $scopes): array
    {
        $scopes = collect($scopes)
            ->filter(fn ($scope) => in_array($scope, ['database', 'files'], true))
            ->unique()
            ->values()
            ->all();

        return $scopes === [] ? ['database', 'files'] : $scopes;
    }

    protected function scopeLabel(array $scopes): string
    {
        if (count($scopes) === 2) {
            return 'full';
        }

        return $scopes[0] ?? 'full';
    }

    protected function expectedArtifacts(BackupRun $backup): array
    {
        return array_filter([
            'database' => $backup->database_path,
            'files' => $backup->files_path,
            'manifest' => $backup->manifest_path,
        ]);
    }

    protected function combinedChecksum(array $artifacts): string
    {
        $hashes = [];

        foreach (array_filter($artifacts) as $artifactPath) {
            if (! Storage::disk($this->disk)->exists($artifactPath)) {
                continue;
            }

            $hashes[] = hash_file('sha256', Storage::disk($this->disk)->path($artifactPath));
        }

        return hash('sha256', implode('|', $hashes));
    }

    protected function totalArtifactSize(array $artifacts): int
    {
        return collect($artifacts)
            ->filter(fn (?string $path) => $path && Storage::disk($this->disk)->exists($path))
            ->sum(fn (string $path) => Storage::disk($this->disk)->size($path));
    }

    protected function zipIsReadable(string $absolutePath): bool
    {
        if (! class_exists(ZipArchive::class) || ! is_file($absolutePath)) {
            return false;
        }

        $zip = new ZipArchive();
        $opened = $zip->open($absolutePath) === true;

        if ($opened) {
            $zip->close();
        }

        return $opened;
    }

    protected function backupStorageWritable(): bool
    {
        try {
            $path = 'backups/.health-'.Str::random(8);
            Storage::disk($this->disk)->put($path, 'ok');
            $healthy = Storage::disk($this->disk)->get($path) === 'ok';
            Storage::disk($this->disk)->delete($path);

            return $healthy;
        } catch (Throwable) {
            return false;
        }
    }

    protected function databaseReachable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function check(string $label, bool $passes, string $value, string $details): array
    {
        return [
            'label' => $label,
            'status' => $passes ? 'pass' : 'warning',
            'value' => $value,
            'details' => $details,
        ];
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new \RuntimeException('Unable to create backup directory.');
        }
    }

    protected function log(BackupRun $backup, string $level, string $message, array $context = []): void
    {
        BackupLog::create([
            'backup_run_id' => $backup->id,
            'level' => $level,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }
}
