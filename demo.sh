#!/usr/bin/env bash
# 시연 단축 명령 (맥·리눅스). PHP 8.2 이상 필요: 맥은 `brew install php`
#   ./demo.sh start     데모 DB(SQLite) 새로 만들고 http://127.0.0.1:8070 실행
#   ./demo.sh backup    예시 백업 폴더를 만들고 자동 점검 실행 (성공 1, 실패 3)
#   ./demo.sh stop      서버 종료
#   ./demo.sh test      테스트 실행 (vendor/bin/phpunit 필요: composer install)
set -euo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8070}"
PIDFILE=data/server.pid

stop_server() {
  if [[ -f $PIDFILE ]]; then
    kill "$(cat $PIDFILE)" 2>/dev/null || true
    rm -f $PIDFILE
  fi
}

case "${1:-help}" in
  start)
    command -v php >/dev/null || { echo "PHP가 없습니다. 맥: brew install php"; exit 1; }
    stop_server
    mkdir -p data
    php bin/init_db.php
    php -S 127.0.0.1:$PORT -t public > data/server.log 2>&1 &
    echo $! > $PIDFILE
    sleep 1
    echo "열기: http://127.0.0.1:$PORT  (종료: ./demo.sh stop)"
    ;;
  backup)
    B=data/sample-backup
    rm -rf "$B" && mkdir -p "$B/ERP_DB" "$B/그룹웨어" "$B/파일서버" "$B/MES_DB"
    head -c 3000000 /dev/urandom > "$B/ERP_DB/erp_$(date +%Y%m%d).bak"     # 정상
    : > "$B/그룹웨어/gw_$(date +%Y%m%d).sql.gz"                             # 크기 0
    head -c 1000 /dev/urandom > "$B/파일서버/nas_old.hbk"
    touch -t "$(date -v-3d +%Y%m%d%H%M 2>/dev/null || date -d '3 days ago' +%Y%m%d%H%M)" "$B/파일서버/nas_old.hbk"  # 3일 전
    # MES_DB 폴더는 비워 둠                                                    # 파일 없음
    BACKUP_DIR="$B" php bin/check_backup.php || echo "→ 실패가 있어 종료 코드 $? (cron에서 알림 받는 용도)"
    echo "대시보드·백업 점검 화면을 새로고침해 보세요."
    ;;
  stop)
    stop_server
    echo "종료했습니다."
    ;;
  test)
    [[ -x vendor/bin/phpunit ]] || { echo "먼저 composer install"; exit 1; }
    vendor/bin/phpunit
    ;;
  *)
    sed -n '2,7p' "$0"
    ;;
esac
