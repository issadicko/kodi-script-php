<?php

declare(strict_types=1);

namespace KodiScript\Tests;

use PHPUnit\Framework\TestCase;
use KodiScript\Lexer;
use KodiScript\TokenType;

final class LexerTest extends TestCase
{
    public function testTokenizesNumbers(): void
    {
        $lexer = new Lexer('42 3.14');
        $tokens = $lexer->tokenize();

        $this->assertCount(3, $tokens); // 2 numbers + EOF
        $this->assertSame(TokenType::NUMBER, $tokens[0]->type);
        $this->assertSame('42', $tokens[0]->value);
        $this->assertSame(TokenType::NUMBER, $tokens[1]->type);
        $this->assertSame('3.14', $tokens[1]->value);
    }

    public function testTokenizesStrings(): void
    {
        $lexer = new Lexer('"hello" \'world\'');
        $tokens = $lexer->tokenize();

        $this->assertCount(3, $tokens);
        $this->assertSame(TokenType::STRING, $tokens[0]->type);
        $this->assertSame('hello', $tokens[0]->value);
        $this->assertSame(TokenType::STRING, $tokens[1]->type);
        $this->assertSame('world', $tokens[1]->value);
    }

    public function testTokenizesOperators(): void
    {
        $lexer = new Lexer('+ - * / == != < <= > >=');
        $tokens = $lexer->tokenize();

        $expectedTypes = [
            TokenType::PLUS,
            TokenType::MINUS,
            TokenType::STAR,
            TokenType::SLASH,
            TokenType::EQ,
            TokenType::NEQ,
            TokenType::LT,
            TokenType::LTE,
            TokenType::GT,
            TokenType::GTE,
            TokenType::EOF,
        ];

        $this->assertCount(count($expectedTypes), $tokens);
        foreach ($expectedTypes as $i => $type) {
            $this->assertSame($type, $tokens[$i]->type);
        }
    }

    public function testTokenizesKeywords(): void
    {
        $lexer = new Lexer('let if else return fn for in while true false null');
        $tokens = $lexer->tokenize();

        $expectedTypes = [
            TokenType::LET,
            TokenType::IF ,
            TokenType::ELSE ,
            TokenType::RETURN ,
            TokenType::FN,
            TokenType::FOR ,
            TokenType::IN,
            TokenType::WHILE ,
            TokenType::TRUE,
            TokenType::FALSE,
            TokenType::NULL,
            TokenType::EOF,
        ];

        $this->assertCount(count($expectedTypes), $tokens);
        foreach ($expectedTypes as $i => $type) {
            $this->assertSame($type, $tokens[$i]->type);
        }
    }

    public function testTokenizesNullSafetyOperators(): void
    {
        $lexer = new Lexer('?. ?:');
        $tokens = $lexer->tokenize();

        $this->assertCount(3, $tokens);
        $this->assertSame(TokenType::QUESTION_DOT, $tokens[0]->type);
        $this->assertSame(TokenType::ELVIS, $tokens[1]->type);
    }

    public function testSkipsComments(): void
    {
        $lexer = new Lexer("42 // this is a comment\n100");
        $tokens = $lexer->tokenize();

        $this->assertCount(3, $tokens);
        $this->assertSame('42', $tokens[0]->value);
        $this->assertSame('100', $tokens[1]->value);
    }
}
