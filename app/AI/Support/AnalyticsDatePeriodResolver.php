<?php

declare(strict_types=1);

namespace App\AI\Support;

use Carbon\Carbon;

class AnalyticsDatePeriodResolver
{
    /**
     * Resolve start and end dates from structured arguments and/or natural language query.
     *
     * @param array<string, mixed> $arguments
     * @param string|null $fallbackQuery
     * @param string $timezone
     * @return array{0: Carbon|null, 1: Carbon|null, 2: string} [startDate, endDate, periodLabel]
     */
    public static function resolve(
        array $arguments = [],
        ?string $fallbackQuery = null,
        string $timezone = 'Asia/Dhaka'
    ): array {
        $rawStart = $arguments['start_date'] ?? $arguments['date_from'] ?? null;
        $rawEnd = $arguments['end_date'] ?? $arguments['date_to'] ?? null;

        $hasExplicitStart = ($rawStart !== null && trim((string) $rawStart) !== '');
        $hasExplicitEnd = ($rawEnd !== null && trim((string) $rawEnd) !== '');

        if ($hasExplicitStart || $hasExplicitEnd) {
            $start = null;
            $end = null;

            if ($hasExplicitStart) {
                try {
                    $start = Carbon::parse((string) $rawStart, $timezone)->startOfDay();
                } catch (\Throwable $e) {
                    throw new \InvalidArgumentException("Invalid date format provided for start date: '{$rawStart}'. Expected format is 'YYYY-MM-DD'.", 0, $e);
                }
            }

            if ($hasExplicitEnd) {
                try {
                    $end = Carbon::parse((string) $rawEnd, $timezone)->endOfDay();
                } catch (\Throwable $e) {
                    throw new \InvalidArgumentException("Invalid date format provided for end date: '{$rawEnd}'. Expected format is 'YYYY-MM-DD'.", 0, $e);
                }
            }

            $now = Carbon::now($timezone);
            $startObj = $start ?? $now->copy()->subDays(29)->startOfDay();
            $endObj = $end ?? $now->copy()->endOfDay();

            return [$startObj, $endObj, "{$startObj->toDateString()} থেকে {$endObj->toDateString()}"];
        }

        // 2. Explicit period key in arguments
        $periodArg = strtolower(trim((string) ($arguments['period'] ?? '')));

        // 3. Natural Language Query Inspection
        $query = trim((string) ($arguments['query'] ?? $arguments['question'] ?? $fallbackQuery ?? ''));
        $lower = mb_strtolower($query);

        $now = Carbon::now($timezone);

        // A. TODAY
        if ($periodArg === 'today' || preg_match('/(?:আজকে|আজকের|আজ\b|today|current\s*day)/ui', $lower)) {
            $today = $now->copy()->startOfDay();
            return [$today, $today->copy()->endOfDay(), 'আজকের (' . $today->toDateString() . ')'];
        }

        // B. YESTERDAY
        if ($periodArg === 'yesterday' || preg_match('/(?:গতকাল|গতকালের|yesterday)/ui', $lower)) {
            $yesterday = $now->copy()->subDay()->startOfDay();
            return [$yesterday, $yesterday->copy()->endOfDay(), 'গতকালের (' . $yesterday->toDateString() . ')'];
        }

        // C. THIS WEEK (Monday/Start of week to Today)
        if ($periodArg === 'this_week' || preg_match('/(?:এই\s*সপ্তাহ|চলতি\s*সপ্তাহ|this\s*week)/ui', $lower)) {
            $startWeek = $now->copy()->startOfWeek();
            return [$startWeek, $now->copy()->endOfDay(), 'এই সপ্তাহের (' . $startWeek->toDateString() . ' থেকে ' . $now->toDateString() . ')'];
        }

        // D. LAST 7 DAYS
        if ($periodArg === 'last_7_days' || preg_match('/(?:গত\s*৭\s*দিন|গত\s*7\s*দিন|last\s*7\s*days)/ui', $lower)) {
            $start7 = $now->copy()->subDays(6)->startOfDay();
            return [$start7, $now->copy()->endOfDay(), 'গত ৭ দিনের (' . $start7->toDateString() . ' থেকে ' . $now->toDateString() . ')'];
        }

        // E. THIS MONTH (1st of month to Today)
        if ($periodArg === 'this_month' || preg_match('/(?:এই\s*মাস|চলতি\s*মাস|this\s*month)/ui', $lower)) {
            $startMonth = $now->copy()->startOfMonth();
            return [$startMonth, $now->copy()->endOfDay(), 'এই মাসের (' . $startMonth->toDateString() . ' থেকে ' . $now->toDateString() . ')'];
        }

        // F. LAST MONTH (Full previous month)
        if ($periodArg === 'last_month' || preg_match('/(?:গত\s*মাস|last\s*month)/ui', $lower)) {
            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
            return [$lastMonthStart, $lastMonthEnd, 'গত মাসের (' . $lastMonthStart->toDateString() . ' থেকে ' . $lastMonthEnd->toDateString() . ')'];
        }

        // G. THIS YEAR (1st of Jan to Today)
        if ($periodArg === 'this_year' || preg_match('/(?:এই\s*বছর|চলতি\s*বছর|this\s*year)/ui', $lower)) {
            $startYear = $now->copy()->startOfYear();
            return [$startYear, $now->copy()->endOfDay(), 'এই বছরের (' . $startYear->toDateString() . ' থেকে ' . $now->toDateString() . ')'];
        }

        // H. ALL TIME
        if ($periodArg === 'all_time' || preg_match('/(?:সব\s*সময়|সবসময়|all\s*time)/ui', $lower)) {
            return [null, null, 'সর্বমোট (All Time)'];
        }

        // I. Single explicit start_date without end_date
        if (!empty($arguments['start_date'])) {
            try {
                $start = Carbon::parse((string) $arguments['start_date'], $timezone)->startOfDay();
                $end = !empty($arguments['end_date'])
                    ? Carbon::parse((string) $arguments['end_date'], $timezone)->endOfDay()
                    : $now->copy()->endOfDay();
                return [$start, $end, "{$start->toDateString()} থেকে {$end->toDateString()}"];
            } catch (\Throwable) {
                // fall through to default
            }
        }

        // J. Default: Last 30 Days
        $defaultStart = $now->copy()->subDays(29)->startOfDay();
        return [$defaultStart, $now->copy()->endOfDay(), "গত ৩০ দিনের ({$defaultStart->toDateString()} থেকে {$now->toDateString()})"];
    }
}
