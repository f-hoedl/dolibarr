-- Phase 1 database inventory for erp.anexum.at (Dolibarr, prefix llx_).
-- Also serves as the Phase 3 validation baseline: the same queries are
-- run on source and target and the outputs must match.

-- Dolibarr schema version and instance identity
SELECT name, value FROM llx_const
WHERE name IN ('MAIN_VERSION_LAST_INSTALL', 'MAIN_VERSION_LAST_UPGRADE',
               'MAIN_INFO_SOCIETE_NOM', 'MAIN_INFO_TVAINTRA',
               'MAIN_INFO_SIREN', 'MAIN_MODULE_BLOCKEDLOG');

-- Enabled modules
SELECT name FROM llx_const WHERE name LIKE 'MAIN_MODULE_%' ORDER BY name;

-- Row counts for every business-data table (zero-data-loss baseline)
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'llx_%'
  AND table_rows > 0
ORDER BY table_name;

-- Financial integrity checksums (must be identical after migration)
SELECT COUNT(*) AS invoices, COALESCE(SUM(total_ttc), 0) AS sum_ttc,
       MIN(ref) AS first_ref, MAX(ref) AS last_ref
FROM llx_facture WHERE fk_statut > 0;

SELECT COUNT(*) AS supplier_invoices, COALESCE(SUM(total_ttc), 0) AS sum_ttc
FROM llx_facture_fourn WHERE fk_statut > 0;

SELECT COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS sum_amount
FROM llx_paiement;

SELECT COUNT(*) AS thirdparties,
       SUM(CASE WHEN tva_intra IS NOT NULL AND tva_intra != '' THEN 1 ELSE 0 END) AS with_uid
FROM llx_societe WHERE entity > 0;

SELECT COUNT(*) AS projects FROM llx_projet;
SELECT COUNT(*) AS ecm_files, COALESCE(SUM(0)+COUNT(*),0) AS n FROM llx_ecm_files;

-- BlockedLog chain head (if module enabled): must be copied verbatim
SELECT COUNT(*) AS blockedlog_rows, MAX(rowid) AS last_rowid
FROM llx_blockedlog;
