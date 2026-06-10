# Anexum ERP Modernization — erp.anexum.at

Migration project for the Dolibarr ERP instance at `erp.anexum.at`
(Anexum GmbH, Austrian SMB, 1–3 internal users).

Goal: move from the current PHP/MySQL Docker stack to a modern,
maintainable, single-operator-friendly stack while preserving Austrian
legal compliance (UID, ebInterface / ZUGFeRD e-invoicing) with zero data
loss.

## Phase plan and status

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | Audit & stack recommendation | **DONE — awaiting go/no-go** |
| 2 | Target stack decision + scaffolding (repo, Compose, CI) | pending |
| 3 | Data migration scripts + validation harness | pending |
| 4 | Feature parity (invoicing, CRM, projects, e-invoicing) | pending |
| 5 | Integration wiring (MinIO, Paperless-ngx, Prometheus) | pending |
| 6 | Cutover plan + rollback documentation | pending |

Each phase ends with a checkpoint report (completed work, test results,
deviations, go/no-go) and waits for an explicit "proceed".

## Layout

- `phase1-audit/AUDIT.md` — audit of the application source and known
  production context.
- `phase1-audit/STACK-RECOMMENDATION.md` — target stack options and the
  recommendation for the Phase 2 decision gate.
- `phase1-audit/scripts/audit_live_instance.sh` — run **on the
  production host** to capture the live configuration (this repository
  contains only the Dolibarr application source; the live instance is
  not reachable from the development environment).
- `phase1-audit/scripts/db_inventory.sql` — row-count / data-footprint
  inventory queries used both for the audit and later as the baseline
  for migration validation.

## Hard constraints (apply to every phase)

- Zero data loss: export/import is validated against the inventory
  baseline before any cutover.
- Austrian compliance preserved or improved: UID handling, ebInterface
  4.x/5.x or ZUGFeRD 2.x (EN 16931) e-invoicing.
- Single operator: one `docker compose` project, no microservices.
- Caddy stays as the TLS-terminating reverse proxy.
- A rollback snapshot (DB dump + documents archive + compose state) is
  taken before every phase gate.
