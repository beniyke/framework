<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Money helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Money\Currency;
use Money\Money;

if (! function_exists('money')) {
    function money(int|float $amount, string $currency = 'USD'): Money
    {
        if (is_float($amount)) {
            return Money::amount($amount, $currency);
        }

        return Money::make($amount, $currency);
    }
}

if (! function_exists('money_parse')) {
    /**
     * Parse money from string
     *
     * @param string      $money    Money string (e.g., "$100.00")
     * @param string|null $currency Currency code
     */
    function money_parse(string $money, ?string $currency = null): Money
    {
        // Simple parser - remove currency symbols and parse
        $amount = preg_replace('/[^0-9.-]/', '', $money);
        $currency = $currency ?? 'USD';

        return Money::amount((float) $amount, $currency);
    }
}

if (! function_exists('money_format')) {
    /**
     * Format money
     *
     * @param Money       $money  Money instance
     * @param string|null $locale Locale code
     */
    function money_format(Money $money, ?string $locale = null): string
    {
        return $money->format($locale);
    }
}

if (! function_exists('currency')) {
    function currency(string $code): Currency
    {
        return Currency::of($code);
    }
}
