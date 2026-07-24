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
    TernaryExpr,
    SpreadExpr,
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
    BreakStatement,
    ContinueStatement,
    TryStatement,
    ArrayDestructure,
    ObjectDestructure,
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

class BreakException extends \Exception
{
    public function __construct()
    {
        parent::__construct('break');
    }
}

class ContinueException extends \Exception
{
    public function __construct()
    {
        parent::__construct('continue');
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
    private int $callDepth = 0;

    /** Maximum nested user-function call depth (recursion guard). */
    private const MAX_CALL_DEPTH = 1000;

    /** @var (callable(string): void)|null */
    private $outputSink = null;

    public function __construct(
        private readonly ?Natives $natives = null
    ) {
    }

    public function setOutputSink(?callable $sink): void
    {
        $this->outputSink = $sink;
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
        } catch (BreakException | ContinueException $e) {
            // A stray break/continue used outside any loop is ignored.
            return new ScriptResult($this->output, null);
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
            'TernaryExpr' => $this->evaluateTernaryExpr($node),
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
            'BreakStatement' => throw new BreakException(),
            'ContinueStatement' => throw new ContinueException(),
            'TryStatement' => $this->evaluateTryStatement($node),
            'ArrayDestructure' => $this->evaluateArrayDestructure($node),
            'ObjectDestructure' => $this->evaluateObjectDestructure($node),
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

        // Short-circuit logical operators (mirror Go: return boolean truthiness).
        if ($node->operator === '&&') {
            if (!$this->isTruthy($left)) {
                return false;
            }
            return $this->isTruthy($this->evaluate($node->right));
        }
        if ($node->operator === '||') {
            if ($this->isTruthy($left)) {
                return true;
            }
            return $this->isTruthy($this->evaluate($node->right));
        }

        $right = $this->evaluate($node->right);

        return match ($node->operator) {
            // KodiScript uses + for both numeric addition and string concatenation:
            // if either operand is a string, concatenate using canonical stringification.
            '+' => is_string($left) || is_string($right)
            ? $this->stringify($left) . $this->stringify($right)
            : (float) $left + (float) $right,
            '-' => (float) $left - (float) $right,
            '*' => (float) $left * (float) $right,
            '/' => (float) $right !== 0.0 ? (float) $left / (float) $right : throw new \RuntimeException("division by zero"),
            '%' => (float) $right !== 0.0 ? fmod((float) $left, (float) $right) : throw new \RuntimeException("modulo by zero"),
            '==' => $left == $right,
            '!=' => $left != $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
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
        // Method-call syntax: receiver.method(args)
        if ($node->callee instanceof MemberExpr) {
            return $this->evaluateMethodCall($node->callee, $node->args);
        }

        $callee = $this->evaluate($node->callee);
        $args = $this->evaluateArgs($node->args);

        return $this->callValue($callee, $args);
    }

    /**
     * Implements method-call syntax receiver.method(args), mirroring Go's
     * evalMethodCall dispatch order:
     *   1. a callable property stored on an object (map) wins,
     *   2. a native (includes higher-order builtins) with the receiver prepended,
     *   3. a bound PHP object's method/callable field via reflection.
     */
    private function evaluateMethodCall(MemberExpr $callee, array $argNodes): mixed
    {
        $receiver = $this->evaluate($callee->object);
        $method = $callee->property;
        $args = $this->evaluateArgs($argNodes);

        // 1. A callable stored under that key on an object (map).
        if (is_array($receiver) && !array_is_list($receiver) && array_key_exists($method, $receiver)) {
            $value = $receiver[$method];
            if ($value instanceof FunctionValue || is_callable($value)) {
                return $this->callValue($value, $args);
            }
        }

        $withReceiver = array_merge([$receiver], $args);

        // 2. A custom host function or native invoked as a method: prepend receiver.
        if (isset($this->customFunctions[$method])) {
            return ($this->customFunctions[$method])(...$withReceiver);
        }

        $natives = $this->natives ?? Natives::instance();
        if ($natives->has($method)) {
            $fn = $natives->get($method);
            return $fn(...$withReceiver);
        }

        // 3. Bound PHP object: method or callable field.
        if (is_object($receiver)) {
            if (method_exists($receiver, $method)) {
                return $receiver->$method(...$args);
            }
            if (isset($receiver->$method) && is_callable($receiver->$method)) {
                return ($receiver->$method)(...$args);
            }
        }

        if ($receiver === null) {
            throw new \RuntimeException("cannot call method '{$method}' on null");
        }

        throw new \RuntimeException("undefined method '{$method}'");
    }

    /**
     * Evaluates a list of argument/element expressions, expanding ...spread.
     *
     * @param Node[] $nodes
     * @return list<mixed>
     */
    private function evaluateArgs(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($node instanceof SpreadExpr) {
                $value = $this->evaluate($node->value);
                if (!is_array($value)) {
                    throw new \RuntimeException("spread operator requires an array");
                }
                foreach ($value as $element) {
                    $result[] = $element;
                }
            } else {
                $result[] = $this->evaluate($node);
            }
        }
        return $result;
    }

    private function callValue(mixed $callee, array $args): mixed
    {
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
        // Recursion guard: bound before PHP exhausts its native stack.
        if ($this->callDepth >= self::MAX_CALL_DEPTH) {
            throw new \RuntimeException("maximum call depth exceeded");
        }

        $savedVariables = $this->variables;
        $this->callDepth++;

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
        } catch (BreakException | ContinueException $e) {
            // A stray break/continue must not escape the function as a value.
            return null;
        } finally {
            $this->callDepth--;
            $this->variables = $savedVariables;
        }
    }

