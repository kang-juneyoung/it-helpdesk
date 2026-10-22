#!/usr/bin/env bash
# 서버(EC2)에서 실행되는 배포 스크립트. GitHub Actions가 SSM으로 호출한다.
#   ./deploy.sh <커밋 SHA>
# 순서: 새 이미지 받기 → 교체 → 헬스체크 → 실패하면 직전 버전으로 롤백
set -euo pipefail

TAG="${1:?사용법: ./deploy.sh <커밋 SHA>}"
DIR="${DEPLOY_DIR:-/opt/it-helpdesk}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/index.php?r=login}"
HEALTH_TRIES="${HEALTH_TRIES:-30}"   # 2초 간격 → 최대 60초
cd "$DIR"

log() { echo "[$(date '+%F %T')] $*"; }
dc()  { docker compose -f docker-compose.prod.yml --env-file .env "$@"; }

# 로그인 화면이 정상으로 뜨고, PHP 오류 문구가 섞여 있지 않으면 정상
healthy() {
  local body
  for _ in $(seq 1 "$HEALTH_TRIES"); do
    if body=$(curl -sf --max-time 5 "$HEALTH_URL") \
       && grep -q '사내 전산 관리' <<<"$body" \
       && ! grep -qiE 'Warning:|Fatal error|Notice:|Deprecated:' <<<"$body"; then
      return 0
    fi
    sleep 2
  done
  return 1
}

switch_to() {
  export APP_TAG="$1"
  dc up -d --remove-orphans
}

PREV="$(cat current_tag 2>/dev/null || true)"
log "배포 시작: ${PREV:0:12}${PREV:+ → }${TAG:0:12}"

export APP_TAG="$TAG"
log "이미지 받기"
dc pull --quiet app
switch_to "$TAG"

# 처음 배포할 때만 DB 테이블 + 데모 데이터 생성 (이후 배포에서는 데이터 유지)
if [[ ! -f .db_initialized ]]; then
  log "첫 배포: DB 초기화 (데모 데이터)"
  dc exec -T app php bin/init_db.php
  touch .db_initialized
fi

log "헬스체크: $HEALTH_URL"
if healthy; then
  echo "$TAG" > current_tag
  echo "$(date -Is) OK       $TAG" >> deploy_history.log
  docker image prune -f >/dev/null || true
  log "배포 성공: ${TAG:0:12}"
  exit 0
fi

log "헬스체크 실패. 앱 로그:"
dc logs --tail 40 app || true
echo "$(date -Is) FAILED   $TAG" >> deploy_history.log

if [[ -n "$PREV" && "$PREV" != "$TAG" ]]; then
  log "직전 버전으로 롤백: ${PREV:0:12}"
  switch_to "$PREV"
  if healthy; then
    echo "$(date -Is) ROLLBACK $PREV" >> deploy_history.log
    log "롤백 완료. 서비스는 직전 버전으로 유지됩니다"
  else
    log "롤백 후에도 헬스체크 실패 — 수동 확인 필요"
  fi
else
  log "되돌릴 직전 버전이 없습니다 (첫 배포)"
fi
exit 1
