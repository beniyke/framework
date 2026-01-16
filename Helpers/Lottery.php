<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 *
 * Lottery helper for probabilistic operations.
 * Features include odds-based execution, weighted selection, and testing hooks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use InvalidArgumentException;

class Lottery
{
    /**
     * Testing hook to force the lottery result.
     *
     * @var array|bool|null
     */
    protected static $testingResult = null;

    protected $chances;

    protected $outOf;

    protected $winnerCallback;

    protected $loserCallback;

    public function __construct(int $chances, int $outOf)
    {
        $this->chances = $chances;
        $this->outOf = $outOf;
    }

    /**
     * Force the lottery to always win (for testing).
     */
    public static function alwaysWin(): void
    {
        static::$testingResult = true;
    }

    /**
     * Force the lottery to always lose (for testing).
     */
    public static function alwaysLose(): void
    {
        static::$testingResult = false;
    }

    /**
     * Fix the lottery results to a specific sequence.
     *
     * @param array $sequence Array of booleans [true, false, true]
     */
    public static function fix(array $sequence): void
    {
        static::$testingResult = $sequence;
    }

    /**
     * Reset the lottery to normal random behavior.
     */
    public static function normal(): void
    {
        static::$testingResult = null;
    }

    /**
     * Determine if the lottery triggers based on odds.
     *
     * @param int $chances The number of winning tickets.
     * @param int $outOf   The total number of tickets.
     *
     * @return bool
     */
    public static function check(int $chances, int $outOf): bool
    {
        if (static::$testingResult !== null) {
            if (is_array(static::$testingResult)) {
                $result = array_shift(static::$testingResult);

                // If sequence runs out, loop or default to false?
                // Let's loop the last value or false.
                return $result ?? false;
            }

            return static::$testingResult;
        }

        if ($outOf < 1) {
            throw new InvalidArgumentException('Total tickets (outOf) must be at least 1.');
        }

        return random_int(1, $outOf) <= $chances;
    }

    /**
     * Create a new Lottery instance or execute immediately.
     *
     * @param int           $chances
     * @param int           $outOf
     * @param callable|null $callback     Optional callback to execute immediately.
     * @param callable|null $failCallback Optional callback for immediate failure.
     *
     * @return mixed|self Returns result if callback provided, or self instance.
     */
    public static function odds(int $chances, int $outOf, ?callable $callback = null, ?callable $failCallback = null): mixed
    {
        $instance = new static($chances, $outOf);

        if ($callback) {
            return $instance->winner($callback)->loser($failCallback)->choose();
        }

        return $instance;
    }

    public function winner(callable $callback): self
    {
        $this->winnerCallback = $callback;

        return $this;
    }

    public function loser(?callable $callback): self
    {
        $this->loserCallback = $callback;

        return $this;
    }

    /**
     * Run the lottery and execute callbacks.
     *
     * @param int $times Number of times to run (default 1).
     *
     * @return mixed Result of callback (or array of results if times > 1).
     */
    public function choose(int $times = 1): mixed
    {
        $results = [];

        for ($i = 0; $i < $times; $i++) {
            $result = null;

            if (static::check($this->chances, $this->outOf)) {
                if ($this->winnerCallback) {
                    $result = call_user_func($this->winnerCallback);
                }
            } elseif ($this->loserCallback) {
                $result = call_user_func($this->loserCallback);
            }

            $results[] = $result;
        }

        return $times === 1 ? $results[0] : $results;
    }

    /**
     * Pick one item from an array based on weights.
     *
     * @param array $items Associative array of [value => weight]
     *
     * @return int|string|null The selected key
     */
    public static function pick(array $items): int|string|null
    {
        if (empty($items)) {
            return null;
        }

        $totalWeight = array_sum($items);
        $random = random_int(1, $totalWeight);
        $currentWeight = 0;

        foreach ($items as $key => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $key;
            }
        }

        return null;
    }
}
