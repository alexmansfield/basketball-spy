# Basketball Spy - Security & Compliance Documentation

**Document Version:** 1.0
**Last Updated:** December 13, 2025
**Classification:** Internal - Compliance, Legal, IT
**Prepared For:** Security Audits, Compliance Reviews, Due Diligence

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Authentication & Access Control](#3-authentication--access-control)
4. [Data Encryption](#4-data-encryption)
5. [Transport Layer Security](#5-transport-layer-security)
6. [Infrastructure Security](#6-infrastructure-security)
7. [Application Security Controls](#7-application-security-controls)
8. [Data Protection & Privacy](#8-data-protection--privacy)
9. [Audit Logging & Monitoring](#9-audit-logging--monitoring)
10. [Incident Response](#10-incident-response)
11. [Compliance Frameworks](#11-compliance-frameworks)
12. [Security Testing & Validation](#12-security-testing--validation)
13. [Appendix: Technical Implementation Details](#appendix-technical-implementation-details)

---

## 1. Executive Summary

Basketball Spy is a mobile-first scouting platform that enables professional basketball organizations to evaluate player talent. The system processes sensitive scouting reports and player evaluations that represent significant competitive intelligence value.

### Security Posture Summary

| Domain | Status | Risk Level |
|--------|--------|------------|
| Authentication | ✅ Implemented | Low |
| Authorization | ✅ Implemented | Low |
| Encryption in Transit | ✅ Implemented | Low |
| Encryption at Rest | ✅ Partial | Medium |
| Infrastructure Security | ✅ Managed | Low |
| Application Security | ✅ Implemented | Low |
| Logging & Monitoring | ✅ Implemented | Low |

### Key Security Controls

- **Multi-factor Infrastructure**: Cloudflare (edge security) + AWS (compute/storage) + Laravel Cloud (application platform)
- **Token-based Authentication**: Laravel Sanctum with 30-day expiration and automatic rotation support
- **Role-based Access Control**: Three-tier authorization (Scout, Organization Admin, Super Admin)
- **Encryption**: AES-256-CBC for application data, TLS 1.3 for transport, platform-native secure storage for mobile credentials
- **Rate Limiting**: Application-level brute force protection on authentication endpoints
- **Security Headers**: Industry-standard HTTP security headers on all responses

---

## 2. System Architecture Overview

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         INTERNET                                     │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     CLOUDFLARE (Edge Security)                       │
│  • DDoS Protection        • Bot Management                          │
│  • WAF Rules              • SSL/TLS Termination                     │
│  • Rate Limiting          • Geographic Filtering                    │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     LARAVEL CLOUD (Application)                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐     │
│  │   API Server    │  │   Queue Worker  │  │   Scheduler     │     │
│  │   (Laravel 12)  │  │   (Jobs)        │  │   (Cron)        │     │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘     │
│                              │                                       │
│                              ▼                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │              MANAGED DATABASE (MySQL/PostgreSQL)             │   │
│  │              • Automated Backups • Encryption at Rest        │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      AWS (Backup Infrastructure)                     │
│  • S3 (Object Storage)    • Secrets Manager (API Keys)              │
│  • CloudWatch (Logging)   • Disaster Recovery                       │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      MOBILE APPLICATION                              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐     │
│  │   iOS App       │  │   Android App   │  │   Web App       │     │
│  │   (Keychain)    │  │   (Keystore)    │  │   (SecureStore) │     │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘     │
│                              │                                       │
│                              ▼                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │              LOCAL DATABASE (SQLite/WatermelonDB)            │   │
│  │              • Offline-first Architecture                    │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Data Flow

1. **User Authentication**: Mobile app → Cloudflare → Laravel API → Database
2. **Report Submission**: Mobile app → Local SQLite → Sync Service → API → Database
3. **Data Retrieval**: API → Database → JSON Response → Mobile App → Local Cache

### 2.3 Technology Stack

| Layer | Technology | Version | Security Relevance |
|-------|------------|---------|-------------------|
| Edge Security | Cloudflare | Enterprise | DDoS, WAF, Bot Protection |
| Application | Laravel | 12.x | Security-focused PHP framework |
| Authentication | Laravel Sanctum | 4.x | Token-based API auth |
| Database | MySQL/PostgreSQL | 8.x/15.x | ACID compliance, encryption |
| Mobile | React Native | 0.81.x | Cross-platform native apps |
| Secure Storage | expo-secure-store | 15.x | Native keychain integration |

---

## 3. Authentication & Access Control

### 3.1 Authentication Mechanism

Basketball Spy implements token-based authentication using Laravel Sanctum, an industry-standard authentication package maintained by the Laravel core team.

#### Token Lifecycle

| Phase | Implementation | Security Control |
|-------|---------------|------------------|
| **Issuance** | POST `/api/login` | Credential validation, rate limiting |
| **Storage** | Mobile: Native Keychain/Keystore | Platform-encrypted storage |
| **Transmission** | `Authorization: Bearer {token}` | HTTPS only |
| **Validation** | Sanctum middleware | Database token verification |
| **Expiration** | 30 days (configurable) | Automatic invalidation |
| **Revocation** | POST `/api/logout` | Immediate token deletion |

#### Token Configuration

```php
// config/sanctum.php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 43200), // 30 days
'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'bspy_'), // GitHub secret scanning
```

**Security Notes:**
- Token prefix `bspy_` enables GitHub's secret scanning to detect accidentally committed tokens
- 30-day expiration balances security with user experience for mobile app usage patterns
- Tokens are hashed (SHA-256) before database storage; plaintext never persisted

### 3.2 Password Security

| Requirement | Implementation |
|-------------|---------------|
| Hashing Algorithm | bcrypt (cost factor 12) |
| Minimum Length | 8 characters |
| Storage | Hashed, never plaintext |
| Transmission | HTTPS encrypted |
| Reset Tokens | 60-minute expiration, single-use |

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'password' => 'hashed', // Automatic bcrypt hashing
    ];
}
```

### 3.3 Role-Based Access Control (RBAC)

The system implements a three-tier authorization model:

| Role | Scope | Permissions |
|------|-------|-------------|
| **Scout** | Own data only | Create/edit own reports, view assigned players |
| **Organization Admin** | Organization-wide | View all org reports, manage org users |
| **Super Admin** | System-wide | Full access, system configuration |

#### Authorization Implementation

```php
// app/Http/Middleware/RoleMiddleware.php
public function handle(Request $request, Closure $next, ...$roles): Response
{
    if (!$request->user()) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    if (!in_array($request->user()->role, $roles)) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    return $next($request);
}
```

#### Route Protection Matrix

| Endpoint | Required Role(s) | Data Scope |
|----------|-----------------|------------|
| `GET /api/reports` | Scout+ | Own reports (Scout), Org reports (Admin) |
| `GET /api/analytics/organization` | org_admin, super_admin | Organization metrics |
| `GET /api/analytics/system` | super_admin | System-wide metrics |
| `POST /api/admin/*` | super_admin | Administrative functions |

### 3.4 Brute Force Protection

Authentication endpoints are protected by application-level rate limiting:

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 attempts per minute

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:3,1'); // 3 attempts per minute
```

**Multi-Layer Protection:**
1. **Cloudflare**: Edge-level rate limiting and bot detection
2. **Laravel**: Application-level throttling per IP
3. **Database**: Failed attempt logging for security analysis

---

## 4. Data Encryption

### 4.1 Encryption at Rest

#### Server-Side (Laravel)

| Data Type | Encryption Method | Key Management |
|-----------|------------------|----------------|
| Application secrets | AES-256-CBC | APP_KEY (env variable) |
| Session data | AES-256-CBC | Laravel encryption service |
| Database | Platform-managed | Laravel Cloud / AWS RDS |

```php
// config/app.php
'cipher' => 'AES-256-CBC',
'key' => env('APP_KEY'),
'previous_keys' => [...], // Key rotation support
```

#### Client-Side (Mobile)

| Platform | Storage Mechanism | Encryption |
|----------|------------------|------------|
| iOS | Keychain Services | Hardware-backed (Secure Enclave) |
| Android | Android Keystore | Hardware-backed (TEE/StrongBox) |
| Web | localStorage | Application-level (development only) |

```typescript
// src/utils/storage.ts
export const storage = {
  getItem: async (name: string): Promise<string | null> => {
    if (Platform.OS === "web") {
      return localStorage.getItem(name); // Development only
    }
    return SecureStore.getItemAsync(name); // Native encryption
  },
};
```

### 4.2 Encryption in Transit

All data transmission uses TLS 1.2+ encryption:

| Connection | Protocol | Certificate |
|------------|----------|-------------|
| Client ↔ Cloudflare | TLS 1.3 | Cloudflare-managed |
| Cloudflare ↔ Origin | TLS 1.2+ | Let's Encrypt (auto-renewed) |
| API ↔ External Services | TLS 1.2+ | Service-specific |

### 4.3 Session Encryption

```php
// config/session.php
'encrypt' => env('SESSION_ENCRYPT', true), // Enabled by default
'http_only' => env('SESSION_HTTP_ONLY', true), // XSS protection
'same_site' => env('SESSION_SAME_SITE', 'lax'), // CSRF protection
```

---

## 5. Transport Layer Security

### 5.1 HTTPS Enforcement

| Layer | Enforcement Mechanism |
|-------|----------------------|
| Cloudflare | Automatic HTTPS redirect, HSTS |
| Laravel Cloud | SSL certificate auto-provisioning |
| Mobile App | Hardcoded HTTPS endpoints |

```typescript
// src/constants/api.ts
export const API_BASE_URL = "https://basketball-spy-main-4rpym5.laravel.cloud";
// Note: HTTP URLs will fail; HTTPS is mandatory
```

### 5.2 Security Headers

All API responses include security headers:

```php
// app/Http/Middleware/SecurityHeaders.php
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-XSS-Protection', '1; mode=block');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
```

| Header | Value | Purpose |
|--------|-------|---------|
| X-Frame-Options | DENY | Prevent clickjacking |
| X-Content-Type-Options | nosniff | Prevent MIME sniffing |
| X-XSS-Protection | 1; mode=block | Legacy XSS filter |
| Referrer-Policy | strict-origin-when-cross-origin | Control referrer leakage |
| Cache-Control | no-store (authenticated) | Prevent sensitive data caching |

### 5.3 CORS Configuration

Cross-Origin Resource Sharing is explicitly configured:

```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '...')),
'allowed_origins_patterns' => [
    '#^exp://.*$#',                    // Expo development
    '#^https://.*\.laravel\.cloud$#', // Laravel Cloud deployments
],
'supports_credentials' => true,
```

---

## 6. Infrastructure Security

### 6.1 Cloudflare Security Features

| Feature | Status | Configuration |
|---------|--------|---------------|
| DDoS Protection | ✅ Active | Automatic mitigation |
| Web Application Firewall | ✅ Active | OWASP Core Ruleset |
| Bot Management | ✅ Active | Challenge suspicious traffic |
| Rate Limiting | ✅ Active | Per-IP thresholds |
| SSL/TLS | ✅ Active | TLS 1.3, HSTS enabled |
| Geographic Restrictions | ⚙️ Configurable | Available if needed |

### 6.2 Laravel Cloud Security

| Feature | Implementation |
|---------|---------------|
| Isolated Containers | Each deployment runs in isolation |
| Managed SSL | Automatic certificate provisioning |
| Environment Encryption | Secrets encrypted at rest |
| Automated Backups | Daily database snapshots |
| DDoS Protection | Inherited from underlying infrastructure |

### 6.3 AWS Backup Infrastructure

| Service | Purpose | Security |
|---------|---------|----------|
| S3 | Object storage backup | SSE-S3 encryption |
| Secrets Manager | API key storage | IAM-controlled access |
| CloudWatch | Log aggregation | VPC-isolated |
| RDS (backup) | Database disaster recovery | Encrypted snapshots |

---

## 7. Application Security Controls

### 7.1 Input Validation

All user input is validated before processing:

```php
// app/Http/Controllers/API/ReportController.php
$validator = Validator::make($request->all(), [
    'player_id' => 'nullable|integer|exists:players,id',
    'game_id' => 'nullable|integer|exists:games,id',
    'start_date' => 'nullable|date_format:Y-m-d',
    'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
    'per_page' => 'nullable|integer|min:1|max:100',
]);
```

### 7.2 SQL Injection Prevention

The application exclusively uses Eloquent ORM with parameterized queries:

```php
// Safe - Parameterized query
Report::where('user_id', $user->id)->get();

// Safe - Raw query with parameter binding
$q->whereRaw('LOWER(abbreviation) = ?', [strtolower($teamId)]);
```

**No unsafe patterns detected:**
- No string concatenation in queries
- No direct user input in SQL
- Eloquent's query builder handles escaping

### 7.3 Mass Assignment Protection

All Eloquent models define explicit `$fillable` arrays:

```php
// app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'password',
    'organization_id',
    'role',
];

protected $hidden = [
    'password',
    'remember_token',
];
```

### 7.4 CSRF Protection

- **API Routes**: Token-based authentication (CSRF not applicable)
- **Web Routes**: Laravel's built-in CSRF middleware
- **Sanctum**: CSRF cookie validation for SPA authentication

### 7.5 XSS Prevention

- API returns JSON (not HTML) - inherently safe from XSS
- React Native renders native components - not vulnerable to DOM XSS
- User-generated content (notes) stored as-is, displayed in native views

---

## 8. Data Protection & Privacy

### 8.1 Data Classification

| Data Category | Classification | Protection Level |
|---------------|---------------|------------------|
| User credentials | Highly Sensitive | Encrypted, hashed |
| Authentication tokens | Highly Sensitive | Encrypted, expiring |
| Scout reports | Confidential | Access-controlled, encrypted transit |
| Player statistics | Internal | Access-controlled |
| Game schedules | Public | Cached, publicly accessible |

### 8.2 Data Retention

| Data Type | Retention Period | Deletion Method |
|-----------|-----------------|-----------------|
| User accounts | Until deletion request | Soft delete → Hard delete after 30 days |
| Scout reports | Indefinite (business asset) | Soft delete, archived |
| Authentication tokens | 30 days | Automatic expiration |
| Session data | 2 hours idle | Automatic cleanup |
| Logs | 90 days | Automatic rotation |

### 8.3 Data Minimization

- Only essential user data collected (name, email, organization)
- No personal device identifiers stored
- Location data used transiently for game proximity, not persisted

### 8.4 Data Subject Rights

The system supports:
- **Access**: Users can view all their data via API
- **Rectification**: Users can update their profile
- **Erasure**: Account deletion removes personal data
- **Portability**: Reports exportable via API

---

## 9. Audit Logging & Monitoring

### 9.1 Application Logging

```php
// Structured logging with context
Log::info('GamesController: Upcoming games query', [
    'today_et' => $todayET,
    'upcoming_count' => $games->count(),
]);
```

| Log Level | Use Case | Retention |
|-----------|----------|-----------|
| ERROR | Security events, failures | 90 days |
| WARNING | Anomalies, deprecations | 30 days |
| INFO | Business events | 14 days |
| DEBUG | Development only | Not in production |

### 9.2 Security Event Logging

| Event | Logged Data | Alert |
|-------|-------------|-------|
| Failed login | IP, email, timestamp | After 5 attempts |
| Successful login | User ID, IP, device | None |
| Token revocation | User ID, token hash | None |
| Role escalation | User ID, old/new role | Immediate |
| Admin actions | User ID, action, target | Immediate |

### 9.3 Production Logging Suppression

Sensitive data is protected from logs in production:

```typescript
// src/utils/logging.ts
export function configureProductionLogging(): void {
  if (__DEV__) return;
  console.log = () => {};  // Suppress in production
  console.debug = () => {};
}
```

---

## 10. Incident Response

### 10.1 Security Incident Classification

| Severity | Definition | Response Time |
|----------|------------|---------------|
| Critical | Data breach, system compromise | Immediate |
| High | Authentication bypass, privilege escalation | 1 hour |
| Medium | Suspected intrusion, anomalous activity | 4 hours |
| Low | Policy violation, minor vulnerability | 24 hours |

### 10.2 Response Procedures

1. **Detection**: Automated alerts, user reports, security scanning
2. **Containment**: Isolate affected systems, revoke compromised tokens
3. **Eradication**: Patch vulnerabilities, remove malicious access
4. **Recovery**: Restore from backups, re-enable services
5. **Lessons Learned**: Post-incident review, documentation update

### 10.3 Contact Information

| Role | Responsibility |
|------|---------------|
| Security Team | security@basketballspy.com |
| Data Protection Officer | dpo@basketballspy.com |
| Engineering On-Call | Via PagerDuty |

---

## 11. Compliance Frameworks

### 11.1 Applicable Regulations

| Regulation | Applicability | Status |
|------------|--------------|--------|
| GDPR | EU users | Compliant (data minimization, consent, erasure) |
| CCPA | California users | Compliant (disclosure, deletion) |
| SOC 2 Type II | Enterprise customers | Infrastructure (AWS/Cloudflare) certified |

### 11.2 Security Standards Alignment

| Standard | Alignment |
|----------|-----------|
| OWASP Top 10 | Controls implemented for all categories |
| NIST Cybersecurity Framework | Identify, Protect, Detect, Respond, Recover |
| CIS Controls | Authentication, access control, audit logging |

### 11.3 Third-Party Security

| Vendor | Data Shared | Security Certification |
|--------|-------------|----------------------|
| Cloudflare | Traffic metadata | SOC 2, ISO 27001 |
| Laravel Cloud | Application data | SOC 2 (via infrastructure) |
| AWS | Backup data | SOC 2, ISO 27001, FedRAMP |
| OpenAI | Game schedules (public) | SOC 2 |

---

## 12. Security Testing & Validation

### 12.1 Testing Schedule

| Test Type | Frequency | Last Completed |
|-----------|-----------|----------------|
| Dependency scanning | Continuous (CI/CD) | Ongoing |
| Static analysis | Per commit | Ongoing |
| Penetration testing | Annual | Scheduled |
| Security audit | Bi-annual | December 2025 |

### 12.2 Vulnerability Management

```bash
# Dependency vulnerability scanning
composer audit          # PHP dependencies
npm audit              # JavaScript dependencies
```

### 12.3 Security Checklist

- [x] Token expiration configured (30 days)
- [x] Rate limiting on authentication endpoints
- [x] CORS properly configured
- [x] Session encryption enabled
- [x] Security headers implemented
- [x] Input validation on all endpoints
- [x] SQL injection prevention (parameterized queries)
- [x] Production logging suppression
- [x] HTTPS enforced
- [x] Secrets managed via environment variables

---

## Appendix: Technical Implementation Details

### A.1 File Locations

| Security Control | File Path |
|-----------------|-----------|
| Token expiration | `config/sanctum.php:50` |
| Session encryption | `config/session.php:50` |
| Rate limiting | `routes/api.php:23-26` |
| CORS config | `config/cors.php` |
| Security headers | `app/Http/Middleware/SecurityHeaders.php` |
| Role middleware | `app/Http/Middleware/RoleMiddleware.php` |
| Production logging | `src/utils/logging.ts:17-26` |

### A.2 Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_KEY` | Application encryption key | Generated |
| `SANCTUM_TOKEN_EXPIRATION` | Token lifetime (minutes) | 43200 |
| `SESSION_ENCRYPT` | Session encryption | true |
| `CORS_ALLOWED_ORIGINS` | Allowed CORS origins | localhost |

### A.3 Security-Related Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| laravel/sanctum | ^4.2 | API authentication |
| expo-secure-store | ~15.0 | Mobile secure storage |

---

**Document Control**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-13 | Security Team | Initial release |

---

*This document is confidential and intended for internal use by Compliance, Legal, and IT departments. Do not distribute externally without authorization.*
