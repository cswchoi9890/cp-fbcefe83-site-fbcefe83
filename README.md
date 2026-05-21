# 지원인

CloudPress로 생성된 WordPress 사이트입니다.

## 아키텍처
```
GitHub Actions (PHP CLI)
      |
      v
WordPress 실제 실행 (php-cli + SQLite)
      |
      v
Cloudflare Worker = 순수 미러링
(wordpress/ 폴더를 PHP CLI로 실행 → 응답을 프록시)
```

## 사이트 정보
- **URL**: https://cp-fbcefe83-wp.choichoi3227.workers.dev
- **관리자 ID**: `wp_hrst2n1g`
- **Worker 이름**: `cp-fbcefe83-wp`
- **GitHub**: [cswchoi9890/cp-fbcefe83-site-fbcefe83](https://github.com/cswchoi9890/cp-fbcefe83-site-fbcefe83)

## 파일 구조
```
├── wordpress/            ← 100% 실제 WordPress (변환 없음)
│   ├── wp-content/
│   │   ├── themes/       ← 테마 (그대로 사용)
│   │   └── plugins/      ← 플러그인 (그대로 사용)
│   └── wp-config.php     ← SQLite 연결 설정
├── _db/
│   └── wordpress.db      ← SQLite DB
├── _cache/               ← 정적 HTML 캐시 (SEO 폴백)
└── .github/workflows/
    ├── install-wordpress.yml  ← WP 초기 설치 (PHP CLI + SQLite)
    ├── wp-sync.yml            ← WP 업데이트 동기화 (WP-CLI)
    ├── static-cache.yml       ← nginx+PHP-FPM 실시간 서버 + SEO 캐시
    └── php-keepalive.yml      ← PHP 서버 상시 가동 (5분마다 keep-alive)
```

## WordPress 관리
- 플러그인/테마 변환 없이 100% 원본 그대로 사용
- WordPress 업데이트 자동 반영
- SQLite 기반으로 별도 DB 서버 불필요
