#!/usr/bin/env bash
# Équivalent Linux de tools/test-local.ps1 (MariaDB ou MySQL installé, port 3399, base isolée dans test-output/mysql).
#   bash tools/test-local.sh            # parcours puis arrêt
#   bash tools/test-local.sh --garder   # laisse le site ouvert sur http://localhost:8080
set -u
root="$(cd "$(dirname "$0")/.." && pwd)"
out="$root/test-output"
data="$out/mysql-linux"
mkdir -p "$out"
if [ ! -d "$data/mysql" ]; then
    mysql_install_db --no-defaults --datadir="$data" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1 \
        || mysqld --no-defaults --initialize-insecure --datadir="$data" >/dev/null 2>&1
fi
mysqld --no-defaults --datadir="$data" --port=3399 --bind-address=127.0.0.1 --socket="$out/mysql.sock" \
    --pid-file="$out/mysql.pid" --user="$(id -un)" >"$out/mysqld.log" 2>&1 &
for _ in $(seq 30); do mysqladmin -uroot -h127.0.0.1 -P3399 ping >/dev/null 2>&1 && break; sleep 1; done
mysql -uroot -h127.0.0.1 -P3399 -e "DROP DATABASE IF EXISTS suivi_test; CREATE DATABASE suivi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -h127.0.0.1 -P3399 --default-character-set=utf8mb4 suivi_test < "$root/database/schema.sql"
cat >"$out/config.test.php" <<'PHP'
<?php
return [
    'base_url' => 'http://localhost:8080',
    'db' => ['dsn' => 'mysql:host=127.0.0.1;port=3399;dbname=suivi_test;charset=utf8mb4', 'user' => 'root', 'password' => ''],
    'mail' => ['mode' => 'log', 'from' => 'noreply@suivi-des-animaux.fr', 'from_name' => 'Suivi des animaux'],
    'support_email' => 'support@suivi-des-animaux.fr',
    'newsletter' => ['from' => 'newsletter@suivi-des-animaux.fr', 'batch' => 2],
    'download_pc' => 'https://github.com/Tepan-Games/SuiviAnimaux-versions/releases/latest',
    'download_android' => 'https://play.google.com/store/apps/details?id=com.tepangames.suivianimaux',
    'sync' => ['max_bytes' => 1024 * 1024],
    'debug' => true,
];
PHP
export SUIVI_SITE_CONFIG="$out/config.test.php"
rm -f "$root/storage/mails.log"
rm -rf "$root/storage/sync" "$root/storage/suivis"
php -S localhost:8080 -t "$root/www" "$root/www/index.php" >"$out/php-server.log" 2>&1 &
server=$!
sleep 1
php "$root/tools/parcours-test.php"
result=$?
if [ "${1:-}" = "--garder" ]; then
    echo "Site de test ouvert : http://localhost:8080 (arrêter : kill $server; mysqladmin -uroot -h127.0.0.1 -P3399 shutdown)"
else
    kill "$server" 2>/dev/null
    mysqladmin -uroot -h127.0.0.1 -P3399 shutdown >/dev/null 2>&1
fi
exit $result
