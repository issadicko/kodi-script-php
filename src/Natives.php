<?php

declare(strict_types=1);

namespace KodiScript;

final class Natives
{
    private static ?self $instance = null;

    /** @var array<string, callable> */
    private array $functions = [];

    private ?Interpreter $interpreter = null;

    private function __construct()
    {
        $this->registerDefaults();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function setInterpreter(Interpreter $interpreter): void
    {
        $this->interpreter = $interpreter;
    }

    public function has(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    public function get(string $name): ?callable
    {
        return $this->functions[$name] ?? null;
    }

    private function registerDefaults(): void
    {
        // Output
        $this->functions['print'] = fn(...$args) => $this->printFn(...$args);

        // String functions
        $this->functions['toString'] = fn($val) => self::stringify($val);
        $this->functions['toNumber'] = fn($val) => is_numeric($val) ? (float) $val : 0.0;
        $this->functions['length'] = fn($val) => is_string($val) ? mb_strlen($val) : (is_array($val) ? count($val) : 0);
        $this->functions['substring'] = fn($str, $start, $end = null) =>
            mb_substr((string) $str, (int) $start, $end !== null ? (int) $end - (int) $start : null);
        $this->functions['toUpperCase'] = fn($str) => mb_strtoupper((string) $str);
        $this->functions['toLowerCase'] = fn($str) => mb_strtolower((string) $str);
        $this->functions['trim'] = fn($str) => trim((string) $str);
        $this->functions['replace'] = fn($str, $old, $new) => str_replace($old, $new, (string) $str);
        $this->functions['split'] = fn($str, $sep) => explode($sep, (string) $str);
        $this->functions['join'] = fn($arr, $sep) => implode(
            $sep,
            array_map(static fn($e) => self::stringify($e), (array) $arr)
        );
        $this->functions['contains'] = fn($str, $substr) => str_contains((string) $str, $substr);
        $this->functions['startsWith'] = fn($str, $prefix) => str_starts_with((string) $str, $prefix);
        $this->functions['endsWith'] = fn($str, $suffix) => str_ends_with((string) $str, $suffix);
        $this->functions['indexOf'] = fn($str, $substr) => mb_strpos((string) $str, $substr) ?: -1;

        // Math functions
        $this->functions['abs'] = fn($n) => abs((float) $n);
        $this->functions['floor'] = fn($n) => floor((float) $n);
        $this->functions['ceil'] = fn($n) => ceil((float) $n);
        $this->functions['round'] = fn($n, $precision = 0) => round((float) $n, (int) $precision);
        $this->functions['min'] = fn(...$args) => min(...$args);
        $this->functions['max'] = fn(...$args) => max(...$args);
        $this->functions['pow'] = fn($base, $exp) => pow((float) $base, (float) $exp);
        $this->functions['sqrt'] = fn($n) => sqrt((float) $n);
        $this->functions['sin'] = fn($n) => sin((float) $n);
        $this->functions['cos'] = fn($n) => cos((float) $n);
        $this->functions['tan'] = fn($n) => tan((float) $n);
        $this->functions['log'] = fn($n) => log((float) $n);
        $this->functions['log10'] = fn($n) => log10((float) $n);
        $this->functions['exp'] = fn($n) => exp((float) $n);

        // Random functions
        $this->functions['random'] = fn() => mt_rand() / mt_getrandmax();
        $this->functions['randomInt'] = fn($min, $max) => mt_rand((int) $min, (int) $max);
        $this->functions['randomUUID'] = fn() => sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        // Type functions
        $this->functions['typeOf'] = fn($val) => match (true) {
            is_null($val) => 'null',
            is_bool($val) => 'boolean',
            is_int($val) || is_float($val) => 'number',
            is_string($val) => 'string',
            is_array($val) => array_is_list($val) ? 'array' : 'object',
            is_callable($val) => 'function',
            default => 'unknown',
        };
        $this->functions['isNull'] = fn($val) => $val === null;
        $this->functions['isNumber'] = fn($val) => is_int($val) || is_float($val);
        $this->functions['isString'] = fn($val) => is_string($val);
        $this->functions['isBool'] = fn($val) => is_bool($val);

        // Array functions
        $this->functions['size'] = fn($arr) => is_array($arr) ? count($arr) : (is_string($arr) ? mb_strlen($arr) : 0);
        $this->functions['first'] = fn($arr) => is_array($arr) && count($arr) > 0 ? reset($arr) : null;
        $this->functions['last'] = fn($arr) => is_array($arr) && count($arr) > 0 ? end($arr) : null;
        $this->functions['slice'] = fn($arr, $start, $end = null) =>
            is_array($arr) ? array_slice($arr, (int) $start, $end !== null ? (int) $end - (int) $start : null) : [];
        $this->functions['reverse'] = fn($arr) => is_array($arr) ? array_reverse($arr) : [];
        $this->functions['sort'] = fn($arr, $order = 'asc') => $this->sortArray((array) $arr, $order);
        $this->functions['sortBy'] = fn($arr, $field, $order = 'asc') => $this->sortArrayBy((array) $arr, $field, $order);

        // JSON functions
        $this->functions['jsonParse'] = fn($str) => json_decode((string) $str, true);
        $this->functions['jsonStringify'] = fn($val) => json_encode($val, JSON_UNESCAPED_UNICODE);

        // Encoding functions
        $this->functions['base64Encode'] = fn($str) => base64_encode((string) $str);
        $this->functions['base64Decode'] = fn($str) => base64_decode((string) $str);
        $this->functions['urlEncode'] = fn($str) => rawurlencode((string) $str);
        $this->functions['urlDecode'] = fn($str) => urldecode((string) $str);

        // Crypto functions
        $this->functions['md5'] = fn($str) => md5((string) $str);
        $this->functions['sha1'] = fn($str) => sha1((string) $str);
        $this->functions['sha256'] = fn($str) => hash('sha256', (string) $str);

        // Date/Time functions
        $this->functions['now'] = fn() => (float) (microtime(true) * 1000);
        $this->functions['date'] = fn() => date('Y-m-d');
        $this->functions['time'] = fn() => date('H:i:s');
        $this->functions['datetime'] = fn() => date('c');
        $this->functions['timestamp'] = fn($str = null) => $str !== null ? strtotime($str) * 1000 : time() * 1000;
        $this->functions['formatDate'] = fn($ts, $fmt = 'Y-m-d H:i:s') => date($fmt, (int) ($ts / 1000));
        $this->functions['year'] = fn($ts = null) => (int) date('Y', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['month'] = fn($ts = null) => (int) date('n', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['day'] = fn($ts = null) => (int) date('j', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['hour'] = fn($ts = null) => (int) date('G', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['minute'] = fn($ts = null) => (int) date('i', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['second'] = fn($ts = null) => (int) date('s', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['dayOfWeek'] = fn($ts = null) => (int) date('w', $ts !== null ? (int) ($ts / 1000) : time());
        $this->functions['addDays'] = fn($ts, $days) => (float) $ts + ($days * 86400000);
        $this->functions['addHours'] = fn($ts, $hours) => (float) $ts + ($hours * 3600000);
        $this->functions['diffDays'] = fn($ts1, $ts2) => floor(abs($ts1 - $ts2) / 86400000);

        // Additional string functions
        $this->functions['repeat'] = fn($str, $times) => str_repeat((string) $str, (int) $times);
        $this->functions['padLeft'] = fn($str, $len, $pad = ' ') => str_pad((string) $str, (int) $len, $pad, STR_PAD_LEFT);
        $this->functions['padRight'] = fn($str, $len, $pad = ' ') => str_pad((string) $str, (int) $len, $pad, STR_PAD_RIGHT);

        // Higher-order array functions
        $this->functions['filter'] = fn($arr, $fn) => $this->filterArray((array) $arr, $fn);
        $this->functions['map'] = fn($arr, $fn) => $this->mapArray((array) $arr, $fn);
        $this->functions['reduce'] = fn($arr, $fn, $init) => $this->reduceArray((array) $arr, $fn, $init);
        $this->functions['find'] = fn($arr, $fn) => $this->findInArray((array) $arr, $fn);
        $this->functions['findIndex'] = fn($arr, $fn) => $this->findIndexInArray((array) $arr, $fn);
        $this->functions['some'] = fn($arr, $fn) => $this->someInArray((array) $arr, $fn);
        $this->functions['every'] = fn($arr, $fn) => $this->everyInArray((array) $arr, $fn);
        $this->functions['flatMap'] = fn($arr, $fn) => $this->flatMapArray((array) $arr, $fn);

        // Array aggregation / transformation
        $this->functions['range'] = fn($a, $b = null) => $this->rangeFn($a, $b);
        $this->functions['sum'] = fn($arr) => array_sum(array_map(fn($v) => (float) $v, (array) $arr));
        $this->functions['avg'] = function ($arr) {
            $arr = (array) $arr;
            if (count($arr) === 0) {
                return 0.0;
            }
            return array_sum(array_map(fn($v) => (float) $v, $arr)) / count($arr);
        };
        $this->functions['unique'] = fn($arr) => $this->uniqueFn((array) $arr);
        $this->functions['flatten'] = fn($arr) => $this->flattenFn((array) $arr);
        $this->functions['push'] = function ($arr, ...$items) {
            $result = array_values((array) $arr);
            foreach ($items as $item) {
                $result[] = $item;
            }
            return $result;
        };
        $this->functions['concat'] = function (...$arrs) {
            $result = [];
            foreach ($arrs as $a) {
                foreach ((array) $a as $e) {
                    $result[] = $e;
                }
            }
            return $result;
        };

        // Object functions
        $this->functions['keys'] = fn($obj) => $this->sortedKeys((array) $obj);
        $this->functions['values'] = function ($obj) {
            $obj = (array) $obj;
            $keys = $this->sortedKeys($obj);
            return array_map(fn($k) => $obj[$k], $keys);
        };
        $this->functions['entries'] = function ($obj) {
            $obj = (array) $obj;
            $keys = $this->sortedKeys($obj);
            return array_map(fn($k) => [$k, $obj[$k]], $keys);
        };
        $this->functions['has'] = fn($coll, $val) => $this->hasFn($coll, $val);

        // Number parsing
        $this->functions['parseInt'] = fn($val) => $this->parseIntFn($val);
        $this->functions['parseFloat'] = fn($val) => $this->parseFloatFn($val);

        // Regex
        $this->functions['regexMatch'] = fn($str, $pat) => $this->regexMatchFn((string) $str, (string) $pat);
        $this->functions['regexReplace'] = fn($str, $pat, $repl) =>
            $this->regexReplaceFn((string) $str, (string) $pat, (string) $repl);
    }

    /**
     * @return list<float>
     */
    private function rangeFn(mixed $a, mixed $b): array
    {
        if ($b === null) {
            $start = 0;
            $end = (int) $a;
        } else {
            $start = (int) $a;
            $end = (int) $b;
        }
        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $result[] = (float) $i;
        }
        return $result;
    }

    /**
     * Dedups while preserving first-seen order, using a type-aware key (mirrors
     * Go's valueKey).
     */
    private function uniqueFn(array $arr): array
    {
        $seen = [];
        $result = [];
        foreach ($arr as $v) {
            $key = self::valueKey($v);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $v;
            }
        }
        return $result;
    }

    private function flattenFn(array $arr): array
    {
        $result = [];
        foreach ($arr as $v) {
            if (is_array($v)) {
                foreach ($v as $e) {
                    $result[] = $e;
                }
            } else {
                $result[] = $v;
            }
        }
        return $result;
    }

    /**
     * @return list<string>
     */
    private function sortedKeys(array $obj): array
    {
        $keys = array_map('strval', array_keys($obj));
        sort($keys, SORT_STRING);
        return $keys;
    }

    private function hasFn(mixed $coll, mixed $val): bool
    {
        if (is_array($coll)) {
            if (array_is_list($coll)) {
                $target = self::valueKey($val);
                foreach ($coll as $item) {
                    if (self::valueKey($item) === $target) {
                        return true;
                    }
                }
                return false;
            }
            return array_key_exists((string) $val, $coll);
        }
        throw new \RuntimeException('has requires an object or array as first argument');
    }

    private function parseIntFn(mixed $val): float
    {
        if (is_int($val) || is_float($val)) {
            return (float) (int) $val;
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if (!is_numeric($trimmed)) {
                throw new \RuntimeException("cannot parse '{$val}' as integer");
            }
            return (float) (int) (float) $trimmed;
        }
        throw new \RuntimeException('parseInt requires a string or number');
    }

    private function parseFloatFn(mixed $val): float
    {
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if (!is_numeric($trimmed)) {
                throw new \RuntimeException("cannot parse '{$val}' as number");
            }
            return (float) $trimmed;
        }
        throw new \RuntimeException('parseFloat requires a string or number');
    }

    private function regexMatchFn(string $str, string $pat): bool
    {
        $result = @preg_match($this->compilePattern($pat), $str);
        if ($result === false) {
            throw new \RuntimeException("invalid regex: {$pat}");
        }
        return $result === 1;
    }

    private function regexReplaceFn(string $str, string $pat, string $repl): string
    {
        $result = @preg_replace($this->compilePattern($pat), $repl, $str);
        if ($result === null) {
            throw new \RuntimeException("invalid regex: {$pat}");
        }
        return $result;
    }

    private function compilePattern(string $pat): string
    {
        // Wrap the raw pattern in delimiters, escaping any occurrence of the
        // delimiter within the pattern.
        return '~' . str_replace('~', '\~', $pat) . '~';
    }

    /**
     * Produces a comparison key for a value (mirrors Go's valueKey: type + value).
     */
    private static function valueKey(mixed $v): string
    {
        if (is_array($v)) {
            return 'array:' . serialize($v);
        }
        if (is_bool($v)) {
            return 'bool:' . ($v ? '1' : '0');
        }
        return gettype($v) . ':' . self::stringify($v);
    }

    private function printFn(...$args): void
    {
        // Mirror Go's builtinPrint: each argument is emitted as its own line.
        if ($this->interpreter === null) {
            return;
        }
        foreach ($args as $arg) {
            $this->interpreter->addOutput(self::stringify($arg));
        }
    }

    /**
     * Renders a KodiScript value in canonical form, identical across language
     * implementations (mirrors Go's natives.Stringify):
     *   - integral numbers print without a trailing ".0" (3, not 3.0)
     *   - arrays print as "[1, 2, 3]" with unquoted string elements
     *   - objects print as "{a: 1, b: 2}" with keys sorted for determinism
     *   - booleans as true/false, null as null
     */
    public static function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NaN';
            }
            if (is_infinite($value)) {
                return $value > 0 ? 'Inf' : '-Inf';
            }
            // Whole numbers print without a trailing ".0".
            if ($value === floor($value) && abs($value) < 1e15) {
                return (string) (int) $value;
            }
            // Shortest round-trippable decimal (matches Go's FormatFloat(-1)).
            return json_encode($value);
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $parts = array_map(static fn($e) => self::stringify($e), $value);
                return '[' . implode(', ', $parts) . ']';
            }
            $keys = array_keys($value);
            usort($keys, static fn($a, $b) => strcmp((string) $a, (string) $b));
            $parts = [];
            foreach ($keys as $k) {
                $parts[] = $k . ': ' . self::stringify($value[$k]);
            }
            return '{' . implode(', ', $parts) . '}';
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value);
        }
        return (string) $value;
    }

