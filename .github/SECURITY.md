# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take the security of PHPeek System Metrics seriously. If you discover a security vulnerability, please follow these steps:

### 1. Do Not Disclose Publicly

Please do not open a public GitHub issue for security vulnerabilities. This gives us time to address the issue before it can be exploited.

### 2. Report Privately

Send your vulnerability report to **sn@cbox.dk** with the subject line: `[SECURITY] PHPeek System Metrics Vulnerability`

### 3. Include Details

Please include the following information in your report:

- **Type of vulnerability** (e.g., command injection, path traversal, information disclosure)
- **Full path of the affected source file(s)**
- **Location of the affected source code** (tag/branch/commit or direct URL)
- **Step-by-step instructions to reproduce the issue**
- **Proof-of-concept or exploit code** (if possible)
- **Impact of the issue** (what an attacker could potentially do)
- **Suggested fix** (if you have one)

### 4. Response Timeline

We will acknowledge receipt of your vulnerability report within **48 hours** and will send you regular updates about our progress. If the issue is confirmed, we will:

- Develop and test a fix
- Prepare a security advisory
- Release a patched version
- Publicly disclose the vulnerability details after the patch is available

## Security Best Practices for Users

When using PHPeek System Metrics, follow these security best practices:

### 1. Read-Only Operations

This library performs **read-only** operations on system files and commands. It never writes to files or modifies system state.

### 2. File Access Permissions

The library reads sensitive system files like:
- `/proc/stat`, `/proc/meminfo`, `/proc/self/cgroup` (Linux)
- `/etc/os-release` (Linux)
- `/sys/class/dmi/id/*` (Linux virtualization detection)

Ensure your PHP process has appropriate read permissions. If running in restricted environments (containers, chroot, etc.), some metrics may be unavailable.

### 3. Command Execution

On macOS, the library executes the following system commands:
- `sysctl` (for CPU and memory metrics)
- `vm_stat` (for memory metrics)
- `sw_vers` (for OS version detection)

These commands are executed with **no user input** and use **hardcoded command strings**. There is no risk of command injection from external input.

### 4. Container and Virtualization Detection

The library attempts to detect virtualization and containerization by reading:
- `/sys/class/dmi/id/` files (Linux)
- `/proc/self/cgroup` (Linux)
- Container runtime files (`.dockerenv`, `/run/secrets/kubernetes.io/`, etc.)

This is purely informational and does not modify any system state.

### 5. Error Handling

The library uses the Result<T> pattern for explicit error handling. Always check if a Result is successful before accessing values:

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Use CPU metrics
} else {
    $error = $result->getError();
    // Handle error appropriately - do not expose error details to end users
}
```

### 6. Sensitive Information

Some metrics may contain sensitive system information:
- CPU core count and usage patterns
- Memory size and usage
- Virtualization/container environment details
- Operating system version and kernel information

**Do not expose this information to untrusted users** as it could aid in targeted attacks.

### 7. Rate Limiting

If exposing metrics through an API, implement rate limiting to prevent:
- Resource exhaustion from repeated metric collection
- Information disclosure through repeated polling

### 8. Privilege Escalation

**Never run this library with elevated privileges (root/sudo)** unless absolutely necessary. The library is designed to work with normal user permissions on most systems.

## Known Security Considerations

### 1. Symbolic Links

The library follows symbolic links when reading files. Ensure that directories like `/proc` and `/sys` are not compromised by malicious symlinks.

### 2. Race Conditions

Metrics are read at specific points in time. File contents can change between reads. This is expected behavior and not a security issue.

### 3. Denial of Service

Repeated rapid calls to metric collection can consume CPU resources. Implement application-level rate limiting if exposing this functionality.

### 4. Container Escape

The library cannot detect all forms of container escape or privilege escalation. Do not rely solely on this library for security-critical container detection.

## Acknowledgments

We appreciate the security research community's efforts to improve the security of open-source software. If you report a valid security vulnerability, we will acknowledge your contribution in the security advisory (if you wish to be credited).

## Contact

For security concerns: **sn@cbox.dk**
For general issues: https://github.com/gophpeek/system-metrics/issues

---

**Last Updated**: January 2025
