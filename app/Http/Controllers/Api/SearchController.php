<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunSearchPipeline;
use App\Models\SearchSession;
use App\Services\Pipeline\SearchPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Start a search, poll its progress, and answer a clarifying question.
 * The heavy work runs in a queue job; the browser polls show() every second.
 */
class SearchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $session = SearchSession::create([
            'query' => trim($data['query']),
            'status' => 'queued',
            'ip' => $request->ip(),
        ]);

        RunSearchPipeline::dispatch($session->id);

        return response()->json([
            'id' => $session->id,
            'status' => $session->fresh()->status,
            'url' => route('search.show', $session),
        ], 201);
    }

    public function show(Request $request, SearchSession $session, SearchPresenter $presenter): JsonResponse
    {
        $after = max(0, (int) $request->query('after', 0));

        return response()->json($presenter->present($session, $after));
    }

    public function clarify(Request $request, SearchSession $session): JsonResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        if ($session->status !== 'needs_clarification' || $session->clarified) {
            return response()->json(['message' => 'This search is not waiting for an answer.'], 409);
        }

        $session->update([
            'clarification_answer' => trim($data['answer']),
            'clarified' => true,
            'status' => 'queued',
            'clarify_question' => null,
        ]);

        RunSearchPipeline::dispatch($session->id);

        return response()->json(['id' => $session->id, 'status' => $session->fresh()->status]);
    }
}
