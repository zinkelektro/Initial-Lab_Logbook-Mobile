#!/bin/bash
# Backup database logbook
set -e
BACKUP_DIR="/var/backups/lab-logbook"
mkdir -p "$BACKUP_DIR"
mysqldump -u labuser -p lab_logbook | gzip > "$BACKUP_DIR/logbook_$(date +%F_%H%M%S).sql.gz"
echo "Backup selesai: $BACKUP_DIR"
