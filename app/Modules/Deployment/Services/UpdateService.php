<?php

namespace App\Modules\Deployment\Services;

use App\Modules\Backup\Services\BackupService;
use App\Modules\Deployment\Models\DeploymentLog;
use App\Modules\Deployment\Models\DeploymentRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Pulls the latest code from GitHub and applies it — the same steps that
 * were previously run by hand over SSH for every deploy in this project
 * (backup, git pull, composer install, migrate, cache rebuild), now
 * triggerable from Studio. Deliberately does NOT run `route:cache`: a
 * prior incident showed Route::defaults() combined with implicit model
 * binding gets silently corrupted under route caching, so production
 * stays on uncached routes on purpose.
 */
class UpdateService
{
    public function __construct(protected BackupService $backupService) {}

    public function isGitRepository(): bool
    {
        return is_dir(base_path('.git'));
    }

    public function currentCommit(): ?string
    {
        $result = $this->run(['git', 'rev-parse', '--short', 'HEAD']);

        return $result->successful() ? trim($result->output()) : null;
    }

    public function currentBranch(): string
    {
        $result = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $branch = $result->successful() ? trim($result->output()) : '';

        return $branch !== '' && $branch !== 'HEAD' ? $branch : 'main';
    }

    /**
     * Fast, read-only: fetches from the remote and reports how far behind
     * we are, without changing anything. Safe to call on every page load.
     *
     * @return array{checked: bool, ahead: int, commits: array<int, array{hash: string, message: string}>, error: ?string}
     */
    public function checkForUpdates(): array
    {
        if (! $this->isGitRepository()) {
            return ['checked' => false, 'ahead' => 0, 'commits' => [], 'error' => 'Not a git repository.'];
        }

        $branch = $this->currentBranch();
        $fetch = $this->run(['git', 'fetch', 'origin', $branch]);

        if (! $fetch->successful()) {
            return ['checked' => false, 'ahead' => 0, 'commits' => [], 'error' => trim($fetch->errorOutput()) ?: 'git fetch failed.'];
        }

        $countResult = $this->run(['git', 'rev-list', "HEAD..origin/{$branch}", '--count']);
        $ahead = $countResult->successful() ? (int) trim($countResult->output()) : 0;

        $commits = [];

        if ($ahead > 0) {
            $logResult = $this->run(['git', 'log', "HEAD..origin/{$branch}", '--pretty=format:%h|%s', '-20']);

            if ($logResult->successful()) {
                foreach (array_filter(explode("\n", trim($logResult->output()))) as $line) {
                    [$hash, $message] = array_pad(explode('|', $line, 2), 2, '');
                    $commits[] = ['hash' => $hash, 'message' => $message];
                }
            }
        }

        return ['checked' => true, 'ahead' => $ahead, 'commits' => $commits, 'error' => null];
    }

    public function history(int $perPage = 10): LengthAwarePaginator
    {
        return DeploymentRun::with(['createdBy', 'logs'])->latest('created_at')->paginate($perPage);
    }

