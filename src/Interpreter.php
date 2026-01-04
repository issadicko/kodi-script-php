<?php

declare(strict_types=1);

namespace KodiScript;

use KodiScript\Ast\{
    Node,
    NumberLiteral,
    StringLiteral,
    StringTemplate,
    BooleanLiteral,
    NullLiteral,
    Identifier,
    BinaryExpr,
    UnaryExpr,
    CallExpr,
    MemberExpr,
    SafeMemberExpr,
    ElvisExpr,
    ArrayLiteral,
    ObjectLiteral,
    IndexExpr,
    FunctionLiteral,
    LetStatement,
    AssignmentStatement,
    IfStatement,
    ForStatement,
    WhileStatement,
    ReturnStatement,
    BlockStatement,
    ExpressionStatement,
    Program
};

class ReturnException extends \Exception
{
    public function __construct(public readonly mixed $value)
    {
        parent::__construct('return');
    }
}

class LimitsExceededException extends \RuntimeException
{
    public function __construct(string $message = 'Execution limits exceeded')
    {
        parent::__construct($message);
    }
}

final class FunctionValue
{
    /**
     * @param Identifier[] $parameters
     * @param array<string, mixed> $closure
     */
    public function __construct(
        public readonly array $parameters,
        public readonly BlockStatement $body,
        public readonly array $closure
    ) {
    }
}

final class Interpreter
{
    /** @var array<string, mixed> */
    private array $variables = [];

    /** @var array<string, callable> */
    private array $customFunctions = [];

    /** @var string[] */
    private array $output = [];

    private int $opCount = 0;
    private int $maxOps = 0;
    private ?float $deadline = null;

    public function __construct(
        private readonly ?Natives $natives = null
    ) {
    }

    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function setVariables(array $vars): void
    {
        foreach ($vars as $name => $value) {
            $this->variables[$name] = $value;
        }
    }

    public function registerFunction(string $name, callable $fn): void
    {
        $this->customFunctions[$name] = $fn;
    }

    public function setMaxOperations(int $maxOps): void
    {
        $this->maxOps = $maxOps;
    }

    public function setDeadline(float $deadline): void
    {
        $this->deadline = $deadline;
    }

    private function checkLimits(): void
    {
        $this->opCount++;

        if ($this->maxOps > 0 && $this->opCount > $this->maxOps) {
            throw new LimitsExceededException('Max operations exceeded');
        }

        if ($this->deadline !== null && microtime(true) * 1000 > $this->deadline) {
            throw new LimitsExceededException('Execution timeout');
        }
    }

    public function run(Program $program): ScriptResult
    {
        $this->output = [];
        $this->opCount = 0;

        try {
            $result = null;
            foreach ($program->statements as $stmt) {
                $result = $this->evaluate($stmt);
            }
            return new ScriptResult($this->output, $result);
        } catch (ReturnException $e) {
            return new ScriptResult($this->output, $e->value);
        }
    }

    public function evaluate(Node $node): mixed
    {
        $this->checkLimits();

        return match ($node->getType()) {
            'NumberLiteral' => $this->evaluateNumberLiteral($node),
            'StringLiteral' => $this->evaluateStringLiteral($node),
            'StringTemplate' => $this->evaluateStringTemplate($node),
            'BooleanLiteral' => $this->evaluateBooleanLiteral($node),
            'NullLiteral' => null,
            'Identifier' => $this->evaluateIdentifier($node),
            'BinaryExpr' => $this->evaluateBinaryExpr($node),
            'UnaryExpr' => $this->evaluateUnaryExpr($node),
            'CallExpr' => $this->evaluateCallExpr($node),
            'MemberExpr' => $this->evaluateMemberExpr($node),
            'SafeMemberExpr' => $this->evaluateSafeMemberExpr($node),
            'ElvisExpr' => $this->evaluateElvisExpr($node),
            'ArrayLiteral' => $this->evaluateArrayLiteral($node),
            'ObjectLiteral' => $this->evaluateObjectLiteral($node),
            'IndexExpr' => $this->evaluateIndexExpr($node),
            'FunctionLiteral' => $this->evaluateFunctionLiteral($node),
            'LetStatement' => $this->evaluateLetStatement($node),
            'AssignmentStatement' => $this->evaluateAssignmentStatement($node),
            'IfStatement' => $this->evaluateIfStatement($node),
            'ForStatement' => $this->evaluateForStatement($node),
            'WhileStatement' => $this->evaluateWhileStatement($node),
            'ReturnStatement' => $this->evaluateReturnStatement($node),
            'BlockStatement' => $this->evaluateBlockStatement($node),
            'ExpressionStatement' => $this->evaluateExpressionStatement($node),
            'Program' => $this->run($node)->value,
            default => throw new \RuntimeException("Unknown node type: " . $node->getType()),
        };
    }

