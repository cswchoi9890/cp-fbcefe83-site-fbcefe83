#!/usr/bin/env bash
# .github/scripts/php-keepalive.sh
# shivammathur/setup-php 가 PHP 8.3+extensions 설치 완료된 상태에서 실행
set -uo pipefail
OFFSET="${1:-0}"
sudo mkdir -p /run/php
WP_ROOT="$(pwd)"
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo '8.3')"

# PHP-FPM 풀 설정
sudo tee /etc/php/${PHP_VER}/fpm/pool.d/wp.conf > /dev/null << 'PHPEOF'
[wp]
user = www-data
group = www-data
listen = /run/php/php-wp.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = static
pm.max_children = 12
pm.max_requests = 2000
php_value[memory_limit] = 256M
php_value[max_execution_time] = 120
php_value[upload_max_filesize] = 64M
php_value[post_max_size] = 64M
PHPEOF

sudo service "php${PHP_VER}-fpm" restart 2>/dev/null || \
  sudo service php-fpm restart 2>/dev/null || true
sleep 1

# nginx 설정 (heredoc 변수 확장 비활성화로 $ 충돌 방지)
sudo tee /etc/nginx/sites-available/default > /dev/null << 'NGINXEOF'
server {
    listen 8080 default_server;
    root WPROOT_PLACEHOLDER;
    index index.php index.html;
    client_max_body_size 64M;
    location / { try_files $uri $uri/ /index.php?$args; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-wp.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
        include fastcgi_params;
    }
    location ~* \.(css|js|png|jpg|gif|ico|svg|woff2)$ { expires 30d; add_header Cache-Control "public,immutable"; }
    location ~ /\. { deny all; }
}
NGINXEOF
# root 경로를 실제 WP_ROOT로 치환
sudo sed -i "s|WPROOT_PLACEHOLDER|${WP_ROOT}|g" /etc/nginx/sites-available/default
sudo nginx -t 2>/dev/null && (sudo service nginx restart 2>/dev/null || sudo nginx 2>/dev/null) || true

# WordPress 미설치 시 PHP 서버만 유지
if [ ! -f "wordpress/wp-load.php" ]; then
  echo "[+${OFFSET}s] PHP+nginx 대기 중 (WP 미설치)"
  exit 0
fi
HTTP="$(curl -o /dev/null -s -w "%{http_code}" --max-time 10 "http://localhost:8080/" 2>/dev/null || echo "000")"
echo "[+${OFFSET}s] HTTP: ${HTTP}"

php -r "
  error_reporting(0);
  require_once getenv('WP_ROOT') . '/wp-load.php';
  global \$wp_version;
  echo '[+${OFFSET}s] WP v' . \$wp_version . ' OK' . PHP_EOL;
" 2>&1 | head -2 || true

curl -sf --max-time 8 "http://localhost:8080/wp-json/" -o /dev/null \
  && echo "[+${OFFSET}s] REST OK" || true

# 헬스체크 JSON 저장 후 커밋
mkdir -p _cache
printf '{"alive":true,"offset":%s,"t":"%s","srv":"nginx+php-fpm"}' \
  "${OFFSET}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > _cache/health.json
git config user.name  "CloudPress Bot" 2>/dev/null || true
git config user.email "bot@cloudpress.app" 2>/dev/null || true
git add _cache/health.json 2>/dev/null || true
if ! git diff --staged --quiet; then
  git commit -m "alive +${OFFSET}s $(date -u +%H:%M:%S)" 2>/dev/null || true
  for _i in 1 2 3; do
    git pull --rebase origin main 2>/dev/null || true
    git push origin main && break || sleep 3
  done || true
fi