<?php

namespace App\Http\Controllers\Api\Cleaning;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Cleaning\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CleaningReportController extends Controller
{
    public function __construct(private readonly EvaluationService $evaluations)
    {
    }

    /**
     * JSON with the SAME numbers as the grid — the frontend renders it to a PNG
     * for WhatsApp. One source of truth (EvaluationService).
     */
    public function data(Request $request): JsonResponse
    {
        [$type, $key] = $this->validatePeriod($request);

        return response()->json(
            $this->evaluations->buildGrid($type, $key, $this->allowedStoreIds($request))
        );
    }

    /**
     * CSV of the whole grid (stores, item verdicts, item score, chart tasks by
     * frequency with weights, chart score).
     */
    public function csv(Request $request): StreamedResponse
    {
        [$type, $key] = $this->validatePeriod($request);
        $grid = $this->evaluations->buildGrid($type, $key, $this->allowedStoreIds($request));

        $itemNames = collect($grid['items'])->pluck('name')->all();
        $freqs = ['daily', 'weekly', 'monthly', 'hourly'];

        $header = array_merge(
            ['Store'],
            $itemNames,
            ['Item Score'],
            array_map(fn ($f) => 'CC ' . ucfirst($f), $freqs),
            ['Chart Score']
        );

        $filename = "cleaning_evaluation_{$key}.csv";

        return response()->streamDownload(function () use ($grid, $itemNames, $freqs, $header) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($grid['rows'] as $row) {
                $line = [$row['store']];
                foreach ($itemNames as $name) {
                    $line[] = $row['item_values'][$name] ?? 'empty';
                }
                $line[] = $row['item_score'] . '%';
                foreach ($freqs as $f) {
                    $tasks = $row['chart'][$f] ?? [];
                    $line[] = implode(' | ', array_map(
                        fn ($t) => $t['name'] . ':' . ($t['verdict'] ?? 'ungraded') . '(' . $t['weight'] . ')',
                        $tasks
                    )) ?: '-';
                }
                $line[] = $row['chart_score'] . '%';
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0:string,1:string} */
    private function validatePeriod(Request $request): array
    {
        $data = $request->validate([
            'period_type' => ['nullable', Rule::in(['date', 'week'])],
            'period_key'  => ['required', 'string', 'max:20'],
        ]);

        return [$data['period_type'] ?? 'week', $data['period_key']];
    }

    /** @return int[] */
    private function allowedStoreIds(Request $request): array
    {
        $user = $request->user();
        return $user ? $user->allowedStoreIdsCached() : Store::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
    }
}