    /**
     * Public method to apply a function value from native functions
     */
    public function applyFunctionValue(FunctionValue $fn, array $args): mixed
    {
        return $this->applyFunction($fn, $args);
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

    private function evaluateTernaryExpr(TernaryExpr $node): mixed
    {
        if ($this->isTruthy($this->evaluate($node->condition))) {
            return $this->evaluate($node->consequent);
        }
        return $this->evaluate($node->alternative);
    }

    private function evaluateTryStatement(TryStatement $node): mixed
    {
        try {
            return $this->evaluate($node->body);
        } catch (ReturnException | BreakException | ContinueException | LimitsExceededException $e) {
            // return / break / continue / limit signals are not catchable errors.
            throw $e;
        } catch (\Throwable $e) {
            if ($node->catchVar !== null) {
                $this->variables[$node->catchVar] = $e->getMessage();
            }
            return $this->evaluate($node->catchBlock);
        }
    }

    private function evaluateArrayDestructure(ArrayDestructure $node): mixed
    {
        $value = $this->evaluate($node->value);
        if (!is_array($value)) {
            throw new \RuntimeException("cannot destructure non-array value");
        }
        $values = array_values($value);
        foreach ($node->names as $i => $name) {
            $this->variables[$name] = $values[$i] ?? null;
        }
        return $value;
    }

    private function evaluateObjectDestructure(ObjectDestructure $node): mixed
    {
        $value = $this->evaluate($node->value);
        if (!is_array($value)) {
            throw new \RuntimeException("cannot destructure non-object value");
        }
        foreach ($node->names as $name) {
            $this->variables[$name] = $value[$name] ?? null;
        }
        return $value;
    }

    private function evaluateArrayLiteral(ArrayLiteral $node): array
    {
        return $this->evaluateArgs($node->elements);
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
            } catch (BreakException $e) {
                break;
            } catch (ContinueException $e) {
                continue;
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
            } catch (BreakException $e) {
                break;
            } catch (ContinueException $e) {
                continue;
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
        return Natives::stringify($value);
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
        if ($this->outputSink !== null) {
            ($this->outputSink)($line);
        }
        $this->output[] = $line;
    }
}
