# Contributing to PHPeek System Metrics

Thank you for considering contributing to PHPeek System Metrics! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

This project adheres to a code of professional conduct. By participating, you are expected to:

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive criticism
- Prioritize what is best for the community

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title** describing the issue
- **Steps to reproduce** the behavior
- **Expected behavior** vs actual behavior
- **Environment details**: PHP version, OS, platform (Linux/macOS)
- **Code samples** or test cases if applicable
- **Error messages** and stack traces

Use the bug report template when creating issues.

### Suggesting Enhancements

Enhancement suggestions are welcome! When suggesting enhancements:

- **Use a clear title** describing the enhancement
- **Provide detailed description** of the proposed functionality
- **Explain the motivation** - why is this enhancement useful?
- **Consider alternatives** you've thought about
- **Provide examples** of how the enhancement would be used

### Pull Requests

We actively welcome pull requests! Follow these steps:

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following our coding standards
3. **Add tests** for any new functionality
4. **Update documentation** if you changed APIs or behavior
5. **Run the test suite** to ensure all tests pass
6. **Run code quality checks** (PHPStan, Pint)
7. **Update CHANGELOG.md** with your changes
8. **Submit the pull request** with a clear description

## Development Setup

### Requirements

- PHP 8.3 or higher
- Composer
- Git
- Linux or macOS (for full testing)

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/system-metrics.git
cd system-metrics

# Install dependencies
composer install

# Run tests to verify setup
composer test
```

### Development Workflow

```bash
# Create a feature branch
git checkout -b feature/your-feature-name

# Make your changes
# ... edit code ...

# Run tests
composer test

# Run static analysis
composer analyse

# Format code
composer format

# Check formatting without changes
composer format:check

# Run test coverage
composer test-coverage
```

## Coding Standards

### PHP Standards

- **PHP 8.3+** features are encouraged (readonly classes, constructor property promotion)
- **Strict types**: All files must declare `declare(strict_types=1)`
- **PSR-4** autoloading
- **PSR-12** code style (enforced by Laravel Pint)
- **PHPStan Level 5** compliance (no errors)

### Architecture Principles

1. **Action Pattern**: Use focused Action classes for use cases
2. **Result<T> Pattern**: Return `Result<T>` instead of throwing exceptions
3. **Immutable DTOs**: Use readonly classes for all data transfer objects
4. **Interface-Driven**: Define contracts and code to interfaces
5. **Composite Pattern**: Use layered sources with graceful fallbacks
6. **No Global State**: Avoid static state (except configuration)

### Code Style

We use **Laravel Pint** for automatic code formatting:

```bash
# Format all code
composer format

# Check formatting without changes
composer format:check
```

**Key style points:**
- 4 spaces for indentation (no tabs)
- Opening braces on same line for methods/functions
- Type hints for all parameters and return types
- PHPDoc for all public methods with descriptions
- Named constructor parameters for clarity

### Testing Standards

We use **Pest v4** for testing:

```php
<?php

use PHPeek\SystemMetrics\DTO\Result;

describe('MyClass', function () {
    it('can do something', function () {
        $result = doSomething();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBe('expected');
    });

    it('handles errors gracefully', function () {
        $result = doSomethingThatFails();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SomeException::class);
    });
});
```

**Testing guidelines:**
- **Unit tests**: Test classes in isolation with mocked dependencies
- **Integration tests**: Test real file/command execution (in `tests/Integration/`)
- **Coverage target**: 80%+ line coverage
- **Test naming**: Use descriptive `it()` statements
- **Arrange-Act-Assert**: Structure tests clearly

### Documentation Standards

- **README.md**: Keep examples up-to-date with API changes
- **CHANGELOG.md**: Follow [Keep a Changelog](https://keepachangelog.com/) format
- **PHPDoc**: Document all public methods with descriptions and types
- **Code comments**: Explain "why", not "what"
- **Architecture docs**: Update CLAUDE.md for architectural changes

## Project Structure

```
system-metrics/
├── src/
│   ├── Actions/           # Use case actions
│   ├── Config/            # Configuration
│   ├── Contracts/         # Interfaces
│   ├── DTO/               # Data transfer objects
│   │   ├── Environment/   # Environment DTOs
│   │   └── Metrics/       # Metric DTOs
│   ├── Exceptions/        # Exception hierarchy
│   ├── Sources/           # Platform-specific implementations
│   │   ├── Cpu/
│   │   ├── Environment/
│   │   └── Memory/
│   ├── Support/           # Shared utilities
│   │   └── Parser/        # Platform-specific parsers
│   └── SystemMetrics.php  # Facade
├── tests/
│   ├── Unit/              # Unit tests
│   │   ├── DTO/
│   │   ├── Parser/
│   │   └── Support/
│   ├── Integration/       # Integration tests
│   ├── ArchTest.php       # Architecture tests
│   └── Pest.php           # Test configuration
├── .github/
│   ├── workflows/         # CI/CD workflows
│   └── SECURITY.md        # Security policy
├── composer.json
├── phpstan.neon.dist      # PHPStan configuration
└── README.md
```

## Platform-Specific Development

### Adding Linux Support

1. Implement source in `src/Sources/{Type}/Linux*Source.php`
2. Add parser in `src/Support/Parser/Linux*Parser.php` if needed
3. Add to composite source with OsDetector check
4. Write unit tests with mocked FileReader
5. Test on actual Linux system

### Adding macOS Support

1. Implement source in `src/Sources/{Type}/MacOs*Source.php`
2. Add parser in `src/Support/Parser/MacOs*Parser.php` if needed
3. Add to composite source with OsDetector check
4. Handle graceful degradation (modern macOS limitations)
5. Write unit tests with mocked ProcessRunner
6. Test on actual macOS system

### Adding New Metrics

1. Define contract in `src/Contracts/`
2. Create DTO in `src/DTO/Metrics/`
3. Implement platform-specific sources
4. Create Action in `src/Actions/`
5. Add method to SystemMetrics facade
6. Write comprehensive tests
7. Update documentation

## Testing Across Platforms

### Local Testing

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Unit/DTO/ResultTest.php

# Run with coverage
composer test-coverage

# Watch mode (re-run on changes)
vendor/bin/pest --watch
```

### CI/CD Testing

Tests run automatically on:
- **Push to any branch** with PHP changes
- **Pull requests**
- **PHP versions**: 8.3, 8.4
- **Platforms**: Ubuntu (Linux), macOS

## Pull Request Process

1. **Update documentation** for any user-facing changes
2. **Add tests** covering new functionality
3. **Ensure all tests pass** on CI/CD
4. **Maintain 80%+ coverage** (visible in PR checks)
5. **Follow commit message conventions**:
   - `feat: Add Linux environment detection`
   - `fix: Handle empty /proc/stat gracefully`
   - `docs: Update README examples`
   - `test: Add MacOS parser tests`
   - `refactor: Extract common parser logic`
6. **Update CHANGELOG.md** under "Unreleased" section
7. **Request review** from maintainers
8. **Address feedback** promptly and professionally

### Commit Message Format

```
type(scope): Short description

Longer description if needed explaining the motivation
and context for the change.

Fixes #123
```

**Types**: `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `chore`

## Questions?

Feel free to open an issue for:
- Questions about contributing
- Clarification on architecture decisions
- Discussion about proposed features
- Help with development setup

You can also reach out to **sn@cbox.dk** for private inquiries.

## Recognition

Contributors will be recognized in:
- GitHub contributors list
- CHANGELOG.md for significant contributions
- README.md credits section

Thank you for making PHPeek System Metrics better! 🚀
