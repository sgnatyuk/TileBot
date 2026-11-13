<?php

namespace App\Services;

class AnalysisResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $fullResult = '',
        public readonly string $description = '',
        public readonly string $reason = '',
    ) {}
}
