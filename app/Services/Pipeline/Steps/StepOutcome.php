<?php

namespace App\Services\Pipeline\Steps;

enum StepOutcome
{
    case Continue;
    case Stop;
}
