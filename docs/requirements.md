---
title: "Requirements"
description: "Supported PHP versions and the dependency-light footprint of Cbox System Metrics"
weight: 4
---

# Requirements

These are the constraints enforced by Composer when you install `cboxdk/system-metrics`. They come directly from the package's `composer.json`.

## PHP Version

- **PHP `^8.3`** — PHP 8.3 or any later 8.x release.

The `8.3` floor is a hard requirement: the library relies on readonly classes and the modern type system introduced in PHP 8.3.

## Dependencies

This package is intentionally **dependency-light**:

- **No runtime Composer dependencies.** The `require` section declares only the PHP version constraint — installing the package pulls in no third-party runtime packages.
- **No required PHP extensions.** `composer.json` declares no `ext-*` requirements, so nothing beyond a standard PHP build is needed to satisfy the resolver.

Development-only tooling (Laravel Pint, Pest, PHPStan) is declared under `require-dev` and is not installed for consumers of the library.

## Next Steps

- [Installation](installation.md) - Install via Composer
- [Quick Start](quickstart.md) - 30-second working example
- [Platform Support](platform-support/comparison.md) - Linux and macOS implementation details
