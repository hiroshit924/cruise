#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

port="${CRUISE_PORT:-8080}"
url="http://localhost:$port"
chmod -R a+rX cruise-theme cruise-booking
docker compose up -d --wait
wp() { docker compose run --rm -T cli wp "$@"; }

# Apache's Docker entrypoint copies WordPress to the shared volume on first boot.
for attempt in {1..30}; do
    if docker compose exec -T wordpress test -f /var/www/html/wp-config.php; then break; fi
    sleep 1
done
if ! wp core is-installed; then
    wp core install --url="$url" --title='C365 CRUISE | 横浜みなとみらい貸切クルーズ' \
        --admin_user=cruise-admin --admin_email=admin@example.test --skip-email
fi
wp option update timezone_string Asia/Tokyo
wp option update blog_public 0
wp option update WPLANG ja
wp plugin activate cruise-booking
wp theme activate cruise-theme
wp rewrite structure '/%postname%/'
wp eval 'c365_import_launch_events(); c365_import_pages();'
wp rewrite flush
curl -fsS "$url/" | python3 -c 'import sys; page=sys.stdin.read(); assert "海から眺める" in page and page.count("class=\"event-card\"") >= 3'
printf 'WordPress: %s\n' "$url"