    public function runUpdate(?int $staffId = null): DeploymentRun
    {
        $branch = $this->currentBranch();

        $deployment = DeploymentRun::create([
            'status' => 'running',
            'from_commit' => $this->currentCommit(),
            'created_by' => $staffId,
            'started_at' => now(),
        ]);

        try {
            // Local drift (e.g. a hosting-panel-edited .htaccess) must not
            // block a pull — stash it, pull, then best-effort restore it.
            $status = $this->run(['git', 'status', '--porcelain']);
            $dirty = trim($status->output()) !== '';

            if ($dirty) {
                $this->step($deployment, 'stash', 'info', 'Local changes detected — stashing before pull.');
                $this->runOrFail($deployment, 'stash', ['git', 'stash', 'push', '-u', '-m', 'auto-stash-before-deploy-'.$deployment->id]);
            }

            $this->step($deployment, 'pull', 'info', "Pulling origin/{$branch}...");
            $this->runOrFail($deployment, 'pull', ['git', 'pull', 'origin', $branch]);

            if ($dirty) {
                $popResult = $this->run(['git', 'stash', 'pop']);
                $this->step($deployment, 'stash', $popResult->successful() ? 'info' : 'warning', $popResult->successful()
                    ? 'Local changes restored.'
                    : 'Could not automatically restore local changes (conflict) — check manually: '.trim($popResult->errorOutput()));
            }

            $toCommit = $this->currentCommit();
            $commitsPulled = 0;

            if ($deployment->from_commit && $toCommit) {
                $countResult = $this->run(['git', 'rev-list', "{$deployment->from_commit}..{$toCommit}", '--count']);
                $commitsPulled = $countResult->successful() ? (int) trim($countResult->output()) : 0;
            }

            $deployment->update(['to_commit' => $toCommit, 'commits_pulled' => $commitsPulled]);

            if ($commitsPulled === 0) {
                $this->step($deployment, 'pull', 'info', 'Already up to date — nothing to deploy.');
                $deployment->update(['status' => 'completed', 'completed_at' => now()]);

                return $deployment->refresh();
            }

            $this->step($deployment, 'composer', 'info', 'Running composer install...');
            // Composer's own shebang resolves to whatever "php" means on
            // this host's PATH, which turned out to be an older CLI default
            // (8.2) than the app requires (8.3) — its platform_check then
            // refuses to install. Running it explicitly through PHP_BINARY
            // (the interpreter currently executing this app) sidesteps that
            // entirely, regardless of what the bare "php" resolves to.
            $composerResult = $this->run([PHP_BINARY, $this->composerBinary(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], timeout: 240);
            $deployment->update(['composer_ran' => $composerResult->successful()]);
            $this->step($deployment, 'composer', $composerResult->successful() ? 'info' : 'warning', $composerResult->successful()
                ? 'composer install completed.'
                : 'composer install had a problem: '.trim($composerResult->errorOutput()));

            $this->step($deployment, 'migrate', 'info', 'Backing up the database before migrating...');
            $this->backupService->createManualBackup(['database']);

            $this->step($deployment, 'migrate', 'info', 'Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $deployment->update(['migrations_ran' => true]);
            $this->step($deployment, 'migrate', 'info', trim(Artisan::output()) ?: 'No pending migrations.');

            $this->step($deployment, 'cache', 'info', 'Rebuilding caches...');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('config:cache');
            Artisan::call('view:cache');
            // route:cache is deliberately never run here — see class docblock.
            $this->step($deployment, 'cache', 'info', 'Caches rebuilt.');

            $deployment->update(['status' => 'completed', 'completed_at' => now()]);

            return $deployment->refresh();
        } catch (Throwable $exception) {
            $deployment->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);
            $this->step($deployment, 'fail', 'error', $exception->getMessage());

            throw $exception;
        }
    }

    protected function runOrFail(DeploymentRun $deployment, string $step, array $command, int $timeout = 60): void
    {
        $result = $this->run($command, $timeout);

        if (! $result->successful()) {
            $message = trim($result->errorOutput()) ?: trim($result->output()) ?: 'Command failed with no output.';
            $this->step($deployment, $step, 'error', $message);

            throw new \RuntimeException(ucfirst($step).' failed: '.$message);
        }

        $this->step($deployment, $step, 'info', trim($result->output()) ?: 'Done.');
    }

    protected function run(array $command, int $timeout = 60)
    {
        return Process::path(base_path())->timeout($timeout)->run($command);
    }

    /**
     * The queue worker's cron environment doesn't necessarily carry the
     * same PATH as an interactive shell — composer can live somewhere
     * like ~/bin/composer that a bare "composer" won't resolve. Confirmed
     * live: the first real deploy silently skipped composer install for
     * exactly this reason.
     */
    protected function composerBinary(): string
    {
        foreach ([getenv('HOME').'/bin/composer', '/usr/local/bin/composer'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'composer';
    }

    protected function step(DeploymentRun $deployment, string $step, string $level, string $message): void
    {
        DeploymentLog::create([
            'deployment_run_id' => $deployment->id,
            'step' => $step,
            'level' => $level,
            'message' => $message,
        ]);
    }
}
