#!/bin/bash
# EC2 처음 만들 때 "사용자 데이터"에 붙여 넣는 초기 설정 (Amazon Linux 2023, x86_64)
# Docker·Compose 설치, 스왑 1GB, 앱 폴더와 비밀번호(.env) 생성까지 한다.
# 앱 배포는 GitHub Actions가 SSM으로 한다 (SSM 에이전트는 Amazon Linux 2023에 기본 설치됨).
set -euxo pipefail

dnf install -y docker
systemctl enable --now docker

# Docker Compose 플러그인
COMPOSE_VERSION=v2.29.7
mkdir -p /usr/local/lib/docker/cli-plugins
curl -fsSL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-$(uname -m)" \
  -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# t3.micro(메모리 1GB)에서 MariaDB + Apache를 같이 돌리므로 스왑 1GB
if [ ! -f /swapfile ]; then
  dd if=/dev/zero of=/swapfile bs=1M count=1024
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# 앱 폴더. 비밀번호는 서버에서 무작위로 만들고 저장소·GitHub 어디에도 두지 않는다
mkdir -p /opt/it-helpdesk/backup
cd /opt/it-helpdesk
if [ ! -f .env ]; then
  rand() { head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n'; }
  cat > .env <<ENV
IMAGE_REPO=ghcr.io/kang-juneyoung/it-helpdesk
DB_PASS=$(rand)
DB_ROOT_PASS=$(rand)
ENV
  chmod 600 .env
fi
