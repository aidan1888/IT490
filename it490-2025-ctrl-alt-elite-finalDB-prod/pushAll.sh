#!/bin/bash

set -euo pipefail

# ==== CONFIG ====

WORKDIR="/home/dev-db/group490"

REMOTE_USER="db-prod1"
REMOTE_HOST="100.124.1.54"
REMOTE_BASE_DEST="/home/db-prod1/IT490"
SSH_KEY="$HOME/.ssh/id_rsa_migration"

TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
REMOTE_BACKUP_DEST="$REMOTE_BASE_DEST/backup_$TIMESTAMP"
REMOTE_LIVE_DEST="$REMOTE_BASE_DEST/Live"

# MySQL dump config
DB_NAME="mysql"
DB_USER="simple"
DB_PASS="1234"
MYSQL_DUMP_DIR="./__mysql_tables__"
REMOTE_MYSQL_SUBDIR="mySQL"

MYSQL_TABLES=("Users" "Airports" "Flights" "UserRoles" "Roles" "user_flights")

# ==== FUNCTION ====

send_file_if_changed() {
  local file="$1"
  local dest_subfolder="${2:-}"

  local clean_path="${file#$WORKDIR/}" 
  if [ -n "$dest_subfolder" ]; then
    clean_path="$dest_subfolder/$(basename "$clean_path")"
  fi

  local local_hash
  local_hash=$(sha256sum "$file" | cut -d ' ' -f1)

  local remote_file="$REMOTE_LIVE_DEST/$clean_path"
  local remote_hash
  remote_hash=$(ssh -i "$SSH_KEY" "$REMOTE_USER@$REMOTE_HOST" "test -f '$remote_file' && sha256sum '$remote_file' | cut -d ' ' -f1" || echo "MISSING")

  if [[ "$local_hash" != "$remote_hash" ]]; then
    if [[ "$remote_hash" != "MISSING" ]]; then
      local remote_backup_dir="$REMOTE_BACKUP_DEST/$(dirname "$clean_path")"
      echo "Backing up existing Live file to $remote_backup_dir"
      ssh -i "$SSH_KEY" "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$remote_backup_dir' && cp '$remote_file' '$remote_backup_dir/'"
      echo "Backed up previous Live: $clean_path"
    fi

    local remote_live_dir="$REMOTE_LIVE_DEST/$(dirname "$clean_path")"
    ssh -i "$SSH_KEY" "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$remote_live_dir'"
    scp -i "$SSH_KEY" "$file" "$REMOTE_USER@$REMOTE_HOST:$remote_file"
    echo "Live updated: $clean_path"
  else
    echo "Skipped (unchanged): $clean_path"
  fi
}


# ==== EXECUTION STARTS ====

echo "Changing to working directory: $WORKDIR"
cd "$WORKDIR"

[ ! -d "$WORKDIR" ] && { echo "ERROR: Working dir '$WORKDIR' not found"; exit 1; }
[ ! -f "$SSH_KEY" ] && { echo "ERROR: SSH key not found at $SSH_KEY"; exit 2; }

echo "Creating remote folders: $REMOTE_BACKUP_DEST and $REMOTE_LIVE_DEST"
ssh -i "$SSH_KEY" "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$REMOTE_BACKUP_DEST' '$REMOTE_LIVE_DEST'"

# === 1. Explicit Files (Passed as Args) ===

if [[ "$#" -eq 0 ]]; then
  echo "No files provided to push. Only MySQL tables will be deployed."
else
  echo "Sending specified files..."
  for file in "$@"; do
    if [[ -f "$file" ]]; then
      send_file_if_changed "$file"
    else
      echo "WARNING: '$file' is not a valid file. Skipping."
    fi
  done
fi

# === 2. MySQL Tables (individual exports) ===

echo "Exporting individual MySQL tables from database: $DB_NAME"

mkdir -p "$MYSQL_DUMP_DIR"

for table in "${MYSQL_TABLES[@]}"; do
  dump_file="$MYSQL_DUMP_DIR/${table}.sql"
  mysqldump -u "$DB_USER" -p"$DB_PASS" --skip-lock-tables --no-tablespaces "$DB_NAME" "$table" > "$dump_file"
  send_file_if_changed "$dump_file" "$REMOTE_MYSQL_SUBDIR"
done

rm -rf "$MYSQL_DUMP_DIR"

echo "All files processed."
echo "Backup folder: $REMOTE_BACKUP_DEST"
echo "Live folder  : $REMOTE_LIVE_DEST"
