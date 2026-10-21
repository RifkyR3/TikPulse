# Security Policy

At **TikPulse**, the security of network infrastructure and multi-router management systems is our highest priority. We take security vulnerabilities seriously and appreciate responsible disclosure.

---

## 🛡️ Supported Versions

Only the current major release series receives security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

---

## 🔒 Reporting a Vulnerability

If you discover a security vulnerability in TikPulse (including Safe Mode bypass vulnerabilities, authentication leaks, or credential exposure), **please do not disclose it publicly or via public GitHub issues**.

Instead, report it privately by sending an email to:

📧 **rifky.r3@gmail.com**

Please include in your report:
1. Description of the vulnerability and potential impact.
2. Step-by-step reproduction instructions or proof-of-concept (PoC) payload.
3. Affected components, routes, or files.
4. Any proposed patches or mitigations.

### Response Timeline
- **Acknowledgement**: Within 48 hours of submission.
- **Initial Assessment**: Within 5 business days.
- **Resolution & Patch**: Within 14 business days, depending on severity and complexity.

---

## ⚙️ Security Architectural Guarantees

TikPulse is designed with several defensive security layers:

1. **Role-Based Access Control (RBAC) & Principle of Least Privilege**:
   - Fine-grained module capabilities (`read`, `write`, `update`, `delete`) enforced on every endpoint by `AuthorizeModuleAccess` middleware (`module.access`).
   - Router access scoping: Groups are strictly restricted to authorized router IDs when `all_routers` is false, preventing lateral access across devices.
   - Account Protection Guardrails: Hard safeguards prevent self-deletion, self-deactivation, or deletion/demotion of the last Super Administrator.
   - Role Simulation Sandboxing: Super Administrator simulation mode executes within a read-only role evaluation context that strictly prevents privilege escalation.

2. **Hardware Safe Mode Guardrails**:
   - Destructive operations (`/system/reboot`, `/system/reset-configuration`, mass deletions) are blocked by default.
   - Operations require a two-step authentication unlock sequence yielding a cryptographically random bypass token with a 15-minute TTL.
   - All guarded attempts are logged into an append-only audit trail (`safe_mode_audit_logs`).

3. **Encrypted Credentials**:
   - RouterOS access credentials are encrypted at rest using AES-256 via Laravel Encrypter (`Crypt::encryptString`).
   - Plaintext credentials are never logged or exposed in client responses.

4. **Isolated Router Contexts**:
   - Multi-tenant router requests require authenticated session context and the `X-Router-ID` header.
   - Router contexts are strictly validated against the user's fleet permissions.

5. **Circuit Breaker Fast-Fail**:
   - Out-of-band network attacks or broken socket attempts fail fast after 3 retries, preventing server socket starvation or DoS amplification.
