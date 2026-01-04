<?php

declare(strict_types=1);

namespace KodiScript\Ast;

interface Node
{
    public function getType(): string;
}
