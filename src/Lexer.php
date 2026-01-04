<?php

declare(strict_types=1);

namespace KodiScript;

final class Lexer
{
    private const KEYWORDS = [
        'let' => TokenType::LET,
        'if' => TokenType::IF,
        'else' => TokenType::ELSE,
        'return' => TokenType::RETURN,
        'true' => TokenType::TRUE,
        'false' => TokenType::FALSE,
        'null' => TokenType::NULL,
        'and' => TokenType::AND,
        'or' => TokenType::OR,
        'not' => TokenType::NOT,
        'fn' => TokenType::FN,
        'for' => TokenType::FOR,
        'in' => TokenType::IN,
        'while' => TokenType::WHILE,
    ];

    private int $pos = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;

    public function __construct(
        private readonly string $source
    ) {
        $this->length = strlen($source);
    }

    /**
     * @return Token[]
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (!$this->isAtEnd()) {
            $this->skipWhitespaceAndComments();
            if ($this->isAtEnd()) {
                break;
            }

            $token = $this->nextToken();
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens[] = new Token(TokenType::EOF, '', $this->line, $this->column);
        return $tokens;
    }

    private function nextToken(): ?Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $char = $this->current();

        // String
        if ($char === '"' || $char === "'") {
            return $this->readString($char);
        }

        // Number
        if ($this->isDigit($char)) {
            return $this->readNumber();
        }

        // Identifier/Keyword
        if ($this->isAlpha($char)) {
            return $this->readIdentifier();
        }

        // Operators and delimiters
        $this->advance();

        return match ($char) {
            '+' => new Token(TokenType::PLUS, '+', $startLine, $startColumn),
            '-' => new Token(TokenType::MINUS, '-', $startLine, $startColumn),
            '*' => new Token(TokenType::STAR, '*', $startLine, $startColumn),
            '/' => new Token(TokenType::SLASH, '/', $startLine, $startColumn),
            '%' => new Token(TokenType::PERCENT, '%', $startLine, $startColumn),
            '(' => new Token(TokenType::LPAREN, '(', $startLine, $startColumn),
            ')' => new Token(TokenType::RPAREN, ')', $startLine, $startColumn),
            '{' => new Token(TokenType::LBRACE, '{', $startLine, $startColumn),
            '}' => new Token(TokenType::RBRACE, '}', $startLine, $startColumn),
            '[' => new Token(TokenType::LBRACKET, '[', $startLine, $startColumn),
            ']' => new Token(TokenType::RBRACKET, ']', $startLine, $startColumn),
            ',' => new Token(TokenType::COMMA, ',', $startLine, $startColumn),
            '.' => new Token(TokenType::DOT, '.', $startLine, $startColumn),
            ':' => new Token(TokenType::COLON, ':', $startLine, $startColumn),
            ';' => new Token(TokenType::SEMICOLON, ';', $startLine, $startColumn),
            '=' => $this->match('=')
                ? new Token(TokenType::EQ, '==', $startLine, $startColumn)
                : new Token(TokenType::ASSIGN, '=', $startLine, $startColumn),
            '!' => $this->match('=')
                ? new Token(TokenType::NEQ, '!=', $startLine, $startColumn)
                : new Token(TokenType::NOT, '!', $startLine, $startColumn),
            '<' => $this->match('=')
                ? new Token(TokenType::LTE, '<=', $startLine, $startColumn)
                : new Token(TokenType::LT, '<', $startLine, $startColumn),
            '>' => $this->match('=')
                ? new Token(TokenType::GTE, '>=', $startLine, $startColumn)
                : new Token(TokenType::GT, '>', $startLine, $startColumn),
            '&' => $this->match('&')
                ? new Token(TokenType::AND, '&&', $startLine, $startColumn)
                : throw new \RuntimeException("Unexpected character '&' at line {$startLine}, column {$startColumn}"),
            '|' => $this->match('|')
                ? new Token(TokenType::OR, '||', $startLine, $startColumn)
                : throw new \RuntimeException("Unexpected character '|' at line {$startLine}, column {$startColumn}"),
            '?' => $this->handleQuestion($startLine, $startColumn),
            default => throw new \RuntimeException("Unexpected character '{$char}' at line {$startLine}, column {$startColumn}"),
        };
    }

    private function handleQuestion(int $startLine, int $startColumn): Token
    {
        if ($this->match('.')) {
            return new Token(TokenType::QUESTION_DOT, '?.', $startLine, $startColumn);
        }
        if ($this->match(':')) {
            return new Token(TokenType::ELVIS, '?:', $startLine, $startColumn);
        }
        throw new \RuntimeException("Unexpected character '?' at line {$startLine}, column {$startColumn}");
    }

    private function readString(string $quote): Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $this->advance(); // Skip opening quote

        $value = '';
        $isTemplate = false;

        while (!$this->isAtEnd() && $this->current() !== $quote) {
            if ($this->current() === '\\') {
                $this->advance();
                if (!$this->isAtEnd()) {
                    $escaped = $this->current();
                    $value .= match ($escaped) {
                        'n' => "\n",
                        't' => "\t",
                        'r' => "\r",
                        '\\' => '\\',
                        '"' => '"',
                        "'" => "'",
                        '$' => '$',
                        default => $escaped,
                    };
                    $this->advance();
                }
            } elseif ($this->current() === '$' && $this->peek(1) === '{') {
                $isTemplate = true;
                $value .= $this->current();
                $this->advance();
            } else {
                $value .= $this->current();
                $this->advance();
            }
        }

        if ($this->isAtEnd()) {
            throw new \RuntimeException("Unterminated string at line {$startLine}, column {$startColumn}");
        }

        $this->advance(); // Skip closing quote

        $tokenType = $isTemplate ? TokenType::STRING_TEMPLATE : TokenType::STRING;
        return new Token($tokenType, $value, $startLine, $startColumn);
    }

    private function readNumber(): Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $value = '';

        while (!$this->isAtEnd() && $this->isDigit($this->current())) {
            $value .= $this->current();
            $this->advance();
        }

        if (!$this->isAtEnd() && $this->current() === '.' && $this->isDigit($this->peek(1))) {
            $value .= $this->current();
            $this->advance();
            while (!$this->isAtEnd() && $this->isDigit($this->current())) {
                $value .= $this->current();
                $this->advance();
            }
        }

        return new Token(TokenType::NUMBER, $value, $startLine, $startColumn);
    }

    private function readIdentifier(): Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $value = '';

        while (!$this->isAtEnd() && $this->isAlphaNumeric($this->current())) {
            $value .= $this->current();
            $this->advance();
        }

        $type = self::KEYWORDS[$value] ?? TokenType::IDENTIFIER;
        return new Token($type, $value, $startLine, $startColumn);
    }

    private function skipWhitespaceAndComments(): void
    {
        while (!$this->isAtEnd()) {
            $char = $this->current();

            if ($char === ' ' || $char === "\t" || $char === "\r") {
                $this->advance();
            } elseif ($char === "\n") {
                $this->line++;
                $this->column = 0;
                $this->advance();
            } elseif ($char === '/' && $this->peek(1) === '/') {
                while (!$this->isAtEnd() && $this->current() !== "\n") {
                    $this->advance();
                }
            } else {
                break;
            }
        }
    }

    private function current(): string
    {
        return $this->source[$this->pos] ?? "\0";
    }

    private function peek(int $offset): string
    {
        return $this->source[$this->pos + $offset] ?? "\0";
    }

    private function advance(): void
    {
        $this->pos++;
        $this->column++;
    }

    private function match(string $expected): bool
    {
        if ($this->isAtEnd() || $this->current() !== $expected) {
            return false;
        }
        $this->advance();
        return true;
    }

    private function isAtEnd(): bool
    {
        return $this->pos >= $this->length;
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') || $char === '_';
    }

    private function isAlphaNumeric(string $char): bool
    {
        return $this->isAlpha($char) || $this->isDigit($char);
    }
}
