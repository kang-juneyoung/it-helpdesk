# AWS 배포 설정 (처음 한 번)

main 브랜치에 push → CI 테스트 통과 → 이미지 빌드(GHCR) → EC2에 자동 배포 → 헬스체크(실패 시 롤백).

```
 개발자 push
     │
     ▼
 GitHub Actions ── CI ── 문법 검사 · PHPUnit(SQLite·MySQL) · 화면 확인
     │ 통과 시
     ▼
 Deploy ── 이미지 빌드 → ghcr.io/kang-juneyoung/it-helpdesk:<커밋 SHA>
     │
     │  OIDC로 AWS 임시 자격 증명 (액세스 키 저장 안 함)
     ▼
 AWS SSM ── EC2에서 deploy.sh 실행 (22번 포트·SSH 키 없음)
     │
     ▼
 EC2 (Docker Compose: Apache+PHP / MariaDB)
     새 이미지로 교체 → 헬스체크 → 실패하면 직전 SHA로 롤백
```

예상 비용(서울 리전, t3.micro): 켜 두면 월 약 1.5~2만원, 중지해 두면 디스크 요금 월 1~2천원. **개인 AWS 계정**에서 진행하고, 아래 0단계 예산 알림부터 걸어 두세요.

## 0. 예산 알림

Billing → Budgets → 예산 생성 → 월 $10, 80% 도달 시 이메일.

## 1. GitHub OIDC 공급자 등록 (계정당 한 번)

IAM → 자격 증명 공급자 → 공급자 추가
- 유형: OpenID Connect
- URL: `https://token.actions.githubusercontent.com`
- 대상(Audience): `sts.amazonaws.com`

## 2. EC2 인스턴스용 역할

IAM → 역할 → 역할 생성 → AWS 서비스 / EC2 → 정책 `AmazonSSMManagedInstanceCore` → 이름 `it-helpdesk-ec2-ssm`

## 3. EC2 만들기

EC2 → 인스턴스 시작
- 이름: `it-helpdesk-demo`, **태그 추가: `App` = `it-helpdesk`** (배포 권한이 이 태그로만 허용됨)
- AMI: Amazon Linux 2023 (x86_64), 유형: `t3.micro`
- 키 페어: 없음 (SSH 쓰지 않음)
- 보안 그룹: 인바운드 **HTTP 80만** 허용 (22번 열지 않음)
- 스토리지: 8~10GB gp3
- 고급 세부 정보 → IAM 인스턴스 프로파일: `it-helpdesk-ec2-ssm`
- 고급 세부 정보 → 사용자 데이터: [`deploy/ec2-user-data.sh`](../deploy/ec2-user-data.sh) 내용 붙여 넣기

5분쯤 뒤 Systems Manager → Fleet Manager에 인스턴스가 보이면 준비 완료. 인스턴스 ID(`i-...`)와 퍼블릭 IP를 적어 둡니다.
중지·시작해도 주소가 바뀌지 않게 하려면 탄력적 IP를 연결합니다(연결된 동안 시간당 요금 발생).

## 4. GitHub Actions가 쓸 배포 역할

IAM → 역할 → 역할 생성 → 사용자 지정 신뢰 정책
- 신뢰 정책: [`deploy/aws/github-oidc-trust-policy.json`](../deploy/aws/github-oidc-trust-policy.json) (`<AWS_ACCOUNT_ID>`를 내 계정 번호로)
- 권한: 인라인 정책 [`deploy/aws/deploy-role-policy.json`](../deploy/aws/deploy-role-policy.json) (같은 방식으로 계정 번호 교체)
- 이름: `it-helpdesk-github-deploy` → 역할 ARN 복사

이 역할은 **이 저장소의 production 환경**에서만 쓸 수 있고, **`App=it-helpdesk` 태그가 붙은 인스턴스에 셸 명령 보내기**만 할 수 있습니다.

> **sub 값 주의.** GitHub가 보내는 OIDC 토큰의 `sub`에는 계정·저장소 고유 ID가 붙습니다.
> `repo:kang-juneyoung@58846281/it-helpdesk@1425188896:environment:production`
> 이름만 쓴 `repo:kang-juneyoung/it-helpdesk:...` 로 조건을 걸면 `Not authorized to perform sts:AssumeRoleWithWebIdentity`로 거절됩니다.
> 실제 값은 CloudTrail → 이벤트 기록 → 이벤트 이름 `AssumeRoleWithWebIdentity` → `userIdentity.userName`에서 확인할 수 있습니다.
> ID가 들어가 있어 저장소를 지우고 같은 이름으로 다시 만들어도 이 역할을 쓸 수 없습니다(사칭 방지).

## 5. GitHub 저장소 변수

Settings → Secrets and variables → Actions → **Variables** 탭 (비밀값이 아니므로 Secrets 아님)

| 이름 | 값 |
|---|---|
| `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::<계정번호>:role/it-helpdesk-github-deploy` |
| `EC2_INSTANCE_ID` | `i-0123...` |
| `APP_URL` | `http://<퍼블릭 IP>` |

이 변수가 없으면 Deploy는 이미지 빌드까지만 하고 배포는 건너뜁니다.

## 6. 첫 배포

1. Actions → Deploy → Run workflow (또는 main에 push)
2. 첫 실행 후 GitHub 프로필 → Packages → `it-helpdesk` → Package settings → **Change visibility → Public**
   (서버가 로그인 없이 이미지를 받기 위해 한 번만 필요)
3. 다시 Run workflow → `APP_URL` 접속 → `admin / admin1234!` (데모 계정)

## 문제 해결 기록

| 증상 | 원인 | 해결 |
|---|---|---|
| `The web identity token provided could not be validated` | 계정에 GitHub OIDC 공급자 미등록 | 1단계 공급자 등록 |
| `Not authorized to perform sts:AssumeRoleWithWebIdentity` | 신뢰 정책 `sub` 조건이 실제 토큰 값(ID 포함 형식)과 다름 | CloudTrail에서 실제 `sub` 확인 후 신뢰 정책 수정 |

## 운영 메모

- 서버 배포 이력: SSM Session Manager로 접속 → `cat /opt/it-helpdesk/deploy_history.log`
- 롤백 확인: 일부러 화면에 오류를 내는 커밋을 올리면 헬스체크 실패 → 직전 버전 자동 복구 → Actions는 실패로 표시
- 수동 롤백: Actions → Deploy를 이전 커밋 기준으로 다시 실행
- 안 쓸 때: EC2 **중지** (데이터는 디스크에 남음). 다시 켤 때 탄력적 IP가 없으면 `APP_URL` 수정
- 데모 계정 비밀번호가 공개돼 있으므로 데모 데이터만 둡니다
