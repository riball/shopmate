<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Pipeline\PipelineContext;

/**
 * One box in the pipeline flowchart. Each step has one job, reads from and
 * writes to the shared context, and says whether the pipeline should go on.
 */
interface PipelineStep
{
    public function handle(PipelineContext $context): StepOutcome;
}