    private function sortArray(array $arr, string $order): array
    {
        if ($order === 'desc') {
            rsort($arr);
        } else {
            sort($arr);
        }
        return $arr;
    }

    private function sortArrayBy(array $arr, string $field, string $order): array
    {
        usort($arr, function ($a, $b) use ($field, $order) {
            $aVal = is_array($a) ? ($a[$field] ?? null) : null;
            $bVal = is_array($b) ? ($b[$field] ?? null) : null;
            $cmp = $aVal <=> $bVal;
            return $order === 'desc' ? -$cmp : $cmp;
        });
        return $arr;
    }

    private function filterArray(array $arr, mixed $fn): array
    {
        $result = [];
        $i = 0;
        foreach ($arr as $item) {
            if ($this->isTruthy($this->callFunction($fn, [$item, (float) $i]))) {
                $result[] = $item;
            }
            $i++;
        }
        return $result;
    }

    private function mapArray(array $arr, mixed $fn): array
    {
        $result = [];
        $i = 0;
        foreach ($arr as $item) {
            $result[] = $this->callFunction($fn, [$item, (float) $i]);
            $i++;
        }
        return $result;
    }

    private function reduceArray(array $arr, mixed $fn, mixed $init): mixed
    {
        $acc = $init;
        $i = 0;
        foreach ($arr as $item) {
            $acc = $this->callFunction($fn, [$acc, $item, (float) $i]);
            $i++;
        }
        return $acc;
    }

