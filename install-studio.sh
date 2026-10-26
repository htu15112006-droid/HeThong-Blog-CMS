#!/usr/bin/env bash
set -euo pipefail
TASK_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${1:-$HOME/qtri_csdl_lab}"
cd "$PROJECT_DIR"
test -f docker-compose.yml || { echo 'Khong tim thay docker-compose.yml.'; exit 1; }
test -f "$TASK_DIR/theme/cms-studio/index.php"
docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; echo "WordPress va database da san sang.\n";'
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$PROJECT_DIR/.studio-backups"
docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; echo json_encode(["template"=>get_template(),"stylesheet"=>get_stylesheet(),"show_on_front"=>get_option("show_on_front"),"page_on_front"=>get_option("page_on_front")]);' > "$PROJECT_DIR/.studio-backups/state-$STAMP.json"
if docker compose exec -T wordpress test -d /var/www/html/wp-content/themes/cms-studio; then
  docker compose exec -T wordpress tar -czf - -C /var/www/html/wp-content/themes cms-studio > "$PROJECT_DIR/.studio-backups/theme-$STAMP.tar.gz"
fi
docker compose cp "$TASK_DIR/theme/cms-studio" wordpress:/var/www/html/wp-content/themes/
docker compose exec -T wordpress php -l /var/www/html/wp-content/themes/cms-studio/functions.php
docker compose exec -T wordpress php -l /var/www/html/wp-content/themes/cms-studio/index.php
docker compose exec -T -u 0 wordpress chown -R www-data:www-data /var/www/html/wp-content/themes/cms-studio
docker compose exec -T wordpress php <<'PHP'
<?php
require '/var/www/html/wp-load.php';
$theme=wp_get_theme('cms-studio');
if (!$theme->exists() || $theme->errors()) {fwrite(STDERR,"Theme khong hop le.\n");exit(1);}
switch_theme('cms-studio');
update_option('show_on_front','posts');
echo "Da kich hoat CMS Studio. Khong thay doi bai viet hoac mat khau.\n";
PHP
echo "Hoan tat. Website: http://localhost/ | Quan tri: http://localhost/?cms=dashboard"
echo "Ban sao cau hinh giao dien: $PROJECT_DIR/.studio-backups/state-$STAMP.json"
echo "Khoi phuc giao dien cu: bash $TASK_DIR/rollback-studio.sh $PROJECT_DIR"
