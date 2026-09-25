#!/usr/bin/env bash
#
# installer.sh — Setup server baru untuk Asterisk-Webhook (NOC System)
#
# Isi:
#   1. Install package sistem (PHP 8.3 + ext, Composer, Node.js 20, MySQL/MariaDB, Supervisor, Swoole, cron)
#   2. Install library aplikasi (composer + npm + wa-gateway)
#   3. Siapkan .env (copy example, samakan DB_*, key:generate, storage:link)
#   4. Buat database + user MySQL, lalu migrate --force
#   5. Ambil/generate token WA gateway
#   6. Tulis /etc/supervisor/conf.d/noc-system.conf + cron schedule:run + reload supervisor
#
# Cara pakai (sebagai root):
#   chmod +x installer.sh
#   MYSQL_USER=noc MYSQL_PASS='ganti-ini' ./installer.sh
#
#   Atau tanpa argumen (akan ditanya interaktif):
#   ./installer.sh
#
set -euo pipefail

# ============ KONFIGURASI (ubah di sini bila perlu) ============
APP_DIR="${APP_DIR:-/var/www/html/Asterisk-Webhook}"
# PHP_VER kosong = otomatis: pilih versi stabil tertinggi di repo bawaan
# (8.4 > 8.3). PHP 8.5+ sengaja dilewati default — Swoole/Laravel 13 belum
# tentu siap; paksa manual bila mau coba: PHP_VER=8.5 ./installer.sh
PHP_VER="${PHP_VER:-}"
NODE_MAJOR="${NODE_MAJOR:-20}"
MYSQL_USER="${MYSQL_USER:-}"
MYSQL_PASS="${MYSQL_PASS:-}"
DB_NAME="${DB_NAME:-crm_asterisk}"
APP_PORT="${APP_PORT:-8020}"
# ================================================================

if [[ $EUID -ne 0 ]]; then
  echo "ERROR: jalankan sebagai root (sudo ./installer.sh)" >&2
  exit 1
fi

if [[ -z "$MYSQL_USER" ]]; then
  read -rp "MySQL user baru: " MYSQL_USER
fi
if [[ -z "$MYSQL_PASS" ]]; then
  read -rsp "Password MySQL untuk '$MYSQL_USER': " MYSQL_PASS
  echo
fi
if [[ -z "$MYSQL_USER" || -z "$MYSQL_PASS" ]]; then
  echo "ERROR: MYSQL_USER dan MYSQL_PASS wajib diisi." >&2
  exit 1
fi

echo "==> [1/6] Install package sistem..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y software-properties-common curl git unzip ca-certificates lsb-release gnupg build-essential autoconf
# Repo PHP: versi sudah dipilih otomatis di atas (8.4 > 8.3) bila ada di repo
# bawaan. Blok ini hanya jalan bila versi terpilih tak ada di repo bawaan
# (cth. Ubuntu 22.04) -> tambah PPA ondrej. add-apt-repository butuh akses
# ke API Launchpad; bila timeout (jaringan dibatasi), tulis manual tanpa API.
. /etc/os-release
if [[ -z "$PHP_VER" ]]; then
  for v in 8.4 8.3; do
    if apt-cache show php${v}-cli >/dev/null 2>&1; then PHP_VER="$v"; break; fi
  done
fi
PHP_VER="${PHP_VER:-8.3}"
echo "PHP yang dipakai: $PHP_VER"
if ! apt-cache show php${PHP_VER}-cli >/dev/null 2>&1; then
  if ! add-apt-repository -y ppa:ondrej/php; then
    echo "WARNING: add-apt-repository gagal (Launchpad tak terjangkau). Tulis manual..."
    echo "deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${VERSION_CODENAME} main" \
      > /etc/apt/sources.list.d/ondrej-php.list
    # Kunci GPG PPA via keyserver Ubuntu (coba hkps lalu hkp port 80)
    mkdir -p /etc/apt/keyrings
    gpg --no-default-keyring --keyring /etc/apt/keyrings/ondrej-php.gpg --keyserver hkps://keyserver.ubuntu.com \
      --recv-keys 4F4EA0AAE5267A6C 2>/dev/null \
    || gpg --no-default-keyring --keyring /etc/apt/keyrings/ondrej-php.gpg --keyserver hkp://keyserver.ubuntu.com:80 \
      --recv-keys 4F4EA0AAE5267A6C \
    || { echo "ERROR: kunci GPG ondrej tidak bisa diambil. Buka akses ke keyserver.ubuntu.com (443/80) lalu ulangi." >&2; exit 1; }
    echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${VERSION_CODENAME} main" \
      > /etc/apt/sources.list.d/ondrej-php.list
  fi
