#!/usr/bin/env bash
# Phase 1 live-instance audit for erp.anexum.at.
# Run ON THE PRODUCTION HOST as the user that owns the compose project.
# Produces ./anexum-audit-<date>.tar.gz — review for secrets before
# sharing (conf.php password is redacted automatically; .env is copied
# with values stripped).
#
# Usage: ./audit_live_instance.sh [compose-project-dir]
set -euo pipefail

PROJECT_DIR="${1:-$PWD}"
OUT="anexum-audit-$(date +%Y%m%d)"
mkdir -p "$OUT"

note() { printf '== %s\n' "$*" | tee -a "$OUT/audit.log"; }

# --- 1. Containers, images, Dolibarr version --------------------------
note "Step 1: containers and versions"
docker compose --project-directory "$PROJECT_DIR" ps -a > "$OUT/compose-ps.txt" || true
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Status}}' > "$OUT/docker-ps.txt"

DOLI_CTR="$(docker ps --format '{{.Names}}' | grep -im1 dolibarr || true)"
if [ -n "$DOLI_CTR" ]; then
  docker exec "$DOLI_CTR" php -r 'include "/var/www/html/filefunc.inc.php"; echo DOL_VERSION,"\n";' \
    > "$OUT/dolibarr-version.txt" 2>/dev/null \
    || docker exec "$DOLI_CTR" cat /var/www/html/version.inc.php > "$OUT/dolibarr-version.txt"
  docker exec "$DOLI_CTR" php -v > "$OUT/php-version.txt"
  # conf.php with the DB password redacted
  docker exec "$DOLI_CTR" sh -c 'sed "s/\(dolibarr_main_db_pass.*=\).*/\1 \"<redacted>\";/" \
    /var/www/html/conf/conf.php' > "$OUT/conf.php.redacted" || true
  # custom modules actually deployed
  docker exec "$DOLI_CTR" ls -la /var/www/html/custom > "$OUT/custom-modules.txt" || true
fi

# --- 2. Database inventory --------------------------------------------
note "Step 2: database inventory (enabled modules, row counts)"
DB_CTR="$(docker ps --format '{{.Names}}' | grep -Eim1 'mysql|mariadb' || true)"
if [ -n "$DB_CTR" ]; then
  echo "Enter MySQL root (or dolibarr user) password when prompted."
  docker exec -i "$DB_CTR" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --table dolibarr' \
    < "$(dirname "$0")/db_inventory.sql" > "$OUT/db-inventory.txt" \
    || note "  -> adjust DB name/credentials in step 2 and re-run"
fi

# --- 3. Object storage (MinIO / DolicraftS3) ---------------------------
note "Step 3: MinIO usage"
MINIO_CTR="$(docker ps --format '{{.Names}}' | grep -im1 minio || true)"
if [ -n "$MINIO_CTR" ]; then
  docker exec "$MINIO_CTR" sh -c 'du -sh /data/* 2>/dev/null' > "$OUT/minio-usage.txt" || true
fi

# --- 4. Caddy ----------------------------------------------------------
note "Step 4: Caddy site config"
for f in /etc/caddy/Caddyfile "$PROJECT_DIR"/Caddyfile "$PROJECT_DIR"/caddy/Caddyfile; do
  [ -f "$f" ] && cp "$f" "$OUT/Caddyfile" && break
done

# --- 5. Compose / env (values stripped) --------------------------------
note "Step 5: compose project files"
for f in "$PROJECT_DIR"/docker-compose.yml "$PROJECT_DIR"/compose.yml "$PROJECT_DIR"/compose.yaml; do
  [ -f "$f" ] && cp "$f" "$OUT/"
done
[ -f "$PROJECT_DIR/.env" ] && sed 's/=.*/=<redacted>/' "$PROJECT_DIR/.env" > "$OUT/env.keys"

# --- 6. Volumes and document tree size ---------------------------------
note "Step 6: volumes and data sizes"
docker volume ls > "$OUT/volumes.txt"
if [ -n "$DOLI_CTR" ]; then
  docker exec "$DOLI_CTR" sh -c 'du -sh /var/www/documents 2>/dev/null; \
    find /var/www/documents -type f | wc -l' > "$OUT/documents-size.txt" || true
fi

tar czf "$OUT.tar.gz" "$OUT"
note "Done: $OUT.tar.gz — review contents, then provide for Phase 2."
