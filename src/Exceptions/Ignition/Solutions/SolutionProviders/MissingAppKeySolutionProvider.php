<?php

namespace Mini\Framework\Exceptions\Ignition\Solutions\SolutionProviders;

use Mini\Framework\Exceptions\Ignition\Solutions\GenerateAppKeySolution;
use RuntimeException;
use Spatie\Ignition\Contracts\HasSolutionsForThrowable;
use Throwable;

class MissingAppKeySolutionProvider implements HasSolutionsForThrowable
{
    public function canSolve(Throwable $throwable): bool
    {
        if (! $throwable instanceof RuntimeException) {
            return false;
        }

        return $throwable->getMessage() === 'No application encryption key has been specified.';
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [new GenerateAppKeySolution];
    }
}
