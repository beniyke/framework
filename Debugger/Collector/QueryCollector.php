<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * QueryCollector collects SQL queries for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use Database\Connection;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Helpers\File\Paths;
use Throwable;

class QueryCollector extends DataCollector implements Renderable
{
    protected const SLOW_QUERY_THRESHOLD_MS = 100;
    protected const EXPLAIN_QUERY_THRESHOLD_MS = 50;
    protected const N_PLUS_ONE_MIN_COUNT = 5;
    protected const N_PLUS_ONE_TIME_WINDOW_MS = 200;
    protected const N_PLUS_ONE_MAX_STACK_VARIANCE = 2;
    protected const BENCH_CPU_HIGH_PERCENT = 30;
    protected const BENCH_MEMORY_HIGH_MB = 50;
    protected const CACHE_HIT_RATE_GOOD = 70;
    protected const LEAK_MIN_RETAINED_KB = 50;
    protected const LEAK_MIN_SCORE = 10;

    private float $startTime;

    private float $startMemory;

    private array $startCpu;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_peak_usage(true);
        $this->startCpu = getrusage();
    }

    public function getName(): string
    {
        return 'query';
    }

    public function collect(): array
    {
        $logs = Connection::getQueryLog();
        Connection::clearQueryLog();

        $connectionCacheStats = $this->getConnectionCacheStats();

        $queries = [];
        $slowQueries = [];
        $leakyQueries = [];
        $totalTime = 0.0;
        $totalMemory = 0;
        $queryHashes = [];
        $candidateGroups = [];

        $cacheHits = 0;
        $cacheMisses = 0;
        $cacheTimeSaved = 0.0;
        $cacheOperations = [];

        $endTime = microtime(true);
        $endMemory = memory_get_peak_usage(true);
        $endCpu = getrusage();
        $totalElapsed = ($endTime - $this->startTime) * 1000;
        $memoryDelta = ($endMemory - $this->startMemory) / 1024 / 1024;

        $cpuUser = $endCpu['ru_utime.tv_sec'] + $endCpu['ru_utime.tv_usec'] / 1e6
            - ($this->startCpu['ru_utime.tv_sec'] + $this->startCpu['ru_utime.tv_usec'] / 1e6);
        $cpuSys = $endCpu['ru_stime.tv_sec'] + $endCpu['ru_stime.tv_usec'] / 1e6
            - ($this->startCpu['ru_stime.tv_sec'] + $this->startCpu['ru_stime.tv_usec'] / 1e6);
        $cpuTotal = $cpuUser + $cpuSys;
        $cpuPercent = $totalElapsed > 0 ? ($cpuTotal / ($totalElapsed / 1000)) * 100 : 0;

        foreach ($logs as $log) {
            if (! isset($log['sql'], $log['time_ms'], $log['connection'], $log['bindings'])) {
                continue;
            }

            $sqlWithBindings = $this->replaceBindings($log['sql'], $log['bindings']);
            $originalBindings = $log['bindings'];

            $timeMs = (float) $log['time_ms'];
            $success = $log['success'] ?? true;
            $sqlHash = md5($log['sql']);
            $isDuplicate = isset($queryHashes[$sqlHash]);
            $queryHashes[$sqlHash] = true;

            $analysis = $this->analyzeQuery($log);
            $timestamp = $log['timestamp'] ?? microtime(true);
            $stack = $log['stack'] ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

            $memBefore = $log['memory_before'] ?? 0;
            $memAfter = $log['memory_after'] ?? 0;
            $memDelta = $memAfter - $memBefore;
            $retained = max(0, $memDelta);
            $leakScore = $timeMs > 0 ? ($retained / 1024) / $timeMs : 0;
            $isLeak = $retained >= (self::LEAK_MIN_RETAINED_KB * 1024)
                && $leakScore >= self::LEAK_MIN_SCORE;

            $cacheInfo = $log['cache'] ?? null;
            $wasCached = false;
            $cacheKey = $cacheInfo['key'] ?? null;
            $cacheHit = $cacheInfo['hit'] ?? false;
            $cacheTime = $cacheInfo['time_ms'] ?? 0;

            if ($cacheHit) {
                $cacheHits++;
                $cacheTimeSaved += $timeMs;
                $wasCached = true;
            } else {
                $cacheMisses++;
            }

            if ($cacheKey) {
                $cacheOperations[] = [
                    'key' => $cacheKey,
                    'hit' => $cacheHit,
                    'time_ms' => $cacheTime,
                    'sql' => $log['sql'],
                    'duration' => $timeMs,
                ];
            }

            $query = array_merge([
                'sql' => $log['sql'],
                'sql_with_bindings' => $sqlWithBindings,
                'params' => $originalBindings,
                'duration' => $timeMs,
                'duration_str' => number_format($timeMs, 2) . 'ms',
                'memory_before' => $memBefore,
                'memory_after' => $memAfter,
                'memory_delta' => $memDelta,
                'memory_delta_str' => $memDelta > 0 ? '+' . number_format($memDelta / 1024, 1) . 'KB' : '0KB',
                'retained_kb' => round($retained / 1024, 2),
                'leak_score' => round($leakScore, 3),
                'is_leak' => $isLeak,
                'connection' => $log['connection'],
                'success' => $success,
                'is_success' => $success,
                'row_count' => $log['row_count'] ?? null,
                'is_duplicate' => $isDuplicate,
                'timestamp' => $timestamp,
                'backtrace' => $this->normalizeStack($stack),
                'cached' => $wasCached,
                'cache_hit' => $cacheHit,
                'cache_key' => $cacheKey,
                'cache_time_ms' => $cacheTime > 0 ? round($cacheTime, 2) : null,
            ], $analysis);

            $queries[] = $query;

            if ($timeMs > self::SLOW_QUERY_THRESHOLD_MS && ! $wasCached) {
                $slowQueries[] = $query;
            }

            if ($isLeak) {
                $leakyQueries[] = $query;
            }

            $totalTime += $timeMs;
            $totalMemory += $memDelta;

            $normSql = $this->normalizeSql($log['sql']);
            $fingerprint = $this->getStackFingerprint($stack);
            $groupKey = $normSql . '|' . $fingerprint;

            if (! isset($candidateGroups[$groupKey])) {
                $candidateGroups[$groupKey] = [
                    'sql' => $log['sql'],
                    'norm_sql' => $normSql,
                    'fingerprint' => $fingerprint,
                    'executions' => [],
                    'stack_sites' => [],
                ];
            }

            $candidateGroups[$groupKey]['executions'][] = ['time' => $timestamp, 'query' => $query];
            $candidateGroups[$groupKey]['stack_sites'][$fingerprint] = true;
        }

        $nPlusOneGroups = [];
        foreach ($candidateGroups as $group) {
            $execs = $group['executions'];
            if (count($execs) < self::N_PLUS_ONE_MIN_COUNT) {
                continue;
            }

            $times = array_column($execs, 'time');
            sort($times);
            $spanMs = ($times[count($times) - 1] - $times[0]) * 1000;

            if ($spanMs > self::N_PLUS_ONE_TIME_WINDOW_MS) {
                continue;
            }

            if (count($group['stack_sites']) > self::N_PLUS_ONE_MAX_STACK_VARIANCE) {
                continue;
            }

            $samples = array_slice($execs, 0, 3);
            $durations = array_map(fn ($e) => $e['query']['duration'], $execs);
            $totalDuration = array_sum($durations);
            $nPlusOneGroups[] = [
                'sql' => $group['sql'],
                'count' => count($execs),
                'total_time' => $totalDuration,
                'avg_time' => $totalDuration / count($execs),
                'time_span' => round($spanMs, 2) . 'ms',
                'call_sites' => count($group['stack_sites']),
                'samples' => array_map(fn ($e) => $e['query'], $samples),
                'warning' => 'N+1 Detected',
            ];
        }

        $durations = array_column($queries, 'duration');
        rsort($durations);
        $p95 = count($durations) > 0 ? $durations[min((int) (count($durations) * 0.05), count($durations) - 1)] : 0;
        $totalCacheOps = $cacheHits + $cacheMisses;
        $hitRate = $totalCacheOps > 0 ? round(($cacheHits / $totalCacheOps) * 100, 1) : 0;

        $benchmarks = [
            'request_duration_ms' => round($totalElapsed, 2),
            'query_time_ms' => round($totalTime, 2),
            'query_percent_of_total' => $totalElapsed > 0 ? round(($totalTime / $totalElapsed) * 100, 1) : 0,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_delta_mb' => round($memoryDelta, 2),
            'cpu_user_sec' => round($cpuUser, 3),
            'cpu_system_sec' => round($cpuSys, 3),
            'cpu_percent' => round($cpuPercent, 1),
            'p95_query_time_ms' => round($p95, 2),
            'queries_per_second' => $totalElapsed > 0 ? round(count($queries) / ($totalElapsed / 1000), 1) : 0,
            'avg_query_time_ms' => count($queries) > 0 ? round($totalTime / count($queries), 2) : 0,
            'is_high_cpu' => $cpuPercent > self::BENCH_CPU_HIGH_PERCENT,
            'is_high_memory' => $memoryDelta > self::BENCH_MEMORY_HIGH_MB,
        ];

        $queryCacheMetrics = [];
        try {
            $queryCacheMetrics = \Helpers\File\Cache::create('query')->getMetrics();
        } catch (Throwable $e) {
            // Ignore
        }

        $cacheMetrics = [
            'hits' => $cacheHits,
            'misses' => $cacheMisses,
            'total' => $totalCacheOps,
            'hit_rate_percent' => $hitRate,
            'time_saved_ms' => round($cacheTimeSaved, 2),
            'is_good_cache' => $hitRate >= self::CACHE_HIT_RATE_GOOD,
            'operations' => $cacheOperations,
            'statement_cache' => json_encode($connectionCacheStats, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
            'query_result_cache' => json_encode($queryCacheMetrics, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
        ];

        $totalRetainedKB = array_sum(array_column($leakyQueries, 'retained_kb'));
        $leakSummary = [
            'leaky_queries' => $leakyQueries,
            'leak_count' => count($leakyQueries),
            'total_retained_kb' => round($totalRetainedKB, 2),
            'has_leak' => count($leakyQueries) > 0,
        ];

        $duplicateCount = count($queries) - count(array_unique(array_column($queries, 'sql')));
        $nPlusOneCount = count($nPlusOneGroups);

        $nPlusOneMessages = [];
        foreach ($nPlusOneGroups as $group) {
            $sql = htmlspecialchars($group['sql']);
            $nPlusOneMessages[] = "
                <span class='phpdebugbar-widgets-label'>{$group['count']} queries in {$group['time_span']}</span>
                <code class='phpdebugbar-widgets-sql'>$sql</code>
                <span class='phpdebugbar-text-muted'>
                    Total: " . number_format($group['total_time'], 2) . 'ms,
                    Avg: ' . number_format($group['avg_time'], 2) . 'ms
                </span>
            ';
        }

        $slowQueriesData = [
            'statements' => $slowQueries,
            'nb_statements' => count($slowQueries),
            'accumulated_duration_str' => '',
        ];

        $leakyQueriesData = [
            'statements' => $leakyQueries,
            'nb_statements' => count($leakyQueries),
            'accumulated_duration_str' => '',
        ];

        return [
            'count' => count($queries),
            'nb_statements' => count($queries),
            'total_time' => $totalTime,
            'total_time_str' => number_format($totalTime, 2) . 'ms',
            'accumulated_duration_str' => number_format($totalTime, 2) . 'ms',
            'queries' => $queries,
            'statements' => $queries,
            'duplicate_count' => $duplicateCount,
            'slow_queries' => $slowQueries,
            'slow_queries_data' => $slowQueriesData,
            'slow_count' => count($slowQueries),
            'n_plus_one_groups' => $nPlusOneGroups,
            'n_plus_one_messages' => $nPlusOneMessages,
            'n_plus_one_count' => $nPlusOneCount,
            'benchmarks' => $benchmarks,
            'cache' => $cacheMetrics,
            'memory_leaks' => $leakSummary,
            'leaky_queries_data' => $leakyQueriesData,
            'memory_usage_str' => $memoryDelta > 0 ? '+' . number_format($memoryDelta / 1024, 1) . 'KB' : '0KB',
        ];
    }

    protected function replaceBindings(string $sql, array $bindings): string
    {
        if (! $bindings) {
            return $sql;
        }

        $tempBindings = $bindings;

        $sql = preg_replace_callback('/\?/', function () use (&$tempBindings) {
            $value = array_shift($tempBindings);

            return $this->formatBinding($value);
        }, $sql);

        foreach ($bindings as $key => $value) {
            if (! is_int($key)) {
                $sql = str_replace(":$key", $this->formatBinding($value), $sql);
            }
        }

        return $sql;
    }

    private function formatBinding($value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'Binary Data';
    }

    protected function normalizeSql(string $sql): string
    {
        return preg_replace_callback('/(\'.*?(?<!\\\\)\'|\".*?(?<!\\\\)\"|\d+\.\d+|\d+|:\w+|\\\?)/', function ($m) {
            $val = $m[0];
            if (preg_match('/^[\'"].*[\'"]$/', $val)) {
                return '?';
            }
            if (is_numeric($val)) {
                return '?';
            }
            if (preg_match('/^:\w+$/', $val)) {
                return ':';
            }

            return $val;
        }, $sql);
    }

    protected function getStackFingerprint(array $stack): string
    {
        foreach ($stack as $frame) {
            if (isset($frame['file']) && isset($frame['line'])) {
                $file = $frame['file'];
                $file = str_replace(Paths::basePath(), '', $file);
                if (str_contains($file, '/vendor/') || str_contains($file, '/symfony/')) {
                    continue;
                }

                return md5($file . ':' . $frame['line']);
            }
        }

        return 'unknown';
    }

    protected function normalizeStack(array $stack): array
    {
        $normalized = [];
        foreach ($stack as $i => $frame) {
            if ($i > 10) {
                break;
            }

            $normalized[] = [
                'file' => str_replace([Paths::basePath(), '\\'], ['', '/'], ($frame['file'] ?? 'unknown')),
                'line' => $frame['line'] ?? 0,
                'namespace' => str_replace('\\', '/', $frame['function'] ?? ''),
            ];
        }

        return $normalized;
    }

    protected function analyzeQuery(array $log): array
    {
        $timeMs = (float) $log['time_ms'];
        $sql = strtoupper(trim($log['sql']));

        $analysis = [
            'is_slow' => $timeMs > self::SLOW_QUERY_THRESHOLD_MS,
            'explain_results' => [],
            'is_unoptimized' => false,
            'unoptimized_reason' => null,
        ];

        if (str_starts_with($sql, 'SELECT') && $timeMs > self::EXPLAIN_QUERY_THRESHOLD_MS) {
            try {
                $db = \Database\DB::connection();
                $stmt = $db->select('EXPLAIN ' . $log['sql'], $log['bindings']);
                foreach ($stmt as $row) {
                    $analysis['explain_results'][] = (array) $row;
                    $type = strtolower($row['type'] ?? '');
                    $extra = strtoupper($row['Extra'] ?? '');

                    if (in_array($type, ['all', 'index'])) {
                        $analysis['is_unoptimized'] = true;
                        $analysis['unoptimized_reason'] = "Full scan ($type).";
                        break;
                    }

                    if (str_contains($extra, 'USING TEMPORARY')) {
                        $analysis['is_unoptimized'] = true;
                        $analysis['unoptimized_reason'] = 'Temporary table.';
                        break;
                    }

                    if (str_contains($extra, 'USING FILESORT')) {
                        $analysis['is_unoptimized'] = true;
                        $analysis['unoptimized_reason'] = 'Filesort.';
                        break;
                    }
                }
            } catch (Throwable $e) {
                $analysis['explain_results'][] = ['error' => 'EXPLAIN failed: ' . $e->getMessage()];
            }
        }

        return $analysis;
    }

    protected function getConnectionCacheStats(): array
    {
        try {
            $connection = \Database\DB::connection();

            if (! method_exists($connection, 'getCacheStats')) {
                return [
                    'available' => false,
                    'message' => 'Statement caching not available',
                ];
            }

            $stats = $connection->getCacheStats();

            $hitRateNumeric = (float) str_replace('%', '', $stats['hit_rate']);
            $utilizationPercent = $stats['max_size'] > 0
                ? round(($stats['size'] / $stats['max_size']) * 100, 1)
                : 0;

            $efficiency = 'unknown';
            if ($hitRateNumeric >= 80) {
                $efficiency = 'excellent';
            } elseif ($hitRateNumeric >= 60) {
                $efficiency = 'good';
            } elseif ($hitRateNumeric >= 40) {
                $efficiency = 'fair';
            } else {
                $efficiency = 'poor';
            }

            return [
                'available' => true,
                'size' => $stats['size'],
                'max_size' => $stats['max_size'],
                'utilization_percent' => $utilizationPercent,
                'hits' => $stats['hits'],
                'misses' => $stats['misses'],
                'hit_rate' => $stats['hit_rate'],
                'hit_rate_numeric' => $hitRateNumeric,
                'efficiency' => $efficiency,
                'is_efficient' => $hitRateNumeric >= 60,
                'recommendations' => $this->getStatementCacheRecommendations($stats, $hitRateNumeric, $utilizationPercent),
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getStatementCacheRecommendations(array $stats, float $hitRate, float $utilization): array
    {
        $recommendations = [];

        if ($hitRate < 40 && $stats['hits'] + $stats['misses'] > 50) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Low statement cache hit rate. Consider reviewing query patterns or increasing cache size.',
            ];
        }

        if ($utilization > 90) {
            $recommendations[] = [
                'type' => 'info',
                'message' => "Cache is {$utilization}% full. Consider increasing max_size if hit rate is good.",
            ];
        }

        if ($hitRate >= 80) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Excellent statement cache performance! Queries are being efficiently reused.',
            ];
        }

        if ($stats['hits'] + $stats['misses'] < 10) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Not enough data to assess cache performance yet.',
            ];
        }

        return $recommendations;
    }

    public function getWidgets(): array
    {
        $widgets = [
            'Queries' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'query',
                'default' => '[]',
            ],
            'Queries:badge' => [
                'map' => 'query.count',
                'default' => '0',
            ],
            'Queries:time' => [
                'map' => 'query.total_time_str',
                'default' => '\'0ms\'',
            ],
            'Performance' => [
                'icon' => 'tachometer-alt',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'query.benchmarks',
                'default' => '{}',
            ],
            'Cache' => [
                'icon' => 'save',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'query.cache',
                'default' => '{}',
            ],
        ];

        $hasN1 = 'query.n_plus_one_count > 0';
        $hasSlow = 'query.slow_count > 0';
        $highCpu = 'query.benchmarks.is_high_cpu';
        $highMem = 'query.benchmarks.is_high_memory';
        $goodCache = 'query.cache.is_good_cache';
        $hasLeak = 'query.memory_leaks.has_leak';

        $widgets['Queries:badge']['condition'] = "$hasN1 || $hasSlow || $highCpu || $highMem || $goodCache || $hasLeak";
        $widgets['Queries:badge']['style'] = "
            if ($hasLeak) return 'background:#9c27b0;color:#fff;';
            if ($hasN1) return 'background:#f0ad4e;color:#fff;';
            if ($highCpu || $highMem) return 'background:#9c27b0;color:#fff;';
            if ($hasSlow) return 'background:#d9534f;color:#fff;';
            if ($goodCache) return 'background:#4caf50;color:#fff;';
            return '';
        ";

        return $widgets;
    }
}
