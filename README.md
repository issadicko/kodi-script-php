# KodiScript PHP SDK

[![Latest Stable Version](https://poser.pugx.org/issadicko/kodi-script/v)](https://packagist.org/packages/issadicko/kodi-script)
[![License](https://poser.pugx.org/issadicko/kodi-script/license)](https://packagist.org/packages/issadicko/kodi-script)
[![CI](https://github.com/issadicko/kodi-script-php/actions/workflows/ci.yml/badge.svg)](https://github.com/issadicko/kodi-script-php/actions/workflows/ci.yml)


A lightweight, embeddable scripting language for PHP applications.

📖 **Documentation complète** : [docs-kodiscript.dickode.net](https://docs-kodiscript.dickode.net/)

## Installation

```bash
composer require issadicko/kodi-script
```

## Quick Start

```php
use KodiScript\KodiScript;

// Simple evaluation
$result = KodiScript::eval('2 + 3 * 4');
echo $result; // 14

// With variables
$result = KodiScript::run('greeting + ", " + name + "!"', [
    'greeting' => 'Hello',
    'name' => 'World',
]);
echo $result->value; // "Hello, World!"

// Capture output
$result = KodiScript::run('
    let items = ["apple", "banana", "cherry"]
    for (item in items) {
        print(item)
    }
');
print_r($result->output); // ["apple", "banana", "cherry"]
```

## Features

- **Variables**: `let x = 10`
- **Functions**: `fn(a, b) { return a + b }`
- **Control Flow**: `if`, `else`, `for-in`, `while`
- **Data Types**: numbers, strings, booleans, arrays, objects
- **Null Safety**: `?.` (safe access), `?:` (elvis operator)
- **50+ Native Functions**: string, math, random, type, array, JSON, encoding, crypto, date/time

## 🔌 Extensibility

KodiScript is designed to be **extensible**. You can add your own native functions.

### Custom Functions

```php
$result = KodiScript::builder('greet("PHP")')
    ->registerFunction('greet', fn($name) => "Hello, $name!")
    ->execute();

echo $result->value; // "Hello, PHP!"
```

### Laravel Integration Example

```php
class ScriptService
{
    public function __construct(
        private UserRepository $userRepo,
        private NotificationService $notifications
    ) {}

    public function execute(string $script, array $context): ScriptResult
    {
        return KodiScript::builder($script)
            ->withVariables($context)
            ->registerFunction('fetchUser', fn($id) => $this->userRepo->find($id))
            ->registerFunction('sendEmail', fn($to, $subject, $body) => 
                $this->notifications->sendEmail($to, $subject, $body))
            ->registerFunction('calculatePrice', fn($qty, $price, $discount = 0) =>
                $qty * $price * (1 - $discount / 100))
            ->execute();
    }
}
```

## API Reference

### `KodiScript::eval(source, variables)`
Evaluates a script and returns the result value. Throws on error.

### `KodiScript::run(source, variables)`
Runs a script with optional variables and returns `ScriptResult`.

### `KodiScript::builder(source)`
Creates a builder for advanced configuration.

## Native Functions

| Category | Functions |
|----------|-----------|
| String | `toString`, `toNumber`, `length`, `substring`, `toUpperCase`, `toLowerCase`, `trim`, `split`, `join`, `replace`, `contains`, `startsWith`, `endsWith`, `indexOf` |
| Math | `abs`, `floor`, `ceil`, `round`, `min`, `max`, `pow`, `sqrt`, `sin`, `cos`, `tan`, `log`, `log10`, `exp` |
| Random | `random`, `randomInt`, `randomUUID` |
| Type | `typeOf`, `isNull`, `isNumber`, `isString`, `isBool` |
| Array | `size`, `first`, `last`, `reverse`, `slice`, `sort`, `sortBy` |
| JSON | `jsonParse`, `jsonStringify` |
| Encoding | `base64Encode`, `base64Decode`, `urlEncode`, `urlDecode` |
| Crypto | `md5`, `sha1`, `sha256` |
| Date/Time | `now`, `date`, `time`, `datetime`, `timestamp`, `formatDate`, `year`, `month`, `day`, `hour`, `minute`, `second`, `dayOfWeek`, `addDays`, `addHours`, `diffDays` |

## Other Implementations

| Language | Package |
|----------|---------|  
| **Kotlin** | [Maven Central](https://central.sonatype.com/artifact/io.github.issadicko/kodi-script) |
| **Go** | [pkg.go.dev](https://pkg.go.dev/github.com/issadicko/kodi-script-go) |
| **Dart** | [pub.dev](https://pub.dev/packages/kodi_script) |
| **TypeScript** | [npm](https://www.npmjs.com/package/@issadicko/kodi-script) |

## License

MIT
