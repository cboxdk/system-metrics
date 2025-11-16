# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Security Model

PHPeek/SystemMetrics is designed with security as a core principle. This document describes the security architecture, threat model, and best practices.

### Design Principles

1. **Pure PHP, No Extensions**: Zero compiled dependencies reduces supply chain attack surface
2. **Read-Only Operations**: No write, modify, or delete operations on the system
3. **Hardcoded Commands**: All system commands and file paths are hardcoded in source code
4. **Defense in Depth**: Multiple layers of security validation
5. **Explicit Error Handling**: No uncaught exceptions, all errors wrapped in Result<T>

## Security Architecture

### Command Execution Security

**ProcessRunner** implements multiple security layers for command execution:

#### 1. Command Whitelist
```php
// Only whitelisted command prefixes are allowed
private const ALLOWED_COMMANDS = [
    'vm_stat',    // macOS memory stats
    'sysctl',     // macOS system info
    'ps',         // Process info
    'cat /proc/', // Linux proc filesystem
    'cat /sys/',  // Linux sys filesystem
    // ... full list in ProcessRunner.php
];
```

**Why:** Prevents arbitrary command execution even if user input somehow reaches execute() method.

#### 2. Command Validation
```php
if (! $this->isCommandAllowed($command)) {
    return Result::failure(
        new SystemMetricsException("Command not whitelisted for security: {$command}")
    );
}
```

**Why:** Explicit validation before execution, fail-fast on unauthorized commands.

#### 3. Shell Escape
```php
$safeCommand = escapeshellcmd($command);
@exec($safeCommand . ' 2>&1', $output, $resultCode);
```

**Why:** Defense-in-depth against command injection, escapes shell metacharacters.

### File Access Security

**FileReader** implements multiple security layers for file operations:

#### 1. Path Whitelist
```php
private const ALLOWED_PATH_PREFIXES = [
    '/proc/',               // Linux proc filesystem
    '/sys/',                // Linux sys filesystem
    '/etc/os-release',      // Linux OS info
    '/private/var/folders/', // macOS temp (for tests)
    // ... full list in FileReader.php
];
```

**Why:** Restricts file access to system metric locations only.

#### 2. Path Validation
```php
if (! $this->isPathAllowed($path)) {
    return Result::failure(
        new SystemMetricsException("Path not whitelisted for security: {$path}")
    );
}
```

**Why:** Prevents directory traversal and arbitrary file access.

#### 3. Realpath Resolution
```php
$realPath = @realpath($path);
// Re-validate resolved path
if (! $this->isPathAllowed($realPath)) {
    return Result::failure(
        new SystemMetricsException("Resolved path not whitelisted for security: {$realPath}")
    );
}
```

**Why:** Prevents symlink attacks and directory traversal (e.g., `/proc/../etc/passwd`).

## Threat Model

### In Scope

1. **Command Injection**: Prevented by whitelist + escapeshellcmd()
2. **Directory Traversal**: Prevented by path whitelist + realpath() validation
3. **Symlink Attacks**: Prevented by realpath() resolution and re-validation
4. **Privilege Escalation**: Read-only operations, no write/modify capabilities
5. **Information Disclosure**: Only system metrics exposed, no sensitive data

### Out of Scope

1. **Denial of Service**: Library assumes trusted execution environment
2. **Time-Based Attacks**: System metrics collection timing is not security-sensitive
3. **Memory Exhaustion**: Caller responsible for resource limits
4. **Container Escape**: Library doesn't interact with container runtime

### Not Vulnerable To

- **SQL Injection**: No database access
- **XSS/CSRF**: No web interface
- **Deserialization Attacks**: No untrusted data deserialization
- **File Upload Attacks**: No file upload functionality
- **Remote Code Execution**: No dynamic code evaluation

## Security Best Practices

### For Library Users

#### 1. Least Privilege
Run processes using this library with minimal privileges:

```bash
# Good - run as non-root user
php script.php

# Avoid - unnecessary root privileges
sudo php script.php
```

#### 2. Container Security
When using in containers, consider:

```dockerfile
# Good - read-only root filesystem
docker run --read-only --security-opt=no-new-privileges your-image

# Good - drop capabilities
docker run --cap-drop=ALL --cap-add=SYS_PTRACE your-image
```

#### 3. Error Handling
Always check Result<T> values:

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Use metrics safely
} else {
    // Handle error - don't expose error details to untrusted users
    error_log($result->getError()->getMessage());
}
```

### For Library Contributors

#### 1. Never Accept User Input
All commands and paths must be hardcoded:

```php
// ✅ GOOD - hardcoded command
$result = $processRunner->execute('sysctl -n hw.ncpu');

// ❌ BAD - user input
$result = $processRunner->execute($_GET['command']); // NEVER DO THIS
```

#### 2. Validate Before Adding to Whitelist
Before adding commands or paths to whitelists:

- [ ] Verify command is read-only
- [ ] Verify command doesn't expose sensitive data
- [ ] Verify path contains only system metrics
- [ ] Test on both Linux and macOS
- [ ] Add unit test verifying whitelist works

#### 3. Use Result<T> Pattern
Never throw exceptions for expected errors:

```php
// ✅ GOOD - return Result
public function read(string $path): Result {
    if (!file_exists($path)) {
        return Result::failure(new FileNotFoundException($path));
    }
    return Result::success(file_get_contents($path));
}

// ❌ BAD - throw exception
public function read(string $path): string {
    if (!file_exists($path)) {
        throw new FileNotFoundException($path); // Don't do this
    }
    return file_get_contents($path);
}
```

## Reporting a Vulnerability

### Reporting Process

If you discover a security vulnerability in PHPeek/SystemMetrics:

1. **DO NOT** create a public GitHub issue
2. Email security reports to: [sn@cbox.dk](mailto:sn@cbox.dk)
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Triage & Validation**: Within 7 days
- **Fix Development**: Within 30 days (depending on severity)
- **Public Disclosure**: After fix is released and users have time to update

### Severity Levels

| Severity | Examples | Response Time |
|----------|----------|---------------|
| **Critical** | Remote code execution, arbitrary file write | 24 hours |
| **High** | Command injection, directory traversal | 7 days |
| **Medium** | Information disclosure, privilege escalation | 14 days |
| **Low** | Denial of service, timing attacks | 30 days |

## Security Checklist

Before releasing new versions:

- [ ] All tests passing (including security tests)
- [ ] PHPStan level max passes with 0 errors
- [ ] No hardcoded secrets or credentials
- [ ] All external commands whitelisted
- [ ] All file paths whitelisted
- [ ] Result<T> pattern used for all fallible operations
- [ ] Error messages don't expose system internals
- [ ] Dependencies updated to latest secure versions (currently: none)
- [ ] SECURITY.md updated with any new security considerations

## Security Updates

Security patches will be released as:

- **Patch versions** (0.1.x) for critical/high severity issues
- **Minor versions** (0.x.0) for medium/low severity issues
- **Major versions** (x.0.0) for breaking security changes

Users should:
- Subscribe to GitHub releases for notifications
- Update regularly, especially patch versions
- Test updates in non-production first
- Monitor composer security audits: `composer audit`

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [CWE Top 25 Most Dangerous Software Weaknesses](https://cwe.mitre.org/top25/)
- [Docker Security Best Practices](https://docs.docker.com/engine/security/)

## License

This security policy is licensed under MIT, same as the library.

---

**Last Updated**: 2025-01-16
**Maintained By**: Sylvester Damgaard (sn@cbox.dk)
