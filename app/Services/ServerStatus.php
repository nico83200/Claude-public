<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * État du serveur lu depuis l'application (hébergement mutualisé : chaque mesure est facultative,
 * l'hébergeur pouvant en interdire la lecture ; elle vaut alors null et la page l'indique).
 * Les limites propres à l'offre Hostinger (CPU, processus, inodes) ne sont visibles que dans hPanel.
 */
class ServerStatus
{
    public function snapshot(): array
    {
        $t0 = microtime(true);
        $data = ['at' => now()->toIso8601String(), 'php' => $this->php(), 'system' => $this->system(), 'disk' => $this->disk(),
            'database' => $this->database(), 'app' => $this->app()];

        return $data + ['build_ms' => (int) round((microtime(true) - $t0) * 1000)];
    }

    private function php(): array
    {
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'opcache' => is_array($opcache) ? [
                'enabled' => (bool) ($opcache['opcache_enabled'] ?? false),
                'used_mb' => round(($opcache['memory_usage']['used_memory'] ?? 0) / 1048576, 1),
                'free_mb' => round(($opcache['memory_usage']['free_memory'] ?? 0) / 1048576, 1),
                'hit_rate' => isset($opcache['opcache_statistics']['opcache_hit_rate']) ? round($opcache['opcache_statistics']['opcache_hit_rate'], 1) : null,
            ] : null,
        ];
    }

    private function system(): array
    {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
        $cpus = is_readable('/proc/cpuinfo') ? (preg_match_all('/^processor\s*:/m', (string) @file_get_contents('/proc/cpuinfo')) ?: null) : null;
        $mem = null;
        if (is_readable('/proc/meminfo') && preg_match_all('/^(MemTotal|MemAvailable):\s+(\d+)/m', (string) @file_get_contents('/proc/meminfo'), $m)) {
            $kv = array_combine($m[1], $m[2]);
            if (isset($kv['MemTotal'], $kv['MemAvailable'])) {
                $mem = ['total_mb' => (int) round($kv['MemTotal'] / 1024), 'available_mb' => (int) round($kv['MemAvailable'] / 1024)];
            }
        }

        return [
            'load' => is_array($load) ? array_map(fn ($v) => round($v, 2), $load) : null,
            'cpus' => $cpus,
            'memory' => $mem,
            'hostname' => @gethostname() ?: null,
        ];
    }

    private function disk(): ?array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        return $free !== false && $total ? ['free_gb' => round($free / 1073741824, 2), 'total_gb' => round($total / 1073741824, 2)] : null;
    }

    private function database(): array
    {
        $t0 = microtime(true);
        try {
            DB::select('select 1');
            $ping = round((microtime(true) - $t0) * 1000, 1);
        } catch (Throwable) {
            return ['ok' => false];
        }
        $status = [];
        try {
            foreach (DB::select("show global status where Variable_name in ('Threads_connected', 'Threads_running', 'Questions', 'Uptime', 'Slow_queries', 'Max_used_connections')") as $row) {
                $status[$row->Variable_name] = (int) $row->Value;
            }
        } catch (Throwable) {
        }
        $size = null;
        $tables = [];
        try {
            $rows = DB::select('select table_name as t, table_rows as r, data_length + index_length as b from information_schema.tables where table_schema = database()');
            $size = round(collect($rows)->sum('b') / 1048576, 1);
            $tables = collect($rows)->sortByDesc('b')->take(8)->map(fn ($r) => ['name' => $r->t, 'rows' => (int) $r->r, 'mb' => round($r->b / 1048576, 2)])->values()->all();
        } catch (Throwable) {
        }

        return ['ok' => true, 'ping_ms' => $ping, 'status' => $status ?: null, 'size_mb' => $size, 'tables' => $tables];
    }

    private function app(): array
    {
        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (Throwable) {
                return null;
            }
        };

        return [
            'queue_pending' => $safe(fn () => DB::table('jobs')->count()),
            'queue_failed' => $safe(fn () => DB::table('failed_jobs')->count()),
            'cron_silent_minutes' => $safe(fn () => SystemMonitor::cronSilentMinutes()),
            'errors_last_hour' => $safe(fn () => SystemMonitor::errorsLastHour()),
            'users' => User::whereNull('simulation_batch_id')->count(),
            'simulated_users' => User::whereNotNull('simulation_batch_id')->count(),
        ];
    }
}