    private function findInArray(array $arr, mixed $fn): mixed
    {
        $i = 0;
        foreach ($arr as $item) {
            if ($this->isTruthy($this->callFunction($fn, [$item, (float) $i]))) {
                return $item;
            }
            $i++;
        }
        return null;
    }

    private function findIndexInArray(array $arr, mixed $fn): float
    {
        $i = 0;
        foreach ($arr as $item) {
            if ($this->isTruthy($this->callFunction($fn, [$item, (float) $i]))) {
                return (float) $i;
            }
            $i++;
        }
        return -1.0;
    }

    private function someInArray(array $arr, mixed $fn): bool
    {
        $i = 0;
        foreach ($arr as $item) {
            if ($this->isTruthy($this->callFunction($fn, [$item, (float) $i]))) {
                return true;
            }
            $i++;
        }
        return false;
    }

    private function everyInArray(array $arr, mixed $fn): bool
    {
        $i = 0;
        foreach ($arr as $item) {
            if (!$this->isTruthy($this->callFunction($fn, [$item, (float) $i]))) {
                return false;
            }
            $i++;
        }
        return true;
    }

    private function flatMapArray(array $arr, mixed $fn): array
    {
        $result = [];
        $i = 0;
        foreach ($arr as $item) {
            $mapped = $this->callFunction($fn, [$item, (float) $i]);
            if (is_array($mapped)) {
                foreach ($mapped as $e) {
                    $result[] = $e;
                }
            } else {
                $result[] = $mapped;
            }
            $i++;
        }
        return $result;
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }
        if ($value === 0 || $value === 0.0 || $value === '') {
            return false;
        }
        return true;
    }

    private function callFunction(mixed $fn, array $args): mixed
    {
        if ($fn instanceof FunctionValue && $this->interpreter !== null) {
            return $this->interpreter->applyFunctionValue($fn, $args);
        }
        if (is_callable($fn)) {
            return $fn(...$args);
        }
        throw new \RuntimeException('Expected a function');
    }
}