    private function evaluateNumberLiteral(NumberLiteral $node): float
    {
        return $node->value;
    }

    private function evaluateStringLiteral(StringLiteral $node): string
    {
        return $node->value;
    }

    private function evaluateStringTemplate(StringTemplate $node): string
    {
        $result = '';
        foreach ($node->parts as $part) {
            $result .= $this->stringify($this->evaluate($part));
        }
        return $result;
    }

    private function evaluateBooleanLiteral(BooleanLiteral $node): bool
    {
        return $node->value;
    }

    private function evaluateIdentifier(Identifier $node): mixed
    {
        $name = $node->name;

        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        }

        if (isset($this->customFunctions[$name])) {
            return $this->customFunctions[$name];
        }

        $natives = $this->natives ?? Natives::instance();
        if ($natives->has($name)) {
            return $natives->get($name);
        }

        throw new \RuntimeException("Undefined variable: {$name}");
    }

    private function evaluateBinaryExpr(BinaryExpr $node): mixed
    {
        $left = $this->evaluate($node->left);
        $right = $this->evaluate($node->right);

        return match ($node->operator) {
            '+' => is_string($left) || is_string($right)
            ? $this->stringify($left) . $this->stringify($right)
            : (float) $left + (float) $right,
            '-' => (float) $left - (float) $right,
            '*' => (float) $left * (float) $right,
            '/' => (float) $right !== 0.0 ? (float) $left / (float) $right : throw new \RuntimeException("Division by zero"),
            '%' => (float) $left % (float) $right,
            '==' => $left === $right,
            '!=' => $left !== $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '&&' => $this->isTruthy($left) && $this->isTruthy($right),
            '||' => $this->isTruthy($left) || $this->isTruthy($right),
            default => throw new \RuntimeException("Unknown operator: {$node->operator}"),
        };
    }

    private function evaluateUnaryExpr(UnaryExpr $node): mixed
    {
        $operand = $this->evaluate($node->operand);

        return match ($node->operator) {
            '-' => -(float) $operand,
            '!' => !$this->isTruthy($operand),
            'not' => !$this->isTruthy($operand),
            default => throw new \RuntimeException("Unknown unary operator: {$node->operator}"),
        };
    }

    private function evaluateCallExpr(CallExpr $node): mixed
    {
        $callee = $this->evaluate($node->callee);

        $args = array_map(fn($arg) => $this->evaluate($arg), $node->args);

        if ($callee instanceof FunctionValue) {
            return $this->applyFunction($callee, $args);
        }

        if (is_callable($callee)) {
            return $callee(...$args);
        }

        throw new \RuntimeException("Cannot call non-function");
    }

    private function applyFunction(FunctionValue $fn, array $args): mixed
    {
        $savedVariables = $this->variables;

        // Apply closure
        foreach ($fn->closure as $name => $value) {
            $this->variables[$name] = $value;
        }

        // Bind parameters
        foreach ($fn->parameters as $i => $param) {
            $this->variables[$param->name] = $args[$i] ?? null;
        }

        try {
            $result = null;
            foreach ($fn->body->statements as $stmt) {
                $result = $this->evaluate($stmt);
            }
            return $result;
        } catch (ReturnException $e) {
            return $e->value;
        } finally {
            $this->variables = $savedVariables;
        }
    }

    private function evaluateMemberExpr(MemberExpr $node): mixed
    {
        $object = $this->evaluate($node->object);
        $property = $node->property;

        if (is_array($object)) {
            return $object[$property] ?? null;
        }

        if (is_object($object)) {
            return $object->$property ?? null;
        }

        throw new \RuntimeException("Cannot access property of non-object");
    }

    private function evaluateSafeMemberExpr(SafeMemberExpr $node): mixed
    {
        $object = $this->evaluate($node->object);

        if ($object === null) {
            return null;
        }

        $property = $node->property;

        if (is_array($object)) {
            return $object[$property] ?? null;
        }

        if (is_object($object)) {
            return $object->$property ?? null;
        }

        return null;
    }

    private function evaluateElvisExpr(ElvisExpr $node): mixed
    {
        $left = $this->evaluate($node->left);

        if ($left !== null) {
            return $left;
        }

        return $this->evaluate($node->right);
    }

    private function evaluateArrayLiteral(ArrayLiteral $node): array
    {
        return array_map(fn($el) => $this->evaluate($el), $node->elements);
    }

    private function evaluateObjectLiteral(ObjectLiteral $node): array
    {
        $result = [];
        foreach ($node->properties as $prop) {
            $result[$prop['key']] = $this->evaluate($prop['value']);
        }
        return $result;
    }

    private function evaluateIndexExpr(IndexExpr $node): mixed
    {
        $object = $this->evaluate($node->object);
        $index = $this->evaluate($node->index);

        if (is_array($object)) {
            return $object[$index] ?? null;
        }

        if (is_string($object) && is_numeric($index)) {
            return $object[(int) $index] ?? null;
        }

        return null;
    }

    private function evaluateFunctionLiteral(FunctionLiteral $node): FunctionValue
    {
        return new FunctionValue($node->parameters, $node->body, $this->variables);
    }

    private function evaluateLetStatement(LetStatement $node): mixed
    {
        $value = $this->evaluate($node->value);
        $this->variables[$node->name] = $value;
        return $value;
    }

    private function evaluateAssignmentStatement(AssignmentStatement $node): mixed
    {
        $value = $this->evaluate($node->value);
        $this->variables[$node->name] = $value;
        return $value;
    }

    private function evaluateIfStatement(IfStatement $node): mixed
    {
        $condition = $this->evaluate($node->condition);

        if ($this->isTruthy($condition)) {
            return $this->evaluate($node->thenBranch);
        } elseif ($node->elseBranch !== null) {
            return $this->evaluate($node->elseBranch);
        }

        return null;
    }

    private function evaluateForStatement(ForStatement $node): mixed
    {
        $iterable = $this->evaluate($node->iterable);

        if (!is_iterable($iterable)) {
            throw new \RuntimeException("Cannot iterate over non-iterable");
        }

        $result = null;
        foreach ($iterable as $item) {
            $this->variables[$node->variable->name] = $item;
            try {
                $result = $this->evaluate($node->body);
            } catch (ReturnException $e) {
                throw $e;
            }
        }

        return $result;
    }

    private function evaluateWhileStatement(WhileStatement $node): mixed
    {
        $result = null;

        while ($this->isTruthy($this->evaluate($node->condition))) {
            try {
                $result = $this->evaluate($node->body);
            } catch (ReturnException $e) {
                throw $e;
            }
        }

        return $result;
    }

    private function evaluateReturnStatement(ReturnStatement $node): never
    {
        $value = $node->value !== null ? $this->evaluate($node->value) : null;
        throw new ReturnException($value);
    }

    private function evaluateBlockStatement(BlockStatement $node): mixed
    {
        $result = null;
        foreach ($node->statements as $stmt) {
            $result = $this->evaluate($stmt);
        }
        return $result;
    }

    private function evaluateExpressionStatement(ExpressionStatement $node): mixed
    {
        return $this->evaluate($node->expression);
    }


    private function isTruthy(mixed $value): bool
    {
        if ($value === null)
            return false;
        if ($value === false)
            return false;
        if ($value === 0 || $value === 0.0)
            return false;
        if ($value === '')
            return false;
        return true;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null)
            return 'null';
        if (is_bool($value))
            return $value ? 'true' : 'false';
        if (is_array($value))
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        return (string) $value;
    }

    /**
     * @return string[]
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    public function addOutput(string $line): void
    {
        $this->output[] = $line;
    }
}
