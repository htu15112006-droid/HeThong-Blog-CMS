#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${1:-$HOME/qtri_csdl_lab}"
cd "$PROJECT_DIR"
STATE="$(find "$PROJECT_DIR/.studio-backups" -maxdepth 1 -name 'state-*.json' -type f | sort | tail -n 1)"
test -n "$STATE" || { echo 'Khong co ban sao giao dien.';exit 1; }
docker compose cp "$STATE" wordpress:/tmp/cms-studio-rollback.json
docker compose exec -T wordpress php <<'PHP'
<?php
require '/var/www/html/wp-load.php';
$s=json_decode(file_get_contents('/tmp/cms-studio-rollback.json'),true);
if(!is_array($s)||empty($s['stylesheet'])||!wp_get_theme($s['stylesheet'])->exists()){fwrite(STDERR,"Khong tim thay giao dien cu.\n");exit(1);}
switch_theme($s['stylesheet']);
update_option('show_on_front',$s['show_on_front']);
update_option('page_on_front',$s['page_on_front']);
echo "Da khoi phuc giao dien truoc lan cai gan nhat.\n";
PHP