else
  echo "php${PHP_VER} tersedia di repo bawaan (${PRETTY_NAME}) — PPA dilewati."
fi
mkdir -p /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
apt-get update -y
apt-get install -y \
  php${PHP_VER} php${PHP_VER}-cli php${PHP_VER}-fpm \
  php${PHP_VER}-mysql php${PHP_VER}-sqlite3 php${PHP_VER}-mbstring php${PHP_VER}-xml \
  php${PHP_VER}-curl php${PHP_VER}-zip php${PHP_VER}-bcmath \
  php${PHP_VER}-gd php${PHP_VER}-pcntl php${PHP_VER}-redis \
  php${PHP_VER}-dev php-pear \
  nodejs supervisor cron
# MySQL 8 (Ubuntu <= 22.04) atau MariaDB 10.6+ (Ubuntu 24.04 tidak lagi menyediakan paket mysql-server)
if apt-cache show mysql-server >/dev/null 2>&1; then
  apt-get install -y mysql-server
else
  apt-get install -y mariadb-server
fi

echo "==> [2/6] Install Composer + Swoole (untuk Octane)..."
if ! command -v composer >/dev/null 2>&1; then
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f composer-setup.php
fi
if ! php -m | grep -qi swoole; then
  pecl install swoole
  echo "extension=swoole.so" > /etc/php/${PHP_VER}/mods-available/swoole.ini
  phpenmod -v ${PHP_VER} -s ALL swoole || phpenmod swoole
fi
php -v | head -n 1
php -m | grep -qi swoole && echo "Swoole OK" || { echo "ERROR: Swoole gagal terpasang." >&2; exit 1; }

echo "==> [3/6] Install library aplikasi..."
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader
npm install
npm run build
cd "$APP_DIR/wa-gateway"
npm install --omit=dev
cd "$APP_DIR"
mkdir -p storage/logs
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "==> [3b/6] Siapkan .env + app key + storage link + migrate..."
[ -f .env ] || cp .env.example .env
# Samakan kredensial DB dengan user yang dibuat di langkah [4/6].
# Di sini hanya tulis file (tanpa koneksi DB); migrate jalan di [4b/6]
# setelah database & user benar-benar ada.
set_env() { # set_env KEY VALUE (tambah bila belum ada)
  local key="$1" val="$2" f="$APP_DIR/.env"
  # Amankan untuk sed: escape backslash, & dan delimiter |
  val="$(printf '%s' "$val" | sed -e 's/\\/\\\\/g' -e 's/&/\\&/g' -e 's/|/\\|/g')"
  if grep -qE "^${key}=" "$f"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$f"
  else
    echo "${key}=${val}" >> "$f"
  fi
}
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$MYSQL_USER"
set_env DB_PASSWORD "$MYSQL_PASS"
php artisan key:generate --force
php artisan storage:link || true

echo "==> [4/6] Buat user MySQL '$MYSQL_USER' (akses semua IP, semua DB)..."
systemctl enable --now mysql 2>/dev/null || systemctl enable --now mariadb
# Buka bind agar bisa diakses dari segala IP (sesuai permintaan).
# Lokasi conf beda tiap distro: MySQL (mysql.conf.d) vs MariaDB (mariadb.conf.d).
MYSQL_CNF_DIR=/etc/mysql/mysql.conf.d
[ -d /etc/mysql/mariadb.conf.d ] && MYSQL_CNF_DIR=/etc/mysql/mariadb.conf.d
mkdir -p "$MYSQL_CNF_DIR"
printf '[mysqld]\nbind-address = 0.0.0.0\nmysqlx-bind-address = 0.0.0.0\n' > "$MYSQL_CNF_DIR/99-remote-access.cnf"
# Buffer pool 2G (butuh untuk tabel >1GB; sesuaikan 50-70% RAM bila RAM beda)
printf '[mysqld]\ninnodb_buffer_pool_size = 2G\ninnodb_buffer_pool_instances = 2\n' > "$MYSQL_CNF_DIR/99-noc-tuning.cnf"
systemctl restart mysql 2>/dev/null || systemctl restart mariadb
# NOTE: root di Ubuntu/MariaDB umumnya auth_socket -> bisa login tanpa password sebagai root OS
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -e "CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASS}';"
mysql -uroot -e "GRANT ALL PRIVILEGES ON *.* TO '${MYSQL_USER}'@'%'; FLUSH PRIVILEGES;"
echo "Database '${DB_NAME}' + user '$MYSQL_USER'@'%' siap."
echo "PENTING: buka port 3306 di firewall/security-group bila diakses dari server lain."

