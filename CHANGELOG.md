# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2026-01-04

### Added
- Initial release of KodiScript PHP SDK
- **Lexer**: Full tokenization with support for strings, numbers, operators, keywords
- **Parser**: Recursive descent parser with precedence climbing
- **Interpreter**: AST evaluation with variable scoping and closures
- **60+ Native Functions**:
  - String: `toString`, `length`, `substring`, `toUpperCase`, `toLowerCase`, `trim`, `split`, `join`, `replace`, `contains`, `startsWith`, `endsWith`, `indexOf`, `repeat`, `padLeft`, `padRight`
  - Math: `abs`, `floor`, `ceil`, `round`, `min`, `max`, `pow`, `sqrt`, `sin`, `cos`, `tan`, `log`, `exp`
  - Array: `size`, `first`, `last`, `reverse`, `slice`, `sort`, `sortBy`, `filter`, `map`, `reduce`, `find`, `findIndex`
  - Type: `typeOf`, `isNull`, `isNumber`, `isString`, `isBool`
  - JSON: `jsonParse`, `jsonStringify`
  - Encoding: `base64Encode`, `base64Decode`, `urlEncode`, `urlDecode`
  - Crypto: `md5`, `sha1`, `sha256`
  - Date/Time: `now`, `date`, `time`, `datetime`, `timestamp`, `formatDate`, `year`, `month`, `day`, `hour`, `minute`, `second`
  - Random: `random`, `randomInt`, `randomUUID`
- **String Templates**: `"Hello ${name}!"` with expression interpolation
- **Control Flow**: `if/else`, `for-in`, `while` loops
- **Functions**: User-defined functions with closures
- **Null Safety**: `?.` (safe access), `?:` (elvis operator)
- **Execution Limits**: `maxOperations` and `timeout` protection
- **Public API**: `KodiScript::run()`, `KodiScript::eval()`, `KodiScript::builder()`
- **30/30 Compliance Tests** passing
- **26/26 Unit Tests** passing
