<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AiReportController extends Controller
{
    public function __construct(private readonly AiReportOrchestrator $orchestrator) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'min:1'],
            'report_type' => ['required', Rule::in(['luv', 'interim', 'final'])],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'until_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'request' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        try {
            $draft = $this->orchestrator->draft(
                $request->user(),
                (int) $data['participant_id'],
                $data['report_type'],
                $data['from_date'],
                $data['until_date'],
                $data['request'],
            );
        } catch (AgentUnavailableException) {
            return response()->json([
                'message' => 'Der interne KI-Dienst ist derzeit nicht verfuegbar.',
            ], 503, [
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response()->json([
            'status' => 'draft',
            ...$draft,
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
