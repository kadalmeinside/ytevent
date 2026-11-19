# Implementation Plan: Score Upgrade & Production Go-Live

Status: Draft implementation plan
Baseline: 82/100
Target MVP production: 92/100
Target post-launch hardening: 95/100
Current verified baseline:
- 210 PHPUnit tests passed, 2232 assertions
- Vite production build passed
- Composer dependency audit: no known vulnerabilities
- Workspace: clean main branch

## Urutan Pengerjaan

### Phase 0 - Dokumentasi Baseline
Owner: Tech Lead
Status: Completed

1. Perbarui README agar akurat.
2. Perbarui UI/UX sebagai source of truth untuk antarmuka produksi.
3. Buat release gate dan stop-ship checklist.
4. Sinkronkan claim teknologi, test, dan status operasional.

Exit criteria:
- Tidak ada versi framework lama.
- Tidak ada angka test statis yang tidak didukung bukti fresh.
- Design rules tidak bertentangan satu sama lain.
- Setiap keputusan produksi mempunyai acceptance criteria.

### Phase 1 - README dan UI/UX
Owner: Tech Lead + Frontend
Status: Completed in initial implementation pass

README:
- Laravel 12, PHP >= 8.2.
- Tambah environment matrix: local, staging, production.
- Tambah production prerequisites.
- Tambah scripts test/build/audit.
- Tambah status validasi yang tidak menjadi angka permanen.
- Tambah deployment, queue, scheduler, backup, and rollback section.
- Tambah security model summary and seeded account policy.
- Pisahkan simulation/sandbox secara eksplisit dari production.

UI/UX:
- Tetapkan utilitarian design over decorative glass.
- Primary action: one solid orange/red action per screen.
- Glass effect only for sticky header, modal, or mobile decision bar.
- Focus-visible, keyboard navigation, Escape, labels, and touch target 44px are hard gates.
- No emoji, pulsing status, dot indicators, or decorative unicode arrows.
- Status uses explicit text and semantic colors.
- Card radius, shadows, typography, and card density follow one component system.
- Add loading, empty, error, stale, success, and retry states to every operational view.
- Include a desktop and mobile browser verification matrix.

Exit criteria:
- UI audit score >= 85/100.
- No duplicate, conflicting, or legacy design instruction.
- Key workflow passes keyboard and mobile smoke testing.

### Phase 2 - Automated Quality and Browser Verification
Owner: QA + Frontend
Status: Completed for initial gate

Tasks:
- Add npm scripts: lint, format:test, typecheck where applicable, test:e2e.
- Add PHP CS fixer/Pint CI gate.
- Extend test suite for security matrices and route authorization.
- Add browser tests for login, registration, payment manual, webhook sandbox, screening, and roster lock.
- Add desktop/mobile screenshots or Playwright smoke tests.
- Add automated axe accessibility checks to representative pages.
- Add test reports for assertions and coverage.
- Prevent build or test against production credentials.

Acceptance:
- Main test suite passes in CI.
- Frontend build passes in CI.
- Composer audit passes in CI.
- Critical workflows pass in Chromium at desktop and mobile widths.
- No critical or serious accessibility findings.

Score impact:
- Testing: 88 -> 92
- Frontend: 82 -> 88
- Security: 84 -> 88

### Phase 3 - Security and RBAC Production Hardening
Owner: Backend + Security

Tasks:
- Verify permission matrix against every admin route.
- Add route authorization feature tests for every role.
- Add session revocation after role/password changes.
- Add CSP and security headers to production configuration.
- Verify private media authorization and signed/expiring access where needed.
- Add stronger login throttling and account lockout policy.
- Remove or disable default seeder accounts outside approved local/staging environments.
- Enforce secrets management and remove fallback credentials from production logs.
- Add audit log protection and export controls.
- Add request correlation IDs.

Acceptance:
- No unauthorized route access in feature tests.
- No secrets in repository or application output.
- Default credentials cannot be created in production.
- Private documents cannot be enumerated or accessed cross-team.
- Security headers verified on staging and production.

Score impact:
- Security: 84 -> 92
- RBAC: 85 -> 93

### Phase 4 - Payment Reconciliation and Webhook Operations
Owner: Backend + Finance
Status: Completed for initial webhook/reconciliation gate

Tasks:
- Add webhook idempotency and reconciliation records.
- Add scheduled reconciliation for pending and delayed gateway payments.
- Add alerts for underpayment, overpayment, mismatch provider, duplicate callback, and failed webhook.
- Add admin reconciliation dashboard for pending and unconfirmed settlements.
- Add provider timeout and retry policy.
- Record correlation IDs across payment initiation, callback, and email.
- Ensure sandbox/simulation route is disabled by environment.
- Add settlement, MDR, gross, net, refund, and aging reports.
- Add finance approval and two-person review policy for high-risk refunds.

Acceptance:
- Same webhook payload handled repeatedly remains unchanged. — Verified by PaymentFlowTest replay coverage.
- Late settlement cannot overfill quota.
- All pending states can be reconciled. — Midtrans pending payments now have an hourly reconciliation command; Xendit reconciliation remains deferred.
- Finance has an auditable operational screen. — Existing Integration Monitor and webhook logs remain the operational surface.
- No gateway initiation can create duplicate external invoice. — Existing gateway claim lock remains protected by payment tests.

