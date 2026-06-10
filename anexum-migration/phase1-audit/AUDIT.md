# Phase 1 Audit — erp.anexum.at

Date: 2026-06-10
Scope: audit of (a) the Dolibarr application source in this repository
and (b) the production context as described for erp.anexum.at. Findings
that could only be verified against the live instance are marked
**[LIVE-VERIFY]** and are covered by `scripts/audit_live_instance.sh`.

## 1. What this repository actually contains

This repository (`f-hoedl/dolibarr`) is a fork of **upstream Dolibarr
`develop`**, version **24.0.0-beta** (`htdocs/version.inc.php`). It is
the application source tree, **not** the deployment of erp.anexum.at:

- `htdocs/custom/` contains only the upstream README — **no custom
  modules are tracked here**. DolicraftS3 (MinIO connector) is therefore
  deployed out-of-band on the server. **[LIVE-VERIFY]**
- No production `docker-compose.yml`, no `Caddyfile`, no `.env` are
  tracked. Only upstream dev images exist under `dev/build/docker*`.
- The production instance reportedly runs **v22/v23**, i.e. it is one to
  two major versions behind this tree. Exact version: **[LIVE-VERIFY]**.

Consequence: the data-migration source of truth is the production
database and `documents/` tree, not this repository. Phase 3 tooling
must run against a dump taken on the host.

## 2. Application architecture (as shipped)

- **Runtime:** monolithic PHP application (no build step), served by
  Apache/PHP-FPM. Upstream supports PHP 7.4–8.x; v22+ effectively
  targets PHP 8.1–8.3.
- **Database:** abstraction layer in `htdocs/core/db/` with drivers for
  **MySQL/MariaDB (`mysqli`)**, **PostgreSQL (`pgsql`)** and SQLite3
  (experimental). Production uses MySQL.
- **Schema:** 749 table-definition files in
  `htdocs/install/mysql/tables/` (prefix `llx_`). Version upgrades are
  applied by SQL scripts in `htdocs/install/mysql/migration/` plus the
  web/CLI upgrade runner — this is the supported, tested upgrade path
  between major versions.
- **REST API:** Restler-based API under `htdocs/api/` exposing CRUD for
  all business objects (invoices, thirdparties, projects, products,
  documents). Relevant as a validation channel in Phase 3.
- **Dependencies:** vendored in `htdocs/includes/` (TCPDF, sabre/dav,
  swiftmailer, stripe, restler, phpoffice, …). `composer.json.disabled`
  — no composer at runtime, which simplifies image builds.
- **CLI:** maintenance and batch scripts in `scripts/` (bank, invoices,
  cron, emailings, withdrawals) — usable from a sidecar/cron container.

## 3. Data footprint to migrate

From the v22+ schema, the tables that carry Anexum's business data
(full inventory queries in `scripts/db_inventory.sql`):

| Domain | Core tables |
|--------|-------------|
| Third parties / CRM | `llx_societe`, `llx_socpeople`, `llx_societe_extrafields`, `llx_categorie*` |
| Customer invoices | `llx_facture`, `llx_facturedet`, `llx_facture_rec`, `llx_paiement*` |
| Supplier invoices | `llx_facture_fourn`, `llx_facture_fourn_det` |
| Proposals / orders | `llx_propal*`, `llx_commande*` |
| Projects / time | `llx_projet`, `llx_projet_task`, `llx_element_time` |
| Products / services | `llx_product*` |
| Banking | `llx_bank_account`, `llx_bank`, `llx_bank_url` |
| Accounting | `llx_accounting_*`, `llx_bookkeeping` |
| Documents (ECM) | `llx_ecm_files` + `documents/` tree (or MinIO bucket via DolicraftS3) |
| Audit/compliance | `llx_blockedlog` (unalterable log — legally relevant, must migrate bit-exact) |
| Config | `llx_const`, `llx_extrafields`, `llx_user`, `llx_usergroup*` |

Notes specific to Austria:

- **UID (VAT number)** lives in `llx_societe.tva_intra`; company's own
  UID in `llx_const` (`MAIN_INFO_TVAINTRA`). Both must survive
  migration unchanged.
- **`llx_blockedlog`**: if the unalterable-log module is enabled
  (common for AT/FR fiscal compliance), its hash chain breaks if rows
  are rewritten — it must be copied verbatim, never re-generated.
  Enabled? **[LIVE-VERIFY]**
- **Sequential invoice numbering** (`llx_facture.ref`) must remain
  gapless across the migration (§ 11 UStG requirements on invoice
  content; numbering continuity is expected by auditors).

## 4. E-invoicing capability (compliance gap analysis)

- Core Dolibarr (including this v24 tree) generates **PDF invoices**
  (TCPDF generators in `htdocs/core/modules/facture/doc/`) but has **no
  native ebInterface and no complete ZUGFeRD/Factur-X profile** built
  in.
- Practical options (evaluated in STACK-RECOMMENDATION.md): community
  Factur-X/ZUGFeRD modules for Dolibarr, or an external rendering step
  that wraps the EN 16931 XML into the PDF/A-3 invoice
  (ebInterface 5.0 / ZUGFeRD 2.2 both implement EN 16931 semantics).
- Whatever the target stack, **e-invoicing is additive work** — the
  current stack does not already provide it, so this constraint shapes
  Phase 4 regardless of the stack decision.

## 5. Production context (declared, to be confirmed on host)

| Component | Declared state | Verify |
|-----------|----------------|--------|
| Dolibarr | v22/23, Docker Compose | `audit_live_instance.sh` step 1 |
| Database | MySQL (container) | step 2 |
| Object storage | MinIO + DolicraftS3 module | step 3 |
| Reverse proxy | Caddy, TLS for erp.anexum.at | step 4 |
| Monitoring | Prometheus/Grafana | step 5 |
| Documents | Paperless-ngx ingestion | step 5 |
| Backup | ProtonDrive sync | step 6 |

## 6. Risks identified in Phase 1

1. **Version skew**: production (v22/23) vs this repo (24.0.0-beta,
   develop). A beta/develop tree must never be the migration target;
   pin a stable release.
2. **Untracked server state**: custom modules, conf.php, compose files
   and Caddyfile exist only on the host. Until captured, every plan is
   provisional. (Mitigated by the audit script.)
3. **DolicraftS3**: third-party module; compatibility with newer
   Dolibarr majors is not guaranteed and it sits in the document path —
   a primary candidate for simplification.
4. **BlockedLog hash chain** (if enabled): constrains how invoice rows
   may be moved.
5. **Rewrite scope**: full feature parity (invoicing incl. AT
   compliance, CRM, projects, accounting export, document ECM) is a
   multi-person-year build if re-implemented from scratch — see the
   stack recommendation.
