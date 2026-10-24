<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchSession extends Model
{
    use HasUuids;

    public const FINISHED = ['needs_clarification', 'done', 'no_results', 'failed'];

    protected $fillable = [
        'query', 'clarification_answer', 'clarified', 'status', 'clarify_question', 'plan',
        'original_budget', 'effective_budget', 'replans', 'llm_driver', 'claims_checked',
        'claims_removed', 'error', 'duration_ms', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'clarified' => 'boolean',
            'plan' => 'array',
            'replans' => 'array',
            'original_budget' => 'float',
            'effective_budget' => 'float',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TraceStep::class)->orderBy('seq');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderBy('rank');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    /** The text the planner reads: the request plus the answer to the clarifying question. */
    public function fullQuery(): string
    {
        return $this->clarification_answer
            ? $this->query.' — '.$this->clarification_answer
            : $this->query;
    }
}