Implemented in this phase:
- Webhook idempotency hash persisted with a unique database constraint.
- Replayed processed callbacks return an idempotent success response without re-settling or creating another log.
- `app:reconcile-pending-payments` checks unchanged Midtrans gateway payments and is scheduled hourly.
- Webhook processing stores `processed_at` for operational inspection.

Deferred:
- Xendit API reconciliation adapter.
- Settlement/MDR/net/refund aging reports.
- Two-person approval policy for high-risk refunds.

Score impact:
- Payment: 85 -> 93
- Backend integration: 85 -> 91

### Phase 5 - Queue, Scheduler, Alerting, and Observability
Owner: DevOps + Backend

Tasks:
- Configure Supervisor or platform equivalent for queue workers.
- Add dead-job, retry, backoff, and alert policy.
- Configure Laravel scheduler with health confirmation.
- Add structured application and webhook logs.
- Add health endpoint for database, queue, scheduler, storage, mail, and payment config.
- Add uptime monitoring.
- Add Sentry or equivalent error tracking.
- Configure log rotation and sensitive-data redaction.
- Create runbooks for queue failure, gateway outage, mail outage, and database degradation.

Acceptance:
- Queue worker survives restart and processes jobs.
- Failed email and webhook alerts reach on-call staff.
- Scheduler expiration is independently confirmed.
- Health endpoint is used by deployment and uptime checks.
- Incident runbooks are executable by a second operator.

Score impact:
- Deployment readiness: 68 -> 92
- Queue: 80 -> 92
- Observability: 60 -> 88

### Phase 6 - Data, Files, and Disaster Recovery
Owner: DevOps + DBA

Tasks:
- Automate encrypted database backups.
- Automate private document and public asset backups.
- Define retention, deletion, and legal hold policy.
- Perform one restore drill.
- Verify foreign key and migration recovery.
- Add storage capacity monitoring.
- Separate public and private storage permissions.
- Add malware and file integrity checks where the deployment budget supports it.

Acceptance:
- Restore drill succeeds from a clean environment.
- Backup age and integrity are monitored.
- Private files remain inaccessible without authorization.
- Data deletion and retention policy is documented.

Score impact:
- Operations: 68 -> 90
- Data resilience: 78 -> 92

### Phase 7 - Production Deployment Pipeline
Owner: DevOps

Tasks:
- Add repeatable deployment manifest or platform configuration.
- Add environment variable inventory and secret validation.
- Add staging deployment matching production topology.
- Configure HTTPS, redirects, TLS renewal, CDN/cache policy, and asset cache.
- Configure production-only optimization commands.
- Add migration dry-run and rollback procedure.
- Add smoke-test deployment gate.
- Document DNS, domain, storage, queue, scheduler, and worker ownership.

Acceptance:
- Staging equals production dependencies and topology.
- Deployment succeeds twice from clean state.
- Rollback instructions work.
- Public assets are cacheable and private assets are not cacheable by intermediaries.

Score impact:
- Deployment: 68 -> 93
- Documentation: 76 -> 90

## Production Score Model

| Area | Baseline | Target |
|---|---:|---:|
| Core features | 88 | 93 |
| Backend integration | 85 | 92 |
| Payment | 85 | 93 |
| UI/UX | 79 | 88 |
| Security/RBAC | 84 | 93 |
| Testing | 88 | 94 |
| Queue and scheduler | 80 | 92 |
| Observability | 60 | 88 |
| Deployment/backup | 68 | 93 |
| Documentation | 76 | 90 |
| Overall | 82 | 92 |

## Go-Live Checklist

- [ ] README matches Laravel, PHP, Node, test, and deployment reality.
- [ ] UI/UX specification matches implementation and legacy claims are removed.
- [ ] All critical test suites pass in CI.
- [ ] Browser smoke tests pass on desktop and mobile.
- [ ] Critical accessibility findings are closed.
- [ ] Production composer/npm audit gates pass.
- [ ] RBAC route matrix passes automated tests.
- [ ] Sandbox routes are disabled.
- [ ] Default accounts and passwords are disabled in production.
- [ ] Midtrans and Xendit production credentials are validated.
- [ ] Webhook replay and reconciliation pass tests.
- [ ] Queue worker and scheduler are supervised.
- [ ] Queue, webhook, and application alerts are tested.
- [ ] Database and private storage backup and restore are verified.
- [ ] Health check and uptime monitor are live.
- [ ] HTTPS, security headers, CSP, and private storage are verified.
- [ ] Rollback and incident runbooks are assigned.
- [ ] Incident owner and on-call contacts are published.
- [ ] At least one rehearsal is completed on staging.

## Features to Defer

- New event types before existing payment and screening stability is complete.
- Additional analytics or BI integrations.
- Mobile native application.
- Public API marketplace.
- Complex multi-region deployment.
- AI scouting features.
- Additional payment providers before reconciliation is stable.
- Decorative redesign work not tied to usability or accessibility.

## Final Delivery Definition

The system is ready for production when all Phase 1 through Phase 7 exit criteria are met, no critical security or payment defect remains open, and the Go-Live Checklist is signed by engineering, operations, finance, and product owners.
