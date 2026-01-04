<?php

declare(strict_types=1);

namespace KodiScript;

final class KodiScriptBuilder
{
    private string $source;

    /** @var array<string, mixed> */
    private array $variables = [];

    /** @var array<string, callable> */
    private array $functions = [];

    private int $maxOps = 0;
    private int $timeout = 0;

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    public function withVariable(string $name, mixed $value): self
    {
        $this->variables[$name] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function withVariables(array $vars): self
    {
        foreach ($vars as $name => $value) {
            $this->variables[$name] = $value;
        }
        return $this;
    }

    public function registerFunction(string $name, callable $fn): self
    {
        $this->functions[$name] = $fn;
        return $this;
    }

    public function bind(string $name, mixed $obj): self
    {
        $this->variables[$name] = $obj;
        return $this;
    }

    public function withMaxOperations(int $maxOps): self
    {
        $this->maxOps = $maxOps;
        return $this;
    }

    public function withTimeout(int $timeoutMs): self
    {
        $this->timeout = $timeoutMs;
        return $this;
    }

    public function execute(): ScriptResult
    {
        try {
            $lexer = new Lexer($this->source);
            $tokens = $lexer->tokenize();

            $parser = new Parser($tokens);
            $ast = $parser->parse();

            $natives = Natives::instance();
            $interpreter = new Interpreter($natives);
            $natives->setInterpreter($interpreter);

            $interpreter->setVariables($this->variables);

            if ($this->maxOps > 0) {
                $interpreter->setMaxOperations($this->maxOps);
            }

            if ($this->timeout > 0) {
                $interpreter->setDeadline(microtime(true) * 1000 + $this->timeout);
            }

            foreach ($this->functions as $name => $fn) {
                $interpreter->registerFunction($name, $fn);
            }

            return $interpreter->run($ast);
        } catch (\Throwable $e) {
            return new ScriptResult([], null, [$e->getMessage()]);
        }
    }
}

final class KodiScript
{
    /**
     * Run a script with optional variables
     *
     * @param array<string, mixed> $variables
     */
    public static function run(string $source, array $variables = []): ScriptResult
    {
        return self::builder($source)
            ->withVariables($variables)
            ->execute();
    }

    /**
     * Evaluate an expression and return the result value
     *
     * @param array<string, mixed> $variables
     * @throws \RuntimeException on error
     */
    public static function eval(string $source, array $variables = []): mixed
    {
        $result = self::run($source, $variables);

        if ($result->hasErrors()) {
            throw new \RuntimeException($result->errors[0]);
        }

        return $result->value;
    }

    /**
     * Create a builder for advanced configuration
     */
    public static function builder(string $source): KodiScriptBuilder
    {
        return new KodiScriptBuilder($source);
    }
}
