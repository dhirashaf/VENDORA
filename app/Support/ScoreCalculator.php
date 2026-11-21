<?php

namespace App\Support;

use App\Enums\Recommendation;
use App\Models\AssessmentCategory;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use Illuminate\Support\Collection;

/**
 * Perhitungan weighted score, persentase pencapaian, dan rekomendasi otomatis
 * mengikuti logika Ringkasan Hasil pada form Vendor Performance Evaluation (FR-13, FR-15).
 */
class ScoreCalculator
{
    public const MAX_SCORE = 4;

    public static function weightedScore(int $score, float $weightPct): float
    {
        return round($score * $weightPct / 100, 2);
    }

    /**
     * Total dihitung dari nilai mentah (skor x bobot) agar tidak ada galat pembulatan per item (NFR-05).
     *
     * @param  Collection<int, EvaluationScore>  $scores
     */
    public static function total(Collection $scores): float
    {
        return round($scores->sum(fn (EvaluationScore $score): float => $score->score * (float) $score->weight_pct) / 100, 2);
    }

    public static function achievementPct(float $totalWeightedScore): float
    {
        return round($totalWeightedScore / self::MAX_SCORE * 100, 2);
    }

    public static function recommendationFor(?float $totalWeightedScore): Recommendation
    {
        if ($totalWeightedScore === null) {
            return Recommendation::CannotBeCalculated;
        }

        if ($totalWeightedScore >= Settings::decimal(Settings::RECOMMENDED_MIN_SCORE)) {
            return Recommendation::Recommended;
        }

        if ($totalWeightedScore >= Settings::decimal(Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE)) {
            return Recommendation::RecommendedWithNotes;
        }

        return Recommendation::NotRecommended;
    }

    /**
     * Skor rata-rata berbobot per kategori (skala 1-4) untuk grafik rekapitulasi.
     *
     * @param  Collection<int, AssessmentCategory>  $categories
     * @return array<string, float|null>
     */
    public static function categoryScores(Evaluation $evaluation, Collection $categories): array
    {
        $scoresByItem = $evaluation->scores->keyBy('item_id');

        return $categories->mapWithKeys(function (AssessmentCategory $category) use ($scoresByItem): array {
            $scores = $category->items->map(fn ($item) => $scoresByItem->get($item->id))->filter();
            $weight = $scores->sum(fn (EvaluationScore $score): float => (float) $score->weight_pct);

            return [$category->code => $weight > 0
                ? round($scores->sum(fn (EvaluationScore $score): float => $score->score * (float) $score->weight_pct) / $weight, 2)
                : null];
        })->all();
    }
}