echo "==> [4b/6] Migrate database..."
cd "$APP_DIR"
php artisan migrate --force

echo "==> [5/6] Ambil token WA gateway dari .env (atau generate)..."
WA_TOKEN="$(grep -E '^WA_GATEWAY_TOKEN=' "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d '\"' || true)"
if [[ -z "${WA_TOKEN:-}" ]]; then
  WA_TOKEN="$(openssl rand -hex 32)"
  echo "WA_GATEWAY_TOKEN belum ada di .env -> generate sementara: $WA_TOKEN"
  echo "Samakan dengan WA_GATEWAY_TOKEN di .env Laravel!"
fi

echo "==> [6/6] Tulis /etc/supervisor/conf.d/noc-system.conf + reload..."
cat > /etc/supervisor/conf.d/noc-system.conf << EOF
[program:noc-web]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan octane:start --server=swoole --host=0.0.0.0 --port=$APP_PORT
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-web.log
[program:noc-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan reverb:start
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-reverb.log

;[program:noc-asterisk-listener]
;process_name=%(program_name)s_%(process_num)02d
;command=php $APP_DIR/artisan asterisk:listen
;autostart=true
;autorestart=true
;user=root
;redirect_stderr=true
;stdout_logfile=$APP_DIR/storage/logs/supervisor-asterisk.log

[program:noc-asterisk-listener]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan ami:listen
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-asterisk.log

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)01d
command=php $APP_DIR/artisan queue:work --daemon --sleep=3 --tries=2 --timeout=3600 --memory=1024
autostart=true
autorestart=true
user=root
numprocs=3
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/worker.log
stopwaitsecs=3600
[program:noc-clear-cache]
process_name=%(program_name)s
command=/bin/bash -c "php $APP_DIR/artisan optimize:clear && php $APP_DIR/artisan cache:clear"
autostart=true
autorestart=false
startsecs=0
user=root
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-clear.log

[program:pds-work]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan pds:work
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-pds.log

[program:wa-gateway]
process_name=%(program_name)s_%(process_num)02d
directory=$APP_DIR/wa-gateway
command=/usr/bin/node server.js
autostart=true
autorestart=true
user=root
environment=WA_PORT="3001",WA_GATEWAY_TOKEN="$WA_TOKEN",WA_MIN_DELAY_MS="3000",WA_MAX_DELAY_MS="7000",LARAVEL_URL="http://127.0.0.1:$APP_PORT"
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/supervisor-wa.log
EOF

supervisorctl reread
supervisorctl update
supervisorctl status || true

# Cron scheduler Laravel (cdr:sync, cdr:summarize, customers:geocode,
# collectors:prune-positions, ...). PENTING SPLIT-SERVER: cron ini hanya boleh
# aktif di SATU server (server aplikasi). Kalau server lama masih jalan,
# matikan cron di sana agar job tidak dobel: `crontab -r` (cek dulu isinya!).
CRON_LINE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
if ! crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
  (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
  echo "Cron schedule:run terpasang."
else
  echo "Cron schedule:run sudah ada."
fi

echo
echo "SELESAI. Langkah manual tersisa:"
echo "  1. Sesuaikan $APP_DIR/.env: APP_URL (IP:port publik server ini), DB_CDR_* (Asterisk lama),"
echo "     AMI_* / PDS_* (bila voice tetap di server lama), REVERB_HOST/PORT, VITE_*."
echo "     Catatan: DB_* lokal + WA_GATEWAY_TOKEN sudah diisi otomatis oleh installer."
echo "  2. Scan ulang QR WA di menu WhatsApp Saya."
echo "  3. Cek: supervisorctl status"
echo "  4. SPLIT-SERVER: matikan cron schedule:run + service Laravel di server LAMA"
echo "     agar tidak dobel dengan server ini."
