<?php

namespace App\Services\Pipeline;

use App\Models\SearchSession;
use App\Models\TraceStep;

/**
 * Records every pipeline step (thought -> action -> observation) to the
 * database as it happens, so the page can show progress live.
 * Tool steps get an ID (T1, T2 ...) that the AI's claims must cite.
 */
class Trace
{
    private int $seq = 0;

    private int $toolSteps = 0;

    /** @var array<string, array{node: string, product_ids: int[]}> */
    private array $index = [];

    public function __construct(private SearchSession $session)
    {
        $this->seq = (int) $session->steps()->max('seq');
        $this->toolSteps = (int) $session->steps()->whereNotNull('step_id')->count();
        foreach ($session->steps()->whereNotNull('step_id')->get() as $step) {
            $this->index[$step->step_id] = ['node' => $step->node, 'product_ids' => $step->product_ids ?? []];
        }
    }

    /** A tool step: returns its citable ID. */
    public function tool(string $node, string $thought, string $action, string $observation, array $productIds = [], array $data = []): string
    {
        $stepId = 'T'.(++$this->toolSteps);
        $this->index[$stepId] = ['node' => $node, 'product_ids' => array_values(array_map('intval', $productIds))];
        $this->write($node, 'info', $stepId, $thought, $action, $observation, $productIds, $data);

        return $stepId;
    }

    /** A note for the shopper (warnings, removed claims, fallbacks). Not citable. */
    public function note(string $node, string $observation, string $level = 'info', array $data = []): void
    {
        $this->write($node, $level, null, null, null, $observation, [], $data);
    }

    public function exists(string $stepId): bool
    {
        return isset($this->index[$stepId]);
    }

    public function stepCovers(string $stepId, int $productId): bool
    {
        return in_array($productId, $this->index[$stepId]['product_ids'] ?? [], true);
    }

    public function nodeOf(string $stepId): ?string
    {
        return $this->index[$stepId]['node'] ?? null;
    }

    private function write(string $node, string $level, ?string $stepId, ?string $thought, ?string $action, string $observation, array $productIds, array $data): void
    {
        TraceStep::create([
            'search_session_id' => $this->session->id,
            'seq' => ++$this->seq,
            'step_id' => $stepId,
            'node' => $node,
            'level' => $level,
            'thought' => $thought,
            'action' => $action,
            'observation' => $observation,
            'product_ids' => $productIds ? array_values($productIds) : null,
            'data' => $data ?: null,
        ]);
    }
}
