<?php

declare(strict_types=1);

namespace BloodMatch\Services;

/** Approximate straight-line reference-point distance, then chapter, then donor ID. */
final class MatchProximity
{
    public static function compare(array $a, array $b): int
    {
        return (($a['distance'] === null) <=> ($b['distance'] === null))
            ?: (($a['distance'] ?? 0) <=> ($b['distance'] ?? 0))
            ?: ($b['same_chapter'] <=> $a['same_chapter'])
            ?: ((int) $a['row']['id'] <=> (int) $b['row']['id']);
    }

    /** Internal aliases only; legacy rank_score and generation never determine proximity. */
    public static function orderSql(string $requestChapter): string
    {
        return "m.distance_km IS NULL ASC, m.distance_km ASC, "
            . "CASE WHEN {$requestChapter} IS NOT NULL AND u.chapter_id = {$requestChapter} THEN 0 ELSE 1 END ASC, m.donor_id ASC";
    }
}
