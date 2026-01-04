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
        $this->functions['toString'] = fn($val) => $this->stringify($val);
        $this->functions['toNumber'] = fn($val) => is_numeric($val) ? (float) $val : 0.0;
        $this->functions['length'] = fn($val) => is_string($val) ? mb_strlen($val) : (is_array($val) ? count($val) : 0);
        $this->functions['substring'] = fn($str, $start, $end = null) =>
            mb_substr((string) $str, (int) $start, $end !== null ? (int) $end - (int) $start : null);
        $this->functions['toUpperCase'] = fn($str) => mb_strtoupper((string) $str);
        $this->functions['toLowerCase'] = fn($str) => mb_strtolower((string) $str);
        $this->functions['trim'] = fn($str) => trim((string) $str);
        $this->functions['replace'] = fn($str, $old, $new) => str_replace($old, $new, (string) $str);
        $this->functions['split'] = fn($str, $sep) => explode($sep, (string) $str);
        $this->functions['join'] = fn($arr, $sep) => implode($sep, (array) $arr);
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
    }

    private function printFn(...$args): null
    {
        $output = implode(' ', array_map(fn($arg) => $this->stringify($arg), $args));
        if ($this->interpreter !== null) {
            $this->interpreter->addOutput($output);
        }
        return null;
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
        foreach ($arr as $item) {
            $shouldInclude = $this->callFunction($fn, [$item]);
            if ($shouldInclude) {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function mapArray(array $arr, mixed $fn): array
    {
        $result = [];
        foreach ($arr as $item) {
            $result[] = $this->callFunction($fn, [$item]);
        }
        return $result;
    }

    private function reduceArray(array $arr, mixed $fn, mixed $init): mixed
    {
        $acc = $init;
        foreach ($arr as $item) {
            $acc = $this->callFunction($fn, [$acc, $item]);
        }
        return $acc;
    }

    private function findInArray(array $arr, mixed $fn): mixed
    {
        foreach ($arr as $item) {
            if ($this->callFunction($fn, [$item])) {
                return $item;
            }
        }
        return null;
    }

    private function findIndexInArray(array $arr, mixed $fn): int
    {
        foreach ($arr as $i => $item) {
            if ($this->callFunction($fn, [$item])) {
                return $i;
            }
        }
        return -1;
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

