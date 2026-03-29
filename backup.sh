#!/bin/bash
# backup.sh — نسخ احتياطي تلقائي

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"
mkdir -p $BACKUP_DIR

echo "[$DATE] بدء النسخ الاحتياطي..."

# PostgreSQL (النظام الجديد)
docker exec saas_postgres pg_dump -U saas_user saas_db | gzip > "$BACKUP_DIR/saas_$DATE.sql.gz"
echo "✅ PostgreSQL - تم"

# MySQL (OSAS)
docker exec osas_mysql mysqldump -u root -proot_password --all-databases | gzip > "$BACKUP_DIR/osas_$DATE.sql.gz"
echo "✅ MySQL (OSAS) - تم"

# حذف النسخ الأقدم من 7 أيام
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
echo "✅ تنظيف النسخ القديمة - تم"

echo "[$DATE] اكتمل النسخ الاحتياطي"
