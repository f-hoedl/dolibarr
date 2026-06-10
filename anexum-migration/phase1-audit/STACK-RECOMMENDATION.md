# Phase 1 — Target Stack Recommendation

Decision to be ratified at the Phase 1→2 gate.

## Options considered

### Option A — Re-platform: current Dolibarr LTS on a modernized deployment stack (RECOMMENDED)

Keep Dolibarr as the application; rewrite everything *around* it.

- **App:** official `dolibarr/dolibarr` image pinned to the latest
  stable major (≥ 22 → upgrade via the supported SQL migration chain),
  PHP 8.3, read-only container FS where possible.
- **DB:** **MariaDB 11 LTS** (drop-in for the existing MySQL data via
  `mysqldump` restore; mature backups, smaller operational surface than
  MySQL 8) — *not* a PostgreSQL conversion: Dolibarr supports pgsql,
  but the dump-level MySQL→PG conversion adds real risk for zero gain
  at this scale.
- **Compose:** single `docker-compose.yml` (app, mariadb, backup
  sidecar), healthchecks, named volumes, `.env` for secrets.
- **Proxy:** existing Caddy, one `handle` block — unchanged contract.
- **Documents:** replace MinIO + DolicraftS3 with a **plain named
  volume** for `documents/`; back it up with restic/rclone to
  ProtonDrive. At 1–3 users, S3 indirection is pure operational cost
  and a third-party-module dependency in the legal document path.
- **Monitoring:** `mysqld-exporter` + Caddy's built-in Prometheus
  metrics + a small HTTP blackbox check; Grafana dashboard provided.
- **Paperless-ngx:** keep ingestion via the Dolibarr REST API
  (`htdocs/api/`) or watched folder — defined in Phase 5.
- **E-invoicing:** ZUGFeRD 2.x (EN 16931, accepted in AT B2B;
  ebInterface for public-sector AT) via Dolibarr module or a small
  post-processing step that embeds EN 16931 XML into the PDF/A-3.
- **CI:** GitHub Actions — compose config lint, image build, restore
  test of the nightly dump (the backup is tested, not assumed).

Effort: days–weeks. Migration risk: low (supported upgrade path; data
never leaves the Dolibarr schema). Compliance: preserved + e-invoicing
added. Maintainability: one compose file, upstream LTS updates.

### Option B — Replace with a different open-source ERP (e.g. Odoo CE, ERPNext)

Modern stack (Python/Postgres), but: full ETL of all business data,
re-validation of AT compliance from scratch, new operational skill set,
and Odoo/ERPNext are *heavier* to operate than Dolibarr for 1–3 users.
Effort: months. Risk: high. Rejected unless Phase 1 review surfaces a
hard Dolibarr blocker.

### Option C — Ground-up custom rewrite (new codebase)

Feature parity with invoicing + AT compliance + CRM + projects + ECM is
a multi-person-year build with permanent sole-maintainer burden — the
opposite of the single-operator constraint. Rejected.

## Recommendation

**Option A.** "Modern, maintainable stack" is delivered where it
actually hurts today: pinned LTS images, declarative Compose, tested
backups, monitoring, compliant e-invoicing — while the data stays on
the supported Dolibarr upgrade path (zero-data-loss constraint is met
by design, then verified by the Phase 3 harness rather than depended
on).

If the live audit (`audit_live_instance.sh`) contradicts the declared
state (e.g. heavy custom modules, schema drift), the decision is
re-opened at the Phase 2 gate.

## Phase 2 inputs required from operator

1. Output bundle of `scripts/audit_live_instance.sh` from the host.
2. Confirmation: gapless invoice numbering scheme in use (mercure/terre
   numbering mask) and whether BlockedLog is enabled.
3. Choice on e-invoicing priority: ZUGFeRD-only (B2B) vs ebInterface
   (needed only if invoicing Austrian public sector via e-rechnung.gv.at).
