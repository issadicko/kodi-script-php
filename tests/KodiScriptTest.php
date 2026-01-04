<?php

declare(strict_types=1);

namespace KodiScript\Tests;

use PHPUnit\Framework\TestCase;
use KodiScript\KodiScript;

final class KodiScriptTest extends TestCase
{
    public function testSimpleArithmetic(): void
    {
        $this->assertEquals(14, KodiScript::eval('2 + 3 * 4'));
        $this->assertEquals(20, KodiScript::eval('(2 + 3) * 4'));
        $this->assertEquals(5, KodiScript::eval('10 / 2'));
        $this->assertEquals(1, KodiScript::eval('10 % 3'));
    }

    public function testVariables(): void
    {
        $this->assertSame(42.0, KodiScript::eval('let x = 42; x'));
        $this->assertSame(100.0, KodiScript::eval('let x = 10; x = 100; x'));
    }

    public function testVariableInjection(): void
    {
        $result = KodiScript::eval('x + y', ['x' => 10, 'y' => 20]);
        $this->assertSame(30.0, $result);
    }

    public function testStrings(): void
    {
        $this->assertSame('Hello World', KodiScript::eval('"Hello" + " " + "World"'));
    }

    public function testBooleans(): void
    {
        $this->assertTrue(KodiScript::eval('true'));
        $this->assertFalse(KodiScript::eval('false'));
        $this->assertTrue(KodiScript::eval('5 > 3'));
        $this->assertFalse(KodiScript::eval('5 < 3'));
    }

    public function testComparisons(): void
    {
        $this->assertTrue(KodiScript::eval('10 == 10'));
        $this->assertTrue(KodiScript::eval('10 != 5'));
        $this->assertTrue(KodiScript::eval('10 >= 10'));
        $this->assertTrue(KodiScript::eval('10 <= 10'));
    }

    public function testLogicalOperators(): void
    {
        $this->assertTrue(KodiScript::eval('true and true'));
        $this->assertFalse(KodiScript::eval('true and false'));
        $this->assertTrue(KodiScript::eval('true or false'));
        $this->assertFalse(KodiScript::eval('not true'));
    }

    public function testIfStatement(): void
    {
        $code = 'let x = 10; if (x > 5) { "big" } else { "small" }';
        $this->assertSame('big', KodiScript::eval($code));

        $code = 'let x = 3; if (x > 5) { "big" } else { "small" }';
        $this->assertSame('small', KodiScript::eval($code));
    }

    public function testForLoop(): void
    {
        $code = '
            let sum = 0
            for (i in [1, 2, 3, 4, 5]) {
                sum = sum + i
            }
            sum
        ';
        $this->assertSame(15.0, KodiScript::eval($code));
    }

    public function testWhileLoop(): void
    {
        $code = '
            let i = 0
            let sum = 0
            while (i < 5) {
                sum = sum + i
                i = i + 1
            }
            sum
        ';
        $this->assertSame(10.0, KodiScript::eval($code));
    }

    public function testFunctions(): void
    {
        $code = '
            let add = fn(a, b) { return a + b }
            add(3, 4)
        ';
        $this->assertSame(7.0, KodiScript::eval($code));
    }

    public function testRecursiveFunction(): void
    {
        $code = '
            let factorial = fn(n) {
                if (n <= 1) { return 1 }
                return n * factorial(n - 1)
            }
            factorial(5)
        ';
        $this->assertSame(120.0, KodiScript::eval($code));
    }

    public function testArrays(): void
    {
        $this->assertSame([1.0, 2.0, 3.0], KodiScript::eval('[1, 2, 3]'));
        $this->assertSame(2.0, KodiScript::eval('[1, 2, 3][1]'));
    }

    public function testObjects(): void
    {
        $code = 'let obj = { name: "Alice", age: 30 }; obj.name';
        $this->assertSame('Alice', KodiScript::eval($code));
    }

    public function testNullSafety(): void
    {
        $result = KodiScript::eval('user?.name', ['user' => null]);
        $this->assertNull($result);

        $result = KodiScript::eval('user?.name', ['user' => ['name' => 'Alice']]);
        $this->assertSame('Alice', $result);
    }

    public function testElvisOperator(): void
    {
        $result = KodiScript::eval('value ?: "default"', ['value' => null]);
        $this->assertSame('default', $result);

        $result = KodiScript::eval('value ?: "default"', ['value' => 'actual']);
        $this->assertSame('actual', $result);
    }

    public function testPrintOutput(): void
    {
        $result = KodiScript::run('print("Hello"); print("World")');
        $this->assertSame(['Hello', 'World'], $result->output);
    }

    public function testNativeFunctions(): void
    {
        $this->assertEquals(5, KodiScript::eval('length("hello")'));
        $this->assertSame('HELLO', KodiScript::eval('toUpperCase("hello")'));
        $this->assertSame('hello', KodiScript::eval('toLowerCase("HELLO")'));
        $this->assertEquals(5, KodiScript::eval('abs(-5)'));
        $this->assertEquals(3, KodiScript::eval('floor(3.7)'));
        $this->assertEquals(4, KodiScript::eval('ceil(3.2)'));
    }

    public function testCustomFunction(): void
    {
        $result = KodiScript::builder('greet("PHP")')
            ->registerFunction('greet', fn($name) => "Hello, $name!")
            ->execute();

        $this->assertSame('Hello, PHP!', $result->value);
    }

    public function testErrorHandling(): void
    {
        $result = KodiScript::run('undefined_variable');
        $this->assertTrue($result->hasErrors());
        $this->assertNotEmpty($result->errors);
    }
}
