# ADR 0002. 운영은 MySQL, 데모·테스트는 SQLite로 같은 코드를 쓴다

- 상태: 채택
- 날짜: 2026-10-19

## 배경

운영 DB는 MySQL(MariaDB)이다. 하지만 시연이나 자동 테스트를 할 때마다 DB 서버를 띄우는 것은 번거롭다.

## 결정

- PDO로 연결하고 `DB_DSN` 환경변수 하나로 MySQL과 SQLite를 바꾼다.
- 스키마는 `db/schema.mysql.sql`, `db/schema.sqlite.sql` 두 개를 두고 컬럼과 의미를 똑같이 맞춘다.
- 쿼리는 두 DB에서 똑같이 동작하는 표준 SQL만 쓴다.
  - 시각은 DB 함수(`NOW()`) 대신 PHP에서 만든 `Y-m-d H:i:s` 문자열로 넣는다.
  - 처리 시간 계산은 `TIMESTAMPDIFF`(MySQL) / `julianday`(SQLite) 대신 PHP에서 한다.
  - LIKE 검색의 이스케이프 문자는 두 DB 모두 같은 `ESCAPE '!'` 문법을 쓴다.
- 테스트는 기본으로 메모리 SQLite에서 돌고, `TEST_DB_DSN`을 주면 MySQL에서 같은 테스트가 돈다.
  CI는 두 DB 모두 돌린다.

## 결과

- 좋은 점: PHP만 있으면 바로 시연·테스트할 수 있고, MySQL에서도 같은 테스트로 확인된다.
- 나쁜 점: DB 고유 기능(MySQL 전문 검색, 윈도 함수 일부)을 쓰기 어렵다. 필요해지면 그 부분만 드라이버별로 나눈다.
