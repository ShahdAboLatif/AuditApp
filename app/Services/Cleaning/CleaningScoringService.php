<?php

namespace App\Services\Cleaning;

/**
 * The two scores. They are ALWAYS separate and never merged.
 *
 * Decisions applied (see cleaning-chart-module.md §12):
 *  - Item Score: ignore empty cells; any auto_fail => 0; else pass / inspected.
 *  - Chart Score: weighted, earned / total; an auto_fail on a chart task just
 *    costs its weight (treated like a fail), it does NOT zero the whole score.
 */
class CleaningScoringService
{
    /**
     * @param  string[]  $values  each: pass|fail|auto_fail|empty
     * @return int  percentage 0..100
     */
    public function itemScore(array $values): int
    {
        $inspected = array_values(array_filter($values, fn ($v) => $v !== 'empty'));

        if (empty($inspected)) {
            return 0;
        }

        if (in_array('auto_fail', $inspected, true)) {
            return 0;
        }

        $pass = count(array_filter($inspected, fn ($v) => $v === 'pass'));

        return (int) round($pass / count($inspected) * 100);
    }

    /**
     * @param  array<array{weight:int, verdict:string}>  $tasks
     * @return array{pct:int, earned:int, total:int, lost:int}
     */
    public function chartScore(array $tasks): array
    {
        $total  = 0;
        $earned = 0;

        foreach ($tasks as $t) {
            $weight = (int) ($t['weight'] ?? 0);
            $total += $weight;
            if (($t['verdict'] ?? null) === 'pass') {
                $earned += $weight;
            }
        }

        $pct = $total > 0 ? (int) round($earned / $total * 100) : 0;

        return [
            'pct'    => $pct,
            'earned' => $earned,
            'total'  => $total,
            'lost'   => $total - $earned,
        ];
    }
}
