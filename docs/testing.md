# Testing the Tokenizer

Because `nexus-ai-tokenizer` acts as the critical math foundation for calculating potential cost metrics, rigorous testing is enforced.

## Running Tests

Ensure all dependencies are loaded, then execute the PHPUnit suite:

```bash
composer install
vendor/bin/phpunit
```

You should see 70+ assertions executed, ensuring formatting logic, chat overhead generation, strategy resolution, and image estimations evaluate perfectly.

## Integration Dependencies

**BPE Algorithms:**
The unit tests do **not** check the explicit large vocabulary output of `deepseek-v3` or `gemini-1.5-pro` since those require downloading multi-megabyte vocabulary payloads dynamically.

Tests running on HuggingFace ensure that the structural parsing of the `tokenizer.json` logic correctly assigns ranks and limits according to mock JSON structs, ensuring the implementation of the graph algorithm works independently of the actual model data.

## Code Quality

Before pushing pull requests, confirm that PHPStan passes without errors at Level 8:

```bash
vendor/bin/phpstan analyse src/ --level=8
```

## Auto-Formatting

Maintain PSR-12 styling using the CS Fixer:

```bash
vendor/bin/php-cs-fixer fix --diff --dry-run
```

---
[❮ Previous: Custom Strategies](custom-strategies.md) | [Next: Contributing ❯](contributing.md)
