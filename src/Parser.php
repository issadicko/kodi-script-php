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

final class Parser
{
    private int $pos = 0;

    /**
     * @param Token[] $tokens
     */
    public function __construct(
        private readonly array $tokens
    ) {
    }

    public function parse(): Program
    {
        $statements = [];

        while (!$this->isAtEnd()) {
            $this->skipSemicolons();
            if ($this->isAtEnd()) {
                break;
            }

            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $statements[] = $stmt;
            }
        }

        return new Program($statements);
    }

    private function parseStatement(): ?Node
    {
        $this->skipSemicolons();

        if ($this->isAtEnd()) {
            return null;
        }

        return match ($this->current()->type) {
            TokenType::LET => $this->parseLetStatement(),
            TokenType::IF => $this->parseIfStatement(),
            TokenType::RETURN => $this->parseReturnStatement(),
            TokenType::FOR => $this->parseForStatement(),
            TokenType::WHILE => $this->parseWhileStatement(),
            TokenType::TRY => $this->parseTryStatement(),
            TokenType::BREAK => $this->parseBreakStatement(),
            TokenType::CONTINUE => $this->parseContinueStatement(),
            TokenType::LBRACE => $this->parseBlockStatement(),
            TokenType::FN => $this->peek(1)->type === TokenType::IDENTIFIER
                ? $this->parseFunctionDeclaration()
                : $this->parseExpressionStatement(),
            TokenType::IDENTIFIER => match (true) {
                $this->peek(1)->type === TokenType::ASSIGN => $this->parseAssignmentStatement(),
                in_array($this->peek(1)->type, [
                    TokenType::PLUS_EQ,
                    TokenType::MINUS_EQ,
                    TokenType::STAR_EQ,
                    TokenType::SLASH_EQ,
                ], true) => $this->parseCompoundAssignment(),
                in_array($this->peek(1)->type, [
                    TokenType::PLUS_PLUS,
                    TokenType::MINUS_MINUS,
                ], true) => $this->parseIncDecStatement(),
                default => $this->parseExpressionStatement(),
            },
            default => $this->parseExpressionStatement(),
        };
    }

    private function parseCompoundAssignment(): AssignmentStatement
    {
        $name = $this->advance()->value;
        $op = match ($this->advance()->type) {
            TokenType::PLUS_EQ => '+',
            TokenType::MINUS_EQ => '-',
            TokenType::STAR_EQ => '*',
            TokenType::SLASH_EQ => '/',
        };
        $right = $this->parseExpression();
        $this->consumeOptionalSemicolon();
        return new AssignmentStatement($name, new BinaryExpr($op, new Identifier($name), $right));
    }

    private function parseIncDecStatement(): AssignmentStatement
    {
        $name = $this->advance()->value;
        $op = $this->advance()->type === TokenType::PLUS_PLUS ? '+' : '-';
        $this->consumeOptionalSemicolon();
        return new AssignmentStatement(
            $name,
            new BinaryExpr($op, new Identifier($name), new NumberLiteral(1.0))
        );
    }

    private function parseBreakStatement(): BreakStatement
    {
        $this->advance(); // consume 'break'
        $this->consumeOptionalSemicolon();
        return new BreakStatement();
    }

    private function parseContinueStatement(): ContinueStatement
    {
        $this->advance(); // consume 'continue'
        $this->consumeOptionalSemicolon();
        return new ContinueStatement();
    }

    private function parseTryStatement(): TryStatement
    {
        $this->advance(); // consume 'try'
        $body = $this->parseBlockStatement();
        $this->expect(TokenType::CATCH, "Expected 'catch' after try block");

        $catchVar = null;
        if ($this->match(TokenType::LPAREN)) {
            $catchVar = $this->expect(TokenType::IDENTIFIER, "Expected catch variable name")->value;
            $this->expect(TokenType::RPAREN, "Expected ')' after catch variable");
        }

        $catchBlock = $this->parseBlockStatement();
        return new TryStatement($body, $catchVar, $catchBlock);
    }

    private function parseFunctionDeclaration(): LetStatement
    {
        $this->advance(); // consume 'fn'
        $name = $this->advance()->value; // function name

        $this->expect(TokenType::LPAREN, "Expected '(' after function name");
        $parameters = [];
        if (!$this->check(TokenType::RPAREN)) {
            do {
                $paramName = $this->expect(TokenType::IDENTIFIER, "Expected parameter name")->value;
                $parameters[] = new Identifier($paramName);
            } while ($this->match(TokenType::COMMA));
        }
        $this->expect(TokenType::RPAREN, "Expected ')' after parameters");

        $body = $this->parseBlockStatement();
        return new LetStatement($name, new FunctionLiteral($parameters, $body));
    }

    private function parseLetStatement(): Node
    {
        $this->advance(); // consume 'let'

        // Destructuring: let [a, b] = expr  /  let {x, y} = expr
        if ($this->check(TokenType::LBRACKET)) {
            return $this->parseDestructure(true);
        }
        if ($this->check(TokenType::LBRACE)) {
            return $this->parseDestructure(false);
        }

        $name = $this->expect(TokenType::IDENTIFIER, "Expected variable name")->value;
        $this->expect(TokenType::ASSIGN, "Expected '=' after variable name");
        $value = $this->parseExpression();
        $this->consumeOptionalSemicolon();
        return new LetStatement($name, $value);
    }

    private function parseDestructure(bool $isArray): Node
    {
        $open = $isArray ? TokenType::LBRACKET : TokenType::LBRACE;
        $close = $isArray ? TokenType::RBRACKET : TokenType::RBRACE;

        $this->expect($open, "Expected destructuring pattern");
        $names = [];
        if (!$this->check($close)) {
            do {
                $names[] = $this->expect(TokenType::IDENTIFIER, "Expected identifier in destructuring pattern")->value;
            } while ($this->match(TokenType::COMMA));
        }
        $this->expect($close, "Expected closing bracket in destructuring pattern");
        $this->expect(TokenType::ASSIGN, "Expected '=' in destructuring assignment");
        $value = $this->parseExpression();
        $this->consumeOptionalSemicolon();

        return $isArray
            ? new ArrayDestructure($names, $value)
            : new ObjectDestructure($names, $value);
    }

    private function parseAssignmentStatement(): AssignmentStatement
    {
        $name = $this->advance()->value;
        $this->expect(TokenType::ASSIGN, "Expected '='");
        $value = $this->parseExpression();
        $this->consumeOptionalSemicolon();
        return new AssignmentStatement($name, $value);
    }

    private function parseIfStatement(): IfStatement
    {
        $this->advance(); // consume 'if'
        $this->expect(TokenType::LPAREN, "Expected '(' after 'if'");
        $condition = $this->parseExpression();
        $this->expect(TokenType::RPAREN, "Expected ')' after condition");

        $thenBranch = $this->check(TokenType::LBRACE)
            ? $this->parseBlockStatement()
            : $this->parseStatement();

        $elseBranch = null;
        if ($this->match(TokenType::ELSE)) {
            $elseBranch = $this->check(TokenType::LBRACE)
                ? $this->parseBlockStatement()
                : $this->parseStatement();
        }

        return new IfStatement($condition, $thenBranch, $elseBranch);
    }

    private function parseReturnStatement(): ReturnStatement
    {
        $this->advance(); // consume 'return'

        $value = null;
        if (!$this->check(TokenType::SEMICOLON) && !$this->check(TokenType::RBRACE) && !$this->isAtEnd()) {
            $value = $this->parseExpression();
        }

        $this->consumeOptionalSemicolon();
        return new ReturnStatement($value);
    }

    private function parseForStatement(): ForStatement
    {
        $this->advance(); // consume 'for'
        $this->expect(TokenType::LPAREN, "Expected '(' after 'for'");
        $varName = $this->expect(TokenType::IDENTIFIER, "Expected variable name")->value;
        $this->expect(TokenType::IN, "Expected 'in' in for loop");
        $iterable = $this->parseExpression();
        $this->expect(TokenType::RPAREN, "Expected ')' after for expression");
        $body = $this->parseBlockStatement();
        return new ForStatement(new Identifier($varName), $iterable, $body);
    }

    private function parseWhileStatement(): WhileStatement
    {
        $this->advance(); // consume 'while'
        $this->expect(TokenType::LPAREN, "Expected '(' after 'while'");
        $condition = $this->parseExpression();
        $this->expect(TokenType::RPAREN, "Expected ')' after while condition");
        $body = $this->parseBlockStatement();
        return new WhileStatement($condition, $body);
    }

    private function parseBlockStatement(): BlockStatement
    {
        $this->expect(TokenType::LBRACE, "Expected '{'");
        $statements = [];

        while (!$this->check(TokenType::RBRACE) && !$this->isAtEnd()) {
            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $statements[] = $stmt;
            }
        }

        $this->expect(TokenType::RBRACE, "Expected '}'");
        return new BlockStatement($statements);
    }

    private function parseExpressionStatement(): ExpressionStatement
    {
        $expr = $this->parseExpression();
        $this->consumeOptionalSemicolon();
        return new ExpressionStatement($expr);
    }

    private function parseExpression(): Node
    {
        return $this->parseTernary();
    }

    private function parseTernary(): Node
    {
        $condition = $this->parseElvis();

        if ($this->match(TokenType::QUESTION)) {
            $consequent = $this->parseTernary();
            $this->expect(TokenType::COLON, "Expected ':' in ternary expression");
            $alternative = $this->parseTernary();
            return new TernaryExpr($condition, $consequent, $alternative);
        }

        return $condition;
    }

    private function parseElvis(): Node
    {
        $left = $this->parseOr();

        while ($this->match(TokenType::ELVIS)) {
            $right = $this->parseOr();
            $left = new ElvisExpr($left, $right);
        }

        return $left;
    }

    private function parseOr(): Node
    {
        $left = $this->parseAnd();

        while ($this->match(TokenType::OR)) {
            $right = $this->parseAnd();
            $left = new BinaryExpr('||', $left, $right);
        }

        return $left;
    }

    private function parseAnd(): Node
    {
        $left = $this->parseEquality();

        while ($this->match(TokenType::AND)) {
            $right = $this->parseEquality();
            $left = new BinaryExpr('&&', $left, $right);
        }

        return $left;
    }

    private function parseEquality(): Node
    {
        $left = $this->parseComparison();

        while ($this->check(TokenType::EQ) || $this->check(TokenType::NEQ)) {
            $op = $this->advance()->value;
            $right = $this->parseComparison();
            $left = new BinaryExpr($op, $left, $right);
        }

        return $left;
    }

    private function parseComparison(): Node
    {
        $left = $this->parseAdditive();

        while (
            $this->check(TokenType::LT) || $this->check(TokenType::LTE) ||
            $this->check(TokenType::GT) || $this->check(TokenType::GTE)
        ) {
            $op = $this->advance()->value;
            $right = $this->parseAdditive();
            $left = new BinaryExpr($op, $left, $right);
        }

        return $left;
    }

    private function parseAdditive(): Node
    {
        $left = $this->parseMultiplicative();

        while ($this->check(TokenType::PLUS) || $this->check(TokenType::MINUS)) {
            $op = $this->advance()->value;
            $right = $this->parseMultiplicative();
            $left = new BinaryExpr($op, $left, $right);
        }

        return $left;
    }

    private function parseMultiplicative(): Node
    {
        $left = $this->parseUnary();

        while ($this->check(TokenType::STAR) || $this->check(TokenType::SLASH) || $this->check(TokenType::PERCENT)) {
            $op = $this->advance()->value;
            $right = $this->parseUnary();
            $left = new BinaryExpr($op, $left, $right);
        }

        return $left;
    }

    private function parseUnary(): Node
    {
        if ($this->check(TokenType::MINUS) || $this->check(TokenType::NOT)) {
            $op = $this->advance()->value;
            return new UnaryExpr($op, $this->parseUnary());
        }

        return $this->parsePostfix();
    }

    private function parsePostfix(): Node
    {
        $expr = $this->parsePrimary();

        while (true) {
            if ($this->match(TokenType::DOT)) {
                $property = $this->expect(TokenType::IDENTIFIER, "Expected property name")->value;
                $expr = new MemberExpr($expr, $property);
            } elseif ($this->match(TokenType::QUESTION_DOT)) {
                $property = $this->expect(TokenType::IDENTIFIER, "Expected property name")->value;
                $expr = new SafeMemberExpr($expr, $property);
            } elseif ($this->match(TokenType::LPAREN)) {
                $args = [];
                if (!$this->check(TokenType::RPAREN)) {
                    do {
                        $args[] = $this->parseListElement();
                    } while ($this->match(TokenType::COMMA));
                }
                $this->expect(TokenType::RPAREN, "Expected ')' after arguments");
                $expr = new CallExpr($expr, $args);
            } elseif ($this->match(TokenType::LBRACKET)) {
                $index = $this->parseExpression();
                $this->expect(TokenType::RBRACKET, "Expected ']' after index");
                $expr = new IndexExpr($expr, $index);
            } else {
                break;
            }
        }

        return $expr;
    }

    private function parsePrimary(): Node
    {
        $token = $this->current();

        return match ($token->type) {
            TokenType::NUMBER => $this->parseNumber(),
            TokenType::STRING => $this->parseString(),
            TokenType::STRING_TEMPLATE => $this->parseStringTemplate(),
            TokenType::TRUE => $this->parseTrue(),
            TokenType::FALSE => $this->parseFalse(),
            TokenType::NULL => $this->parseNull(),
            TokenType::IDENTIFIER => $this->parseIdentifier(),
            TokenType::LBRACKET => $this->parseArrayLiteral(),
            TokenType::LBRACE => $this->parseObjectLiteral(),
            TokenType::FN => $this->parseFunctionLiteral(),
            TokenType::LPAREN => $this->parseGroupedExpression(),
            default => throw new \RuntimeException("Unexpected token: {$token->type->value}"),
        };
    }

    private function parseNumber(): NumberLiteral
    {
        $value = (float) $this->advance()->value;
        return new NumberLiteral($value);
    }

    private function parseString(): StringLiteral
    {
        return new StringLiteral($this->advance()->value);
    }

    private function parseStringTemplate(): StringTemplate
    {
        $templateValue = $this->advance()->value;
        $parts = [];
        $current = '';
        $i = 0;
        $len = strlen($templateValue);

        while ($i < $len) {
            if ($i < $len - 1 && $templateValue[$i] === '$' && $templateValue[$i + 1] === '{') {
                // Save any text before this interpolation
                if ($current !== '') {
                    $parts[] = new StringLiteral($current);
                    $current = '';
                }

                // Find matching closing brace
                $i += 2; // Skip ${
                $braceCount = 1;
                $exprStr = '';

                while ($i < $len && $braceCount > 0) {
                    if ($templateValue[$i] === '{') {
                        $braceCount++;
                    } elseif ($templateValue[$i] === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            break;
                        }
                    }
                    $exprStr .= $templateValue[$i];
                    $i++;
                }

                if ($braceCount !== 0) {
                    throw new \RuntimeException("Unterminated string template expression");
                }

                $i++; // Skip closing }

                // Parse the expression
                $lexer = new Lexer($exprStr);
                $tokens = $lexer->tokenize();
                $parser = new Parser($tokens);
                $program = $parser->parse();

                if (!empty($program->statements)) {
                    $parts[] = $program->statements[0]->expression ?? $program->statements[0];
                }
            } else {
                $current .= $templateValue[$i];
                $i++;
            }
        }

        // Add any remaining text
        if ($current !== '') {
            $parts[] = new StringLiteral($current);
        }

        return new StringTemplate($parts);
    }

    private function parseTrue(): BooleanLiteral
    {
        $this->advance();
        return new BooleanLiteral(true);
    }

    private function parseFalse(): BooleanLiteral
    {
        $this->advance();
        return new BooleanLiteral(false);
    }

    private function parseNull(): NullLiteral
    {
        $this->advance();
        return new NullLiteral();
    }

    private function parseIdentifier(): Identifier
    {
        return new Identifier($this->advance()->value);
    }

    private function parseArrayLiteral(): ArrayLiteral
    {
        $this->advance(); // consume '['
        $elements = [];

        if (!$this->check(TokenType::RBRACKET)) {
            do {
                $elements[] = $this->parseListElement();
            } while ($this->match(TokenType::COMMA));
        }

        $this->expect(TokenType::RBRACKET, "Expected ']' after array elements");
        return new ArrayLiteral($elements);
    }

    /**
     * Parses one element of an array literal or argument list, allowing a
     * spread element (...expr).
     */
    private function parseListElement(): Node
    {
        if ($this->check(TokenType::ELLIPSIS)) {
            $this->advance(); // consume '...'
            return new SpreadExpr($this->parseExpression());
        }
        return $this->parseExpression();
    }

    private function parseObjectLiteral(): ObjectLiteral
    {
        $this->advance(); // consume '{'
        $properties = [];

        if (!$this->check(TokenType::RBRACE)) {
            do {
                $keyToken = $this->current();
                if ($keyToken->type !== TokenType::IDENTIFIER && $keyToken->type !== TokenType::STRING) {
                    throw new \RuntimeException("Expected property name, got {$keyToken->type->value} at line {$keyToken->line}");
                }
                $key = $this->advance()->value;
                $this->expect(TokenType::COLON, "Expected ':' after property name");
                $value = $this->parseExpression();
                $properties[] = ['key' => $key, 'value' => $value];
            } while ($this->match(TokenType::COMMA));
        }

        $this->expect(TokenType::RBRACE, "Expected '}' after object properties");
        return new ObjectLiteral($properties);
    }

    private function parseFunctionLiteral(): FunctionLiteral
    {
        $this->advance(); // consume 'fn'
        $this->expect(TokenType::LPAREN, "Expected '(' after 'fn'");

        $parameters = [];
        if (!$this->check(TokenType::RPAREN)) {
            do {
                $paramName = $this->expect(TokenType::IDENTIFIER, "Expected parameter name")->value;
                $parameters[] = new Identifier($paramName);
            } while ($this->match(TokenType::COMMA));
        }

        $this->expect(TokenType::RPAREN, "Expected ')' after parameters");
        $body = $this->parseBlockStatement();

        return new FunctionLiteral($parameters, $body);
    }

    private function parseGroupedExpression(): Node
    {
        $this->advance(); // consume '('
        $expr = $this->parseExpression();
        $this->expect(TokenType::RPAREN, "Expected ')' after expression");
        return $expr;
    }

    private function skipSemicolons(): void
    {
        while ($this->match(TokenType::SEMICOLON)) {
            // Skip
        }
    }

    private function consumeOptionalSemicolon(): void
    {
        $this->match(TokenType::SEMICOLON);
    }

    private function current(): Token
    {
        return $this->tokens[$this->pos] ?? new Token(TokenType::EOF, '', 0, 0);
    }

    private function peek(int $offset): Token
    {
        return $this->tokens[$this->pos + $offset] ?? new Token(TokenType::EOF, '', 0, 0);
    }

    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function match(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function advance(): Token
    {
        return $this->tokens[$this->pos++];
    }

    private function expect(TokenType $type, string $message): Token
    {
        if (!$this->check($type)) {
            $current = $this->current();
            throw new \RuntimeException("{$message}, got {$current->type->value} at line {$current->line}");
        }
        return $this->advance();
    }

    private function isAtEnd(): bool
    {
        return $this->current()->type === TokenType::EOF;
    }
}
