<?php

declare(strict_types=1);

namespace KodiScript;

final class ScriptResult
{
    /**
     * @param string[] $output
     * @param string[] $errors
     */
    public function __construct(
        public readonly array $output,
        public readonly mixed $value,
        public readonly array $errors = []
    ) {
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
