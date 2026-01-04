<?php

declare(strict_types=1);

namespace KodiScript\Ast;

final class NumberLiteral implements Node
{
    public function __construct(
        public readonly float $value
    ) {
    }

    public function getType(): string
    {
        return 'NumberLiteral';
    }
}

final class StringLiteral implements Node
{
    public function __construct(
        public readonly string $value
    ) {
    }

    public function getType(): string
    {
        return 'StringLiteral';
    }
}

final class StringTemplate implements Node
{
    /**
     * @param Node[] $parts
     */
    public function __construct(
        public readonly array $parts
    ) {
    }

    public function getType(): string
    {
        return 'StringTemplate';
    }
}

final class BooleanLiteral implements Node
{
    public function __construct(
        public readonly bool $value
    ) {
    }

    public function getType(): string
    {
        return 'BooleanLiteral';
    }
}

final class NullLiteral implements Node
{
    public function getType(): string
    {
        return 'NullLiteral';
    }
}

final class Identifier implements Node
{
    public function __construct(
        public readonly string $name
    ) {
    }

    public function getType(): string
    {
        return 'Identifier';
    }
}

final class BinaryExpr implements Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $left,
        public readonly Node $right
    ) {
    }

    public function getType(): string
    {
        return 'BinaryExpr';
    }
}

final class UnaryExpr implements Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $operand
    ) {
    }

    public function getType(): string
    {
        return 'UnaryExpr';
    }
}

final class CallExpr implements Node
{
    /**
     * @param Node[] $args
     */
    public function __construct(
        public readonly Node $callee,
        public readonly array $args
    ) {
    }

    public function getType(): string
    {
        return 'CallExpr';
    }
}

final class MemberExpr implements Node
{
    public function __construct(
        public readonly Node $object,
        public readonly string $property
    ) {
    }

    public function getType(): string
    {
        return 'MemberExpr';
    }
}

final class SafeMemberExpr implements Node
{
    public function __construct(
        public readonly Node $object,
        public readonly string $property
    ) {
    }

    public function getType(): string
    {
        return 'SafeMemberExpr';
    }
}

final class ElvisExpr implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly Node $right
    ) {
    }

    public function getType(): string
    {
        return 'ElvisExpr';
    }
}

final class ArrayLiteral implements Node
{
    /**
     * @param Node[] $elements
     */
    public function __construct(
        public readonly array $elements
    ) {
    }

    public function getType(): string
    {
        return 'ArrayLiteral';
    }
}

final class ObjectLiteral implements Node
{
    /**
     * @param array<array{key: string, value: Node}> $properties
     */
    public function __construct(
        public readonly array $properties
    ) {
    }

    public function getType(): string
    {
        return 'ObjectLiteral';
    }
}

final class IndexExpr implements Node
{
    public function __construct(
        public readonly Node $object,
        public readonly Node $index
    ) {
    }

    public function getType(): string
    {
        return 'IndexExpr';
    }
}

final class FunctionLiteral implements Node
{
    /**
     * @param Identifier[] $parameters
     */
    public function __construct(
        public readonly array $parameters,
        public readonly BlockStatement $body
    ) {
    }

    public function getType(): string
    {
        return 'FunctionLiteral';
    }
}

final class LetStatement implements Node
{
    public function __construct(
        public readonly string $name,
        public readonly Node $value
    ) {
    }

    public function getType(): string
    {
        return 'LetStatement';
    }
}

final class AssignmentStatement implements Node
{
    public function __construct(
        public readonly string $name,
        public readonly Node $value
    ) {
    }

    public function getType(): string
    {
        return 'AssignmentStatement';
    }
}

final class IfStatement implements Node
{
    public function __construct(
        public readonly Node $condition,
        public readonly Node $thenBranch,
        public readonly ?Node $elseBranch
    ) {
    }

    public function getType(): string
    {
        return 'IfStatement';
    }
}

final class ForStatement implements Node
{
    public function __construct(
        public readonly Identifier $variable,
        public readonly Node $iterable,
        public readonly BlockStatement $body
    ) {
    }

    public function getType(): string
    {
        return 'ForStatement';
    }
}

final class WhileStatement implements Node
{
    public function __construct(
        public readonly Node $condition,
        public readonly BlockStatement $body
    ) {
    }

    public function getType(): string
    {
        return 'WhileStatement';
    }
}

final class ReturnStatement implements Node
{
    public function __construct(
        public readonly ?Node $value
    ) {
    }

    public function getType(): string
    {
        return 'ReturnStatement';
    }
}

final class BlockStatement implements Node
{
    /**
     * @param Node[] $statements
     */
    public function __construct(
        public readonly array $statements
    ) {
    }

    public function getType(): string
    {
        return 'BlockStatement';
    }
}

final class ExpressionStatement implements Node
{
    public function __construct(
        public readonly Node $expression
    ) {
    }

    public function getType(): string
    {
        return 'ExpressionStatement';
    }
}

final class Program implements Node
{
    /**
     * @param Node[] $statements
     */
    public function __construct(
        public readonly array $statements
    ) {
    }

    public function getType(): string
    {
        return 'Program';
    }
}
