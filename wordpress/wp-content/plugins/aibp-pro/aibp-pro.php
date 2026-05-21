<?php
/**
 * Plugin Name: AIBP Pro: AI Blog Posting
 * Version: 1.2.2
 * Author: 최성원
 * Text Domain: aibp-pro
 * Description: AI 블로그 자동 작성 + 2026 Elite SEO + 네이버 웹문서 1위 + 멀티스키마 완전지원 + 서브키워드 자동활용 + 탬플릿/유사문서 0% + 애드센스 수익화 최적화 + H2/H3 구조 최적화 + strong/u 태그 균형 사용
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>AIBP Pro: PHP 7.4 이상 필요.</p></div>';
    } );
    return;
}

define( 'AIBP_PRO_VERSION', '1.2.0' );
define( 'AIBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIBP_URL', plugin_dir_url( __FILE__ ) );

class AIBP_Pro {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes',        [ $this, 'register_metabox' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu',            [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'init',                  [ $this, 'register_seo_post_meta' ] );
        add_action( 'save_post',             [ $this, 'save_seo_meta_fields' ] );

        // AJAX handlers
        add_action( 'wp_ajax_ai_blog_generate',            [ $this, 'ajax_generate_content' ] );
        add_action( 'wp_ajax_ai_blog_expand_content',      [ $this, 'ajax_expand_content' ] );
        add_action( 'wp_ajax_ai_blog_generate_schema',     [ $this, 'ajax_generate_schema' ] );
        add_action( 'wp_ajax_ai_blog_save_schema_markup',   [ $this, 'ajax_save_schema_markup' ] );
        add_action( 'wp_ajax_ai_blog_delete_schema',       [ $this, 'ajax_delete_schema' ] );
        add_action( 'wp_ajax_ai_blog_save_seo_meta',       [ $this, 'ajax_save_all_seo_meta' ] );
        add_action( 'wp_ajax_ai_blog_generate_thumbnail',  [ $this, 'ajax_generate_thumbnail' ] );
        add_action( 'wp_ajax_ai_blog_generate_image_prompt', [ $this, 'ajax_generate_image_prompt' ] );
        add_action( 'wp_ajax_aibp_pollinations_generate',   [ $this, 'ajax_pollinations_generate' ] );
        add_action( 'wp_ajax_aibp_save_template',           [ $this, 'ajax_save_template' ] );
        add_action( 'wp_ajax_aibp_delete_template',         [ $this, 'ajax_delete_template' ] );
        add_action( 'wp_ajax_aibp_save_font_path',           [ $this, 'ajax_save_font_path' ] );
        add_action( 'wp_ajax_aibp_upload_template_image',    [ $this, 'ajax_upload_template_image' ] );


        add_action( 'wp_head', [ $this, 'insert_schema_markup' ], 99 );
        add_action( 'wp_head', [ $this, 'insert_seo_meta_tags' ], 1 );  // ✅ SEO 메타 자동 출력
    }

    /* ── 포스트 메타 등록 ── */
    public function register_seo_post_meta() {
        $fields = [ '_ai_seo_title', '_ai_meta_desc', '_ai_slug', '_ai_focus_keyword', '_ai_blog_schema_markup' ];
        foreach ( $fields as $key ) {
            register_post_meta( 'post', $key, [
                'show_in_rest'  => true, 'single' => true, 'type' => 'string',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ] );
        }
    }

    /* ── 메타박스 ── */
    public function register_metabox() {
        add_meta_box( 'ai-blog-writer-box', 'AI 블로그 작성기 Pro', [ $this, 'render_metabox' ], 'post', 'side', 'high' );
    }

    public function render_metabox( $post ) {
        wp_nonce_field( 'ai_blog_writer_nonce', 'ai_blog_writer_nonce' );
        $seo_title = get_post_meta( $post->ID, '_ai_seo_title',          true );
        $meta_desc = get_post_meta( $post->ID, '_ai_meta_desc',          true );
        $slug      = get_post_meta( $post->ID, '_ai_slug',               true );
        $focus_kw  = get_post_meta( $post->ID, '_ai_focus_keyword',      true );
        $schema    = get_post_meta( $post->ID, '_ai_blog_schema_markup', true );
        $schemas   = $this->decode_schemas( $schema );
        ?>
        <div id="ai-blog-writer-container">

            <!-- 탭 헤더 -->
            <div class="ai-blog-tabs">
                <button type="button" class="ai-blog-tab active" data-tab="content">AI 글쓰기</button>
                <button type="button" class="ai-blog-tab" data-tab="thumbnail">AI 썸네일</button>
            </div>

            <!-- AI 글쓰기 탭 -->
            <div class="ai-blog-tab-content active" data-content="content">
                <div class="ai-blog-input-group">
                    <label for="ai-blog-topic">주제 키워드</label>
                    <input type="text" id="ai-blog-topic" class="ai-blog-input" placeholder="예: 민생회복지원금" />
                </div>
                <div class="ai-blog-input-group">
                    <label for="ai-blog-type">글 유형</label>
                    <select id="ai-blog-type" class="ai-blog-select">
                        <option value="informational">정보성</option>
                        <option value="utility">유틸리티</option>
                        <option value="policy_guide">정책·공공</option>
                        <option value="review_comparison">리뷰·비교 (쿠팡파트너스)</option>
                    </select>
                </div>

                <div style="text-align:left;margin-top:6px;">
                <button type="button" id="ai-blog-generate-btn" class="ai-blog-button ai-blog-button--primary">AI 콘텐츠 생성</button>
                </div>
                <div id="ai-blog-progress" class="ai-blog-progress" style="display:none;">
                    <div class="ai-blog-progress-bar">
                        <div class="ai-blog-progress-fill"></div>
                    </div>
                    <div class="ai-blog-progress-text">
                        <span class="progress-label">AI 처리 시작 중</span>
                        <span class="progress-percent">0%</span>
                    </div>
                </div>

                <!-- SEO 메타 정보 숨김 필드 (자동 저장용) -->
                <input type="hidden" id="ai_seo_title" name="ai_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" />
                <input type="hidden" id="ai_meta_desc" name="ai_meta_desc" value="<?php echo esc_attr( $meta_desc ); ?>" />
                <input type="hidden" id="ai_slug" name="ai_slug" value="<?php echo esc_attr( $slug ); ?>" />
                <input type="hidden" id="ai_focus_keyword" name="ai_focus_keyword" value="<?php echo esc_attr( $focus_kw ); ?>" />
            </div>

            <!-- AI 썸네일 탭 -->
            <div class="ai-blog-tab-content" data-content="thumbnail">
                <div class="ai-blog-input-group">
                    <label for="ai-thumb-topic">썸네일 주제</label>
                    <input type="text" id="ai-thumb-topic" class="ai-blog-input"
                           placeholder="예: 다이어트 방법, 재테크 전략" />
                </div>
                <div class="ai-blog-input-group">
                    <label for="ai-thumb-style">이미지 스타일</label>
                    <select id="ai-thumb-style" class="ai-blog-select">
                        <optgroup label="AI 이미지 생성">
                            <option value="poster">포스터</option>
                            <option value="minimal">미니멀</option>
                            <option value="photo_realistic">사실적 사진</option>
                            <option value="typography">타이포그래피</option>
                            <option value="branding">브랜딩</option>
                        </optgroup>
                        <?php
                        $_aibp_tpls = get_option( 'aibp_thumb_templates', [] );
                        if ( ! empty( $_aibp_tpls ) ) :
                        ?>
                        <optgroup label="자체 썸네일 템플릿">
                            <?php foreach ( $_aibp_tpls as $_ti => $_t ) : ?>
                                <option value="custom_tpl_<?php echo $_ti; ?>"><?php echo esc_html( $_t['name'] ); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <!-- 자체 템플릿 선택 시 나타나는 제목/부제목 입력 영역 -->
                <div id="aibp-custom-tpl-inputs" style="display:none;">
                    <div class="ai-blog-input-group">
                        <label for="aibp-tpl-title-input">📝 제목</label>
                        <input type="text" id="aibp-tpl-title-input" class="ai-blog-input" placeholder="썸네일에 표시할 제목" />
                    </div>
                    <div class="ai-blog-input-group">
                        <label for="aibp-tpl-sub-input">📌 부제목</label>
                        <input type="text" id="aibp-tpl-sub-input" class="ai-blog-input" placeholder="썸네일에 표시할 부제목" />
                    </div>
                </div>
                <div style="text-align:left;margin-top:6px;">
                <button type="button" id="ai-thumb-generate-btn" class="ai-blog-button ai-blog-button--primary">🖼️ 썸네일 생성</button>
                </div>
                <div id="ai-thumb-progress" style="display:none;margin-top:10px;text-align:center;padding:12px;background:#f7f9ff;border-radius:8px;">
                    <div class="aibp-spin-loader"></div>
                    <div id="ai-thumb-progress-text" style="margin:8px 0 0;font-size:12px;color:#555;text-align:center;">⏳ 처리 중...</div>
                </div>
                <div id="ai-thumb-preview" style="display:none;margin-top:12px;">
                    <img id="ai-thumb-img" src="" alt="썸네일 미리보기"
                         style="width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.15);">
                    <p style="font-size:11px;color:#888;margin:6px 0 0;text-align:center;">✅ 대표 이미지 설정 완료</p>
                </div>
                <span id="ai-thumb-status" style="display:block;margin-top:8px;font-size:12px;min-height:16px;"></span>
            </div>

            <!-- 구분선 -->
            <hr style="border:none;border-top:2px solid #f0f0f0;margin:0;">

            <!-- AI 스키마 마크업 섹션 -->
            <div id="ai-schema-section" style="padding:16px 20px 20px;">
                <div style="font-size:14px;font-weight:700;color:#262626;margin-bottom:10px;">⭐ AI 스키마 마크업 (멀티 지원)</div>

                <div class="ai-blog-input-group" style="margin-bottom:8px;">
                    <select id="ai-schema-type" class="ai-blog-select">
                        <option value="">스키마 유형 선택</option>
                        <option value="article">기사 (Article)</option>
                        <option value="faq">FAQ</option>
                        <option value="product_review">상품리뷰 (Product Review)</option>
                    </select>
                </div>

                <div style="text-align:left;margin-top:6px;"><button type="button" id="ai-schema-generate-btn" class="ai-blog-button ai-blog-button--primary" style="margin-top:0;">
                    ➕ 스키마 추가 생성
                </button></div>

                <!-- 진행 단계 표시 -->
                <div id="ai-schema-progress" style="display:none;margin-top:10px;padding:12px;background:#f0f7ff;border-radius:8px;border:1px solid #b3d4f5;">
                    <div id="ai-schema-step" style="font-size:12px;font-weight:600;color:#0066cc;">⏳ 스키마 분석 중...</div>
                    <div style="margin-top:6px;height:4px;background:#ddeeff;border-radius:4px;overflow:hidden;">
                        <div id="ai-schema-progress-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#0095f6,#00d4ff);border-radius:4px;transition:width 0.6s ease;"></div>
                    </div>
                </div>

                <!-- 적용된 스키마 목록 -->
                <div id="ai-schema-list" style="margin-top:10px;">
                    <?php if ( ! empty( $schemas ) ) : ?>
                        <?php foreach ( $schemas as $idx => $s ) :
                            $json_str = '';
                            if ( ! empty( $s['json'] ) ) {
                                $json_str = is_string( $s['json'] ) ? $s['json'] : wp_json_encode( $s['json'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
                            } elseif ( ! empty( $s['html'] ) && preg_match( '/<script[^>]*>([\s\S]*?)<\/script>/i', $s['html'], $jm ) ) {
                                $json_str = trim( $jm[1] );
                            }
                        ?>
                        <div class="aibp-schema-item"
                             data-index="<?php echo $idx; ?>"
                             data-type="<?php echo esc_attr( $s['type'] ); ?>"
                             data-json="<?php echo esc_attr( $json_str ); ?>">
                            <div class="aibp-schema-item-header">
                                <span class="aibp-schema-item-label">✅ <?php echo esc_html( strtoupper( $s['type'] ) ); ?> 스키마</span>
                                <div class="aibp-schema-item-actions">
                                    <button type="button" class="aibp-small-btn aibp-btn-edit aibp-schema-edit-single" data-index="<?php echo $idx; ?>">✏️ 편집</button>
                                    <button type="button" class="aibp-small-btn aibp-btn-danger aibp-schema-delete-single" data-index="<?php echo $idx; ?>">🗑 삭제</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $schemas ) ) : ?>
                <button type="button" id="ai-schema-delete-all-btn" class="aibp-small-btn aibp-btn-danger" style="margin-top:8px;width:100%;">🗑 전체 스키마 삭제</button>
                <?php endif; ?>

                <span id="ai-schema-status" style="display:block;margin-top:8px;font-size:12px;min-height:16px;"></span>
            </div>

            <!-- 결과 메시지 -->
            <div id="ai-blog-result" class="ai-blog-result" style="display:none;margin:0 20px 16px;"></div>

        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════
       스키마 디코드 헬퍼 v4.0 — 완전 재작성
       규칙: json 필드는 항상 "pretty-printed 문자열"로 반환
       DB 저장 형식: [{"type":"article","json":{...객체...}}, ...]
       — json 필드를 객체로 저장하여 이중인코딩 문제 완전 제거
    ══════════════════════════════════════════════════════ */
    private function decode_schemas( $raw ) {
        if ( empty( $raw ) ) return [];

        $str = trim( $raw );
        if ( empty( $str ) ) return [];

        // ── STEP 1: 최외곽 JSON 파싱 ──
        $outer = json_decode( $str, true );

        // ── STEP 2: JSON 배열 형식 (표준 멀티 스키마) ──
        if ( is_array( $outer ) && isset( $outer[0] ) ) {
            $result = [];
            foreach ( $outer as $item ) {
                if ( ! is_array( $item ) || empty( $item['type'] ) ) continue;

                $schema_obj = null;
                // json 필드: 객체(새 형식) or 문자열(구 형식) 둘 다 처리
                if ( isset( $item['json'] ) ) {
                    if ( is_array( $item['json'] ) ) {
                        $schema_obj = $item['json'];
                    } elseif ( is_string( $item['json'] ) && ! empty( $item['json'] ) ) {
                        $parsed = json_decode( $item['json'], true );
                        if ( is_array( $parsed ) ) $schema_obj = $parsed;
                    }
                }
                // 레거시: html 필드 → script 태그에서 추출
                if ( ! $schema_obj && ! empty( $item['html'] ) ) {
                    if ( preg_match( '/<script[^>]*>([\s\S]*?)<\/script>/i', $item['html'], $m ) ) {
                        $parsed = json_decode( trim( $m[1] ), true );
                        if ( is_array( $parsed ) ) $schema_obj = $parsed;
                    }
                }

                if ( $schema_obj ) {
                    $result[] = [
                        'type' => sanitize_key( $item['type'] ),
                        'json' => wp_json_encode( $schema_obj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
                    ];
                }
            }
            return $result;
        }

        // ── STEP 3: 단일 JSON 객체 (레거시 형식) ──
        if ( is_array( $outer ) && ! empty( $outer['@type'] ) ) {
            $type = strtolower( sanitize_key( $outer['@type'] ) );
            return [ [
                'type' => $type,
                'json' => wp_json_encode( $outer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            ] ];
        }

        // ── STEP 4: 레거시 — script 태그 포함 문자열 ──
        if ( preg_match( '/<script[^>]*>([\s\S]*?)<\/script>/i', $str, $m ) ) {
            $parsed = json_decode( trim( $m[1] ), true );
            if ( is_array( $parsed ) ) {
                $type = isset( $parsed['@type'] ) ? strtolower( sanitize_key( $parsed['@type'] ) ) : 'schema';
                return [ [
                    'type' => $type,
                    'json' => wp_json_encode( $parsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
                ] ];
            }
        }

        return [];
    }

    /* ══════════════════════════════════════════════════════
       스키마 저장 헬퍼 v4.0 — 완전 재작성
       DB에 json 필드를 객체(배열)로 저장 → 이중인코딩 제거
       반환값: decode_schemas() 형식 (json은 pretty-string)
    ══════════════════════════════════════════════════════ */
    private function save_schemas( $post_id, array $schemas ) {
        $to_save = [];
        foreach ( $schemas as $s ) {
            if ( empty( $s['type'] ) ) continue;

            // json 필드를 반드시 PHP 배열(객체)로 변환
            $schema_obj = null;
            if ( isset( $s['json'] ) ) {
                if ( is_array( $s['json'] ) ) {
                    $schema_obj = $s['json'];
                } elseif ( is_string( $s['json'] ) && ! empty( $s['json'] ) ) {
                    $parsed = json_decode( $s['json'], true );
                    if ( is_array( $parsed ) ) $schema_obj = $parsed;
                }
            }
            if ( ! $schema_obj ) continue;

            $to_save[] = [
                'type' => sanitize_key( $s['type'] ),
                'json' => $schema_obj,   // ✅ 객체로 저장 (이중인코딩 없음)
            ];
        }

        if ( empty( $to_save ) ) {
            delete_post_meta( $post_id, '_ai_blog_schema_markup' );
        } else {
            $encoded = wp_json_encode( $to_save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            update_post_meta( $post_id, '_ai_blog_schema_markup', $encoded );
        }

        // 반환값은 항상 decode_schemas 형식 (json = pretty string)
        return $this->decode_schemas(
            wp_json_encode( $to_save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        );
    }

    /* ── save_post ── */
    public function save_seo_meta_fields( $post_id ) {
        if ( ! isset( $_POST['ai_blog_writer_nonce'] ) || ! wp_verify_nonce( $_POST['ai_blog_writer_nonce'], 'ai_blog_writer_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $fields = [ 'ai_seo_title' => '_ai_seo_title', 'ai_meta_desc' => '_ai_meta_desc', 'ai_slug' => '_ai_slug', 'ai_focus_keyword' => '_ai_focus_keyword' ];
        foreach ( $fields as $k => $meta ) {
            if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $meta, sanitize_text_field( $_POST[ $k ] ) );
        }
    }

    /* ── 에셋 ── */
    public function enqueue_assets( $hook ) {
        $allowed_hooks = [
            'post.php',
            'post-new.php',
            'settings_page_ai-blog-writer-settings',
        ];
        if ( ! in_array( $hook, $allowed_hooks ) ) return;

        // 미디어 업로더 (템플릿 관리 페이지에서 필수)
        wp_enqueue_media();

        wp_enqueue_style( 'aibp-admin', AIBP_URL . 'assets/style-pro.css', [], AIBP_PRO_VERSION );
        wp_enqueue_script( 'aibp-admin', AIBP_URL . 'assets/script-pro.js', [ 'jquery' ], AIBP_PRO_VERSION, true );
        global $post;
        $aibp_tpl_data = get_option( 'aibp_thumb_templates', [] );
        wp_localize_script( 'aibp-admin', 'aiBlogWriter', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ai_blog_writer_nonce' ),
            'postId'    => isset( $post->ID ) ? $post->ID : 0,
            'templates' => array_values( $aibp_tpl_data ),
        ] );
    }

    /* ── 설정 ── */
    public function add_settings_page() {
        add_options_page( 'AI 블로그 작성기 설정', 'AI 블로그 작성기', 'manage_options', 'ai-blog-writer-settings', [ $this, 'render_settings_page' ] );
        add_options_page( 'AI 썸네일 템플릿 관리', 'AIBP 썸네일 템플릿', 'manage_options', 'aibp-thumb-templates', [ $this, 'render_template_manager' ] );
    }

    public function register_settings() {
        // ✅ v3.7.0: API 키 입력란 1개로 통합 (Rate Limit 시 내부 재시도 로직 자동 처리)
        register_setting( 'ai_blog_writer_settings', 'ai_blog_writer_gemini_api_key' );
        register_setting( 'ai_blog_writer_settings', 'aibp_cf_worker_url' );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( isset( $_GET['settings-updated'] ) ) add_settings_error( 'aibp_msg', 'aibp_saved', '설정이 저장되었습니다.', 'updated' );
        settings_errors( 'aibp_msg' );
        $api_key    = get_option( 'ai_blog_writer_gemini_api_key', '' );
        $worker_url = get_option( 'aibp_cf_worker_url', '' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <!-- 통합 설정 폼 -->
            <div style="background:#fff;padding:24px;margin:20px 0;border:1px solid #ccd0d4;max-width:800px;">
                <form method="post" action="options.php">
                    <?php settings_fields( 'ai_blog_writer_settings' ); ?>
                    <h2 style="border-bottom:2px solid #f0f0f0;padding-bottom:12px;">🔑 API 설정</h2>

                    <table class="form-table">
                        <tr>
                            <th><label for="ai_blog_writer_gemini_api_key">Gemini API 키 <span style="color:#d32f2f;">*필수</span></label></th>
                            <td>
                                <input type="text" id="ai_blog_writer_gemini_api_key" name="ai_blog_writer_gemini_api_key"
                                       value="<?php echo esc_attr( $api_key ); ?>"
                                       style="width:100%;max-width:600px;font-family:monospace;" placeholder="AIzaSy..." />
                                <?php if ( ! empty( $api_key ) ) : ?>
                                    <span style="color:#4caf50;font-weight:600;margin-left:8px;">✅ 설정됨</span>
                                <?php else : ?>
                                    <span style="color:#f44336;font-weight:600;margin-left:8px;">⚠️ 미설정</span>
                                <?php endif; ?>
                                <p class="description"><a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>에서 무료 발급 · 콘텐츠 생성 + 스키마 + 썸네일 프롬프트에 사용</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="aibp_cf_worker_url">Cloudflare Worker URL <span style="color:#d32f2f;">*이미지 생성 필수</span></label></th>
                            <td>
                                <input type="text" id="aibp_cf_worker_url" name="aibp_cf_worker_url"
                                       value="<?php echo esc_attr( $worker_url ); ?>"
                                       style="width:100%;max-width:600px;font-family:monospace;" placeholder="https://your-worker.your-subdomain.workers.dev" />
                                <?php if ( ! empty( $worker_url ) ) : ?>
                                    <span style="color:#4caf50;font-weight:600;margin-left:8px;">✅ 설정됨</span>
                                <?php else : ?>
                                    <span style="color:#f44336;font-weight:600;margin-left:8px;">⚠️ 미설정 — Worker URL을 입력해야 이미지 생성이 가능합니다</span>
                                <?php endif; ?>
                                <p class="description">
                                    Cloudflare Dashboard → Workers &amp; Pages → Worker 생성 → AI 바인딩 추가 후 URL 입력<br>
                                    <strong>Worker 설정:</strong> Settings → Bindings → AI → 변수명: <code>AI</code>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( '설정 저장' ); ?>
                </form>
            </div>

            <!-- 자체 썸네일 템플릿 안내 -->
            <div style="background:#e3f2fd;padding:16px 20px;margin:20px 0;border:1px solid #90caf9;max-width:800px;border-radius:6px;">
                <h3 style="margin:0 0 8px;color:#0d47a1;">🖼️ 자체 썸네일 템플릿</h3>
                <p style="margin:0;color:#333;">
                    <strong>AIBP 썸네일 템플릿</strong> 메뉴에서 배경 이미지를 업로드하고 제목·부제목 텍스트 박스의 <strong>위치</strong>를 지정해 두세요.<br>
                    포스트 편집 시 해당 템플릿을 선택하면 제목·부제목 입력란이 나타나며, 직접 입력한 텍스트가 지정된 위치에 합성됩니다.
                </p>
                <p style="margin:8px 0 0;">
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=aibp-thumb-templates' ) ); ?>" class="button button-secondary">→ 템플릿 관리 페이지 이동</a>
                </p>
            </div>
        </div>
        <?php
    }

    /* ── Gemini API 키 (v3.7.0: 단일 키, 내부 자동 재시도) ── */
    private function get_api_keys() {
        $k = trim( get_option( 'ai_blog_writer_gemini_api_key', '' ) );
        return ! empty( $k ) ? [ $k ] : [];
    }

    private function get_next_api_key( array $keys, $exclude = [] ) {
        $available = array_values( array_diff( $keys, $exclude ) );
        if ( empty( $available ) ) return null;
        // 라운드로빈: transient 기반 인덱스
        $idx_key = 'aibp_key_idx_' . md5( implode( '', $keys ) );
        $idx     = (int) get_transient( $idx_key );
        $idx     = $idx % count( $available );
        set_transient( $idx_key, ( $idx + 1 ) % count( $available ), 3600 );
        return $available[ $idx ];
    }

    private function call_gemini_api( $body, $timeout = 130, $model = 'gemini-2.5-flash' ) {
        $keys = $this->get_api_keys();
        if ( empty( $keys ) ) return new WP_Error( 'no_api_key', 'Gemini API 키가 설정되지 않았습니다. 설정 페이지에서 API 키를 최소 1개 입력해주세요.' );
        $excluded = [];
        $max_try  = min( count( $keys ) * 2, 6 ); // 키당 최대 2번, 총 6회 이내

        for ( $attempt = 0; $attempt < $max_try; $attempt++ ) {
            $api_key = $this->get_next_api_key( $keys, $excluded );
            if ( ! $api_key ) {
                // 모든 키가 일시 제한 → 짧게 대기 후 excluded 초기화 재시도
                sleep( 5 );
                $excluded = [];
                $api_key  = $keys[0];
            }

            $api_url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
            $response = wp_remote_post( $api_url, [
                'timeout' => $timeout,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $response ) ) {
                // 네트워크 오류는 잠시 후 재시도
                if ( $attempt < $max_try - 1 ) { sleep( 2 ); continue; }
                return new WP_Error( 'api_error', 'API 요청 실패: ' . $response->get_error_message() );
            }

            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code === 200 ) return $data;

            // 429(Rate Limit) 또는 503 → 해당 키 제외 후 다음 키로
            if ( in_array( $code, [ 429, 503 ], true ) ) {
                $excluded[] = $api_key;
                // 지수 백오프: 1초, 2초, 4초…
                $wait = min( pow( 2, $attempt ), 16 );
                sleep( (int) $wait );
                continue;
            }

            // 기타 오류
            $err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
            return new WP_Error( 'api_error', 'API 오류: ' . $err );
        }

        return new WP_Error( 'api_error', 'API 요청이 여러 번 실패했습니다. 잠시 후 다시 시도해주세요.' );
    }

    /* ── AJAX: 콘텐츠 생성 ── */
    public function ajax_generate_content() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $topic              = isset( $_POST['topic'] )              ? sanitize_text_field( $_POST['topic'] )              : '';
        $type               = isset( $_POST['type'] )               ? sanitize_text_field( $_POST['type'] )               : 'informational';
        $post_id            = isset( $_POST['post_id'] )            ? absint( $_POST['post_id'] )                          : 0;
        if ( empty( $topic ) ) wp_send_json_error( [ 'message' => '주제를 입력해주세요.' ] );

        try {
            $result = $this->generate_blog_content( $topic, $type );
            if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );

            $meta_info = isset( $result['meta_info'] ) ? $result['meta_info'] : [];
            // 제목 자동 삽입 제거 — 포스트 제목은 사용자가 직접 작성
            unset( $meta_info['title'] );

            if ( $post_id > 0 ) {
                // SEO 메타 저장 (제목 제외)
                if ( ! empty( $meta_info['meta_desc'] ) )     update_post_meta( $post_id, '_ai_meta_desc',     sanitize_text_field( $meta_info['meta_desc'] ) );
                if ( ! empty( $meta_info['slug'] ) )          update_post_meta( $post_id, '_ai_slug',          sanitize_text_field( $meta_info['slug'] ) );
                if ( ! empty( $meta_info['focus_keyword'] ) ) update_post_meta( $post_id, '_ai_focus_keyword', sanitize_text_field( $meta_info['focus_keyword'] ) );

                // 슬러그 업데이트 (한글 유지)
                if ( ! empty( $meta_info['slug'] ) ) {
                    $korean_slug = $meta_info['slug'];
                    global $wpdb;
                    $wpdb->update( $wpdb->posts, [ 'post_name' => $korean_slug ], [ 'ID' => $post_id ], [ '%s' ], [ '%d' ] );
                }

                // Rank Math SEO 자동 연동 (제목 제외)
                if ( ! empty( $meta_info['meta_desc'] ) )     update_post_meta( $post_id, 'rank_math_description',     sanitize_text_field( $meta_info['meta_desc'] ) );
                if ( ! empty( $meta_info['focus_keyword'] ) ) update_post_meta( $post_id, 'rank_math_focus_keyword',   sanitize_text_field( $meta_info['focus_keyword'] ) );
            }

            $raw  = $result['html'];
            $raw  = preg_replace( '/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $raw );
            $raw  = preg_replace( '/\*(.+?)\*/us', '$1', $raw );
            $raw  = str_replace( '*', '', $raw );
            // ── H4 태그 완전 제거: h4 → h3 로 상향 변환 (콘텐츠 손실 없이 제거) ──
            $raw  = preg_replace( '/<h4([^>]*)>/i', '<h3$1>', $raw );
            $raw  = preg_replace( '/<\/h4>/i', '</h3>', $raw );
            $html = $this->strip_seo_from_content( $raw );
            $html = $this->ensure_description_first( $html, $meta_info['meta_desc'] ?? '', $topic );

            wp_send_json_success( [ 'html' => $html, 'meta_info' => $meta_info ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => '오류: ' . $e->getMessage() ] );
        }
    }

    /* ── AJAX: 콘텐츠 확장 (선택 문장 → 3~4문장 인라인 확장) ── */
    public function ajax_expand_content() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $selected     = isset( $_POST['selected_text'] ) ? sanitize_textarea_field( $_POST['selected_text'] ) : '';
        $full_content = isset( $_POST['full_content'] )  ? sanitize_textarea_field( $_POST['full_content'] )  : '';
        $post_title   = isset( $_POST['post_title'] )    ? sanitize_text_field( $_POST['post_title'] )        : '';
        if ( empty( $selected ) ) wp_send_json_error( [ 'message' => '확장할 텍스트를 선택해주세요.' ] );

        $context_block = '';
        if ( ! empty( $post_title ) )   $context_block .= "글 제목: {$post_title}\n";
        if ( ! empty( $full_content ) ) $context_block .= "전체 글 내용(일부):\n" . mb_substr( $full_content, 0, 1500, 'UTF-8' ) . "\n";

        $prompt = "당신은 한국어 블로그 전문 작가입니다.
아래 [원본 문장]을 3~4개 문장으로 확장하여 반환하세요.

[전체 글 컨텍스트]
{$context_block}

[원본 문장 — 이 문장을 3~4개 문장으로 확장]
{$selected}

【핵심 지시사항】
1. 원본 문장의 핵심 내용과 의미를 반드시 유지하세요.
2. 원본 문장을 더 구체적이고 상세하게 풀어서 3~4개 문장으로 확장하세요.
3. 원본 문장의 내용을 첫 문장에 자연스럽게 포함시키세요.
4. 추가 문장들은 구체적인 수치·예시·이유로 원본 내용을 뒷받침하세요.
5. 전체 글의 흐름과 주제에 맞게 자연스럽게 이어지도록 작성하세요.
6. 각 문장은 반드시 20~70자 이내로 작성하세요 (너무 짧아도 너무 길어도 안 됨).

⚠️ 절대 금지
- 원본 문장과 무관한 새로운 주제 도입 금지
- 새로운 H2/H3 섹션 생성 금지 (인라인 확장만)
- 마크다운, 별표(*), 한자 금지
- '결론적으로', '이상으로', '살펴보았습니다', '알아보았습니다', '정리해드리겠습니다' 등 마무리·안내 표현 금지
- '본문에서는', '이어서는', '다음 섹션에서는' 등 구조 안내 표현 금지
- 15자 미만의 너무 짧은 문장 금지
- 80자 초과의 너무 긴 문장 금지
- 500자 초과 금지 (간결하게 3~4문장만)

✅ 출력 형식 (반드시 준수)
- 확장된 3~4개 문장만 출력
- HTML 태그 완전 금지 (p태그·br태그·h태그 모두 금지)
- 마크다운 완전 금지 (별표·샵·백틱 모두 금지)
- 줄바꿈 없이 이어지는 하나의 단락
- 한국어만 사용 / 영어·한자 금지
- 앞에 번호·기호·따옴표 붙이지 말 것

오직 확장된 3~4개 문장만 출력하세요:";

        $body = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'temperature' => 0.70, 'maxOutputTokens' => 600 ],
            'tools'            => [ [ 'google_search' => (object)[] ] ],
        ];
        $data = $this->call_gemini_api( $body, 60 );
        if ( is_wp_error( $data ) ) wp_send_json_error( [ 'message' => $data->get_error_message() ] );
        $text = $this->extract_text( $data );
        if ( is_wp_error( $text ) ) wp_send_json_error( [ 'message' => $text->get_error_message() ] );

        // 마크다운·코드블록·HTML 태그 정리
        $text = preg_replace( '/```[\s\S]*?```/i', '', $text );
        $text = preg_replace( '/\*\*(.+?)\*\*/us', '$1', $text );
        $text = str_replace( '*', '', $text );
        $text = strip_tags( $text );  // 순수 텍스트만 반환
        $text = trim( $text );

        // 최소 글자 검증
        if ( mb_strlen( $text, 'UTF-8' ) < 30 ) {
            wp_send_json_error( [ 'message' => '확장 결과가 너무 짧습니다. 다시 시도해주세요.' ] );
        }

        wp_send_json_success( [ 'expanded_text' => $text, 'message' => 'AI 콘텐츠 확장 완료' ] );
    }

        /* ── AJAX: 스키마 생성 ── */
    public function ajax_generate_schema() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $post_id     = isset( $_POST['post_id'] )     ? absint( $_POST['post_id'] )                  : 0;
        $schema_type = isset( $_POST['schema_type'] ) ? sanitize_text_field( $_POST['schema_type'] ) : '';
        $content_raw = isset( $_POST['content'] )     ? wp_strip_all_tags( $_POST['content'] )       : '';
        if ( ! $post_id || ! $schema_type ) wp_send_json_error( [ 'message' => '포스트 ID 또는 스키마 유형이 없습니다.' ] );

        $title     = get_post_meta( $post_id, '_ai_seo_title',     true ) ?: get_the_title( $post_id );
        $meta_desc = get_post_meta( $post_id, '_ai_meta_desc',     true ) ?: '';
        $focus_kw  = get_post_meta( $post_id, '_ai_focus_keyword', true ) ?: '';
        $post_url  = get_permalink( $post_id ) ?: get_site_url();
        $site_name = get_bloginfo( 'name' ) ?: '블로그';
        if ( empty( $content_raw ) ) {
            $post_obj    = get_post( $post_id );
            $content_raw = $post_obj ? wp_strip_all_tags( $post_obj->post_content ) : '';
        }

        $prompt = $this->build_schema_prompt( $schema_type, $title, $meta_desc, $focus_kw, $content_raw, $post_url, $site_name );
        $body   = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'maxOutputTokens'  => 3000,
                'topP'             => 0.8,
                'responseMimeType' => 'application/json',
            ],
        ];
        $data   = $this->call_gemini_api( $body, 60 );
        if ( is_wp_error( $data ) ) wp_send_json_error( [ 'message' => $data->get_error_message() ] );

        $json_text = '';
        if ( isset( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                if ( isset( $part['text'] ) ) $json_text .= $part['text'];
            }
        }

        // 마크다운 코드블록 제거 (```json ... ``` / ``` ... ```)
        $json_text = preg_replace( '/^```(?:json)?\s*/im', '', $json_text );
        $json_text = preg_replace( '/```\s*$/m', '', $json_text );
        // BOM 및 불가시 제어문자 제거
        $json_text = preg_replace( '/^\xEF\xBB\xBF/', '', $json_text );
        $json_text = trim( $json_text );

        $decoded = json_decode( $json_text, true );

        // 1차 실패: 텍스트 내 첫 번째 JSON 오브젝트 추출 시도
        if ( ! $decoded ) {
            if ( preg_match( '/(\{[\s\S]*\})/m', $json_text, $m ) ) {
                $decoded = json_decode( $m[1], true );
            }
        }

        // 2차 실패: trailing 콤마 제거 후 재시도
        if ( ! $decoded ) {
            $cleaned = preg_replace( '/,\s*([\}\]])/m', '$1', $json_text );
            $decoded = json_decode( $cleaned, true );
            if ( ! $decoded && preg_match( '/(\{[\s\S]*\})/m', $cleaned, $m ) ) {
                $decoded = json_decode( $m[1], true );
            }
        }

        if ( ! $decoded ) {
            wp_send_json_error( [ 'message' => '스키마 JSON 파싱 실패. 다시 시도해주세요. (오류: ' . json_last_error_msg() . ')' ] );
        }

        // ✅ v4.0 멀티 스키마 완전 재작성: 기존 스키마 로드 → 같은 타입만 교체 → 저장
        $existing_raw = get_post_meta( $post_id, '_ai_blog_schema_markup', true );
        $schemas      = $this->decode_schemas( $existing_raw );

        // 같은 타입은 제거 (교체), 다른 타입은 반드시 보존 → 멀티 스키마 핵심
        $schemas = array_values( array_filter( $schemas, function( $s ) use ( $schema_type ) {
            return ! ( isset( $s['type'] ) && $s['type'] === $schema_type );
        } ) );

        // 새 스키마 추가 (json은 PHP 배열로 — save_schemas가 객체로 저장)
        $schemas[] = [
            'type' => $schema_type,
            'json' => $decoded,   // PHP array → save_schemas가 처리
        ];

        // 저장 (내부에서 json 필드를 객체로 변환하여 이중인코딩 없이 저장)
        $schemas = $this->save_schemas( $post_id, $schemas );

        // 미리보기용 html (JS에서 표시만 사용)
        $schema_html_preview = '<script type="application/ld+json">' . "\n" . json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n</script>";

        wp_send_json_success( [
            'schema'   => $schema_html_preview,
            'type'     => $schema_type,
            'schemas'  => $schemas,
            'message'  => '스키마가 추가되었습니다. (총 ' . count( $schemas ) . '개)',
        ] );
    }

    /* ── AJAX: 스키마 저장 ── */
    public function ajax_save_schema_markup() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $post_id     = isset( $_POST['post_id'] )     ? absint( $_POST['post_id'] )                  : 0;
        $schema_type = isset( $_POST['schema_type'] ) ? sanitize_text_field( $_POST['schema_type'] ) : 'schema';
        $schema_json = isset( $_POST['schema_json'] ) ? wp_unslash( $_POST['schema_json'] )          : '';
        $edit_idx    = isset( $_POST['edit_idx'] )    && $_POST['edit_idx'] !== '' ? (int) $_POST['edit_idx'] : null;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) )
            wp_send_json_error( [ 'message' => '권한 없음' ] );

        // JSON 유효성 검사
        $decoded = json_decode( $schema_json, true );
        if ( ! $decoded ) wp_send_json_error( [ 'message' => '유효하지 않은 JSON입니다. 문법을 확인하세요.' ] );

        $json_pretty = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        $schemas = $this->decode_schemas( get_post_meta( $post_id, '_ai_blog_schema_markup', true ) );

        if ( $edit_idx !== null && isset( $schemas[ $edit_idx ] ) ) {
            // ── 편집 모드: 특정 인덱스만 수정 ──
            $schemas[ $edit_idx ]['json'] = $decoded;   // PHP array
            $schemas[ $edit_idx ]['type'] = $schema_type;
            unset( $schemas[ $edit_idx ]['html'] );
        } else {
            // ── 신규 추가 모드: 같은 타입 제거 후 추가 ──
            $schemas = array_values( array_filter( $schemas, function( $s ) use ( $schema_type ) {
                return ! ( isset( $s['type'] ) && $s['type'] === $schema_type );
            } ) );
            $schemas[] = [ 'type' => $schema_type, 'json' => $decoded ];  // PHP array
        }

        $schemas = $this->save_schemas( $post_id, $schemas );
        wp_send_json_success( [ 'message' => '스키마가 저장되었습니다.', 'schemas' => array_values( $schemas ) ] );
    }

    /* ── AJAX: 스키마 삭제 ── */
    public function ajax_delete_schema() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $index   = isset( $_POST['index'] )   ? intval( $_POST['index'] )   : -1;
        if ( ! $post_id ) wp_send_json_error( [ 'message' => '포스트 ID 없음' ] );

        if ( $index === -1 ) {
            // 전체 삭제
            delete_post_meta( $post_id, '_ai_blog_schema_markup' );
            wp_send_json_success( [ 'message' => '모든 스키마가 삭제되었습니다.', 'all' => true ] );
        } else {
            $existing_raw = get_post_meta( $post_id, '_ai_blog_schema_markup', true );
            $schemas      = $this->decode_schemas( $existing_raw );
            if ( isset( $schemas[ $index ] ) ) {
                unset( $schemas[ $index ] );
                $schemas = array_values( $schemas );
            }
            if ( empty( $schemas ) ) {
                delete_post_meta( $post_id, '_ai_blog_schema_markup' );
            } else {
                $this->save_schemas( $post_id, $schemas );
            }
            wp_send_json_success( [ 'message' => '스키마가 삭제되었습니다.', 'schemas' => array_values( $schemas ) ] );
        }
    }

    /* ── AJAX: SEO 메타 저장 ── */
    public function ajax_save_all_seo_meta() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( [ 'message' => '권한 없음' ] );
        $fields = [ 'seo_title' => '_ai_seo_title', 'meta_desc' => '_ai_meta_desc', 'slug' => '_ai_slug', 'focus_keyword' => '_ai_focus_keyword' ];
        foreach ( $fields as $k => $meta ) {
            if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $meta, sanitize_text_field( $_POST[ $k ] ) );
        }
        // Rank Math 연동
        if ( isset( $_POST['seo_title'] ) )      update_post_meta( $post_id, 'rank_math_title',         sanitize_text_field( $_POST['seo_title'] ) );
        if ( isset( $_POST['meta_desc'] ) )      update_post_meta( $post_id, 'rank_math_description',   sanitize_text_field( $_POST['meta_desc'] ) );
        if ( isset( $_POST['focus_keyword'] ) )  update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $_POST['focus_keyword'] ) );
        wp_send_json_success( [ 'message' => 'SEO 메타가 저장되었습니다.' ] );
    }

    /* ══════════════════════════════════════════
       AI 썸네일 — Canvas API로 이미지 생성 → 미디어 저장
       각 스타일별 40가지 이상의 고유한 배경 조합
    ══════════════════════════════════════════════════════════ */

    /* ════════════════════════════════════════════════════════
       AJAX: base64 이미지 데이터 → WordPress 미디어 라이브러리 저장
       (Canvas 생성 이미지를 미디어 라이브러리에 저장)
    ════════════════════════════════════════════════════════ */
    public function ajax_generate_thumbnail() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );
        $post_id   = isset( $_POST['post_id'] )    ? absint( $_POST['post_id'] )                          : 0;
        $topic     = isset( $_POST['topic'] )      ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
        $image_b64 = isset( $_POST['image_data'] ) ? wp_unslash( $_POST['image_data'] )                   : '';

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) )
            wp_send_json_error( [ 'message' => '권한 없음' ] );
        if ( empty( $image_b64 ) )
            wp_send_json_error( [ 'message' => '이미지 데이터가 없습니다.' ] );

        // ── base64 data URI 파싱 ──
        if ( ! preg_match( '/^data:(image\/[a-z]+);base64,(.+)$/i', $image_b64, $m ) )
            wp_send_json_error( [ 'message' => '잘못된 이미지 형식입니다.' ] );

        $mime_type = $m[1];
        $img_data  = base64_decode( $m[2] );

        if ( ! $img_data )
            wp_send_json_error( [ 'message' => '이미지 디코딩 실패' ] );

        // ── WordPress 미디어 라이브러리에 등록 ──
        $ext        = ( strpos( $mime_type, 'jpeg' ) !== false ) ? 'jpg' : 'png';
        $upload_dir = wp_upload_dir();
        $filename   = 'aibp-thumb-' . $post_id . '-' . time() . '.' . $ext;
        $filepath   = $upload_dir['path'] . '/' . $filename;
        $fileurl    = $upload_dir['url']  . '/' . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $filepath, $img_data );

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => $mime_type,
            'post_title'     => sanitize_file_name( $topic . ' 썸네일' ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $filepath, $post_id );

        if ( is_wp_error( $attachment_id ) )
            wp_send_json_error( [ 'message' => '미디어 등록 실패: ' . $attachment_id->get_error_message() ] );

        $attach_data = wp_generate_attachment_metadata( $attachment_id, $filepath );
        wp_update_attachment_metadata( $attachment_id, $attach_data );
        set_post_thumbnail( $post_id, $attachment_id );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => $fileurl,
            'message'       => '썸네일이 생성되어 대표 이미지로 설정되었습니다.',
        ] );
    }

    /* ════════════════════════════════════════════════════════
       AJAX: AI Horde 이미지 생성 (메인) — Canvas는 폴백용
       이미지 생성: Pollinations FLUX.1[Schnell]
       실패 시 retry:true 반환 → JS가 Canvas 폴백 실행
    ════════════════════════════════════════════════════════ */
    /* ════════════════════════════════════════════════════════
       AJAX: Step 1 — 제미나이 2단계
         Phase A: 주제의 실제 의미를 한국 맥락에서 조사
         Phase B: 조사 결과 기반으로 정확한 AI Horde 프롬프트 생성
       반환: { prompt, neg_prompt, style_label, topic_research }
    ════════════════════════════════════════════════════════ */
    public function ajax_generate_image_prompt() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );

        $topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
        $style = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] )               : 'poster';

        if ( empty( $topic ) )
            wp_send_json_error( [ 'message' => '주제가 없습니다.' ] );

        /* ══════════════════════════════════════════════════════
           스타일별 완전 차별화 디렉티브 (Imagen 동급 품질 기준)
           각 스타일은 완전히 다른 시각 언어·구도·렌더링 방식
        ══════════════════════════════════════════════════════ */
        /*
         * 스타일 디렉티브 설계 원칙:
         *  - 모든 스타일(photo_realistic 제외)은 AI Horde가 "배경 템플릿"을 생성
         *  - Canvas가 그 위에 주제 텍스트를 중앙에 삽입
         *  - 따라서 인물/얼굴/사람은 모든 스타일에서 절대 금지
         *  - 중앙에 텍스트가 들어갈 공간이 확보되어야 함
         *  - photo_realistic만 텍스트 오버레이 없이 그대로 사용
         */
        $no_people = 'no people, no person, no human, no face, no portrait, no character, no figure, no body parts, no hands, no eyes, no silhouette of person';

        $style_directives = [
            /* ── 포스터: 깔끔한 단순 배경, 조화로운 색상, 중앙 텍스트 공간 ── */
            'poster' => [
                'label'      => '포스터',
                'sdxl_style' => 'clean movie poster background',
                'core'       => 'clean professional POSTER background, flat solid color blocks or very simple smooth gradient, bold harmonious color palette, large EMPTY CENTER SPACE for title text, minimal composition, no complex shapes, no diagonal lines, no intersecting abstract lines, no busy patterns, simple clean layout',
                'subject'    => 'single simple geometric accent element at edge only — keep center completely clear for text',
                'color'      => 'harmonious two-tone palette: deep navy + warm cream, or rich burgundy + soft gold, or forest green + white — colors must complement each other',
                'quality'    => '(masterpiece:1.4), (best quality:1.3), sharp clean edges, professional poster quality',
                'avoid'      => "complex lines, diagonal intersecting lines, chaotic abstract shapes, busy patterns, noisy background, watercolor, {$no_people}",
            ],
            /* ── 미니멀: 단색 배경, 텍스트는 반드시 흰색 ── */
            'minimal' => [
                'label'      => '미니멀',
                'sdxl_style' => 'minimalist solid color background',
                'core'       => 'pure SOLID SINGLE COLOR background, no gradient, no pattern, no texture, completely flat and clean, Bauhaus zen simplicity, large empty center space',
                'subject'    => 'nothing at center — absolutely flat solid background only',
                'color'      => 'single rich solid background color: deep navy blue, or charcoal dark gray, or deep forest green, or rich burgundy — one color only, flat',
                'quality'    => '(masterpiece:1.4), (best quality:1.3), perfectly flat solid color, no noise, no grain',
                'avoid'      => "gradient, texture, pattern, multiple colors, bright white background, complex shapes, neon, {$no_people}",
            ],
            /* ── 사실적 사진: 제미나이 프롬프트 기반 실사 이미지 ── */
            'photo_realistic' => [
                'label'      => '사실적 사진',
                'sdxl_style' => 'hyperrealistic photography',
                'core'       => 'ultra-photorealistic DSLR photography, shot on Sony A7R5, 50mm f/1.4 lens, shallow depth of field, cinematic natural lighting, National Geographic editorial quality',
                'subject'    => 'the exact real-world object, place, or scene the topic refers to — photographed authentically',
                'color'      => 'true-to-life color grading, warm filmic tone, rich shadows, luminous highlights',
                'quality'    => '(RAW photo:1.4), (photorealistic:1.4), (hyperrealistic:1.3), 8k uhd, DSLR, award-winning',
                'avoid'      => 'illustration, cartoon, anime, painting, CGI, artificial look, plastic, oversaturated',
            ],
            /* ── 타이포그래피: 순수 검은색 배경, 텍스트 디자인 극대화 ── */
            'typography' => [
                'label'      => '타이포그래피',
                'sdxl_style' => 'pure black background template',
                'core'       => 'PURE SOLID BLACK background, completely dark #000000, no gradients, no light, no bright areas, absolute darkness, clean matte black surface, empty center reserved for bold typography overlay',
                'subject'    => 'nothing — pure black void only, center must be completely dark and clear',
                'color'      => 'pure black only, #000000, absolutely no other colors, no gradients, solid darkness',
                'quality'    => '(masterpiece:1.4), (best quality:1.3), pure black, solid dark background, no light leaks',
                'avoid'      => "bright areas, gradients, colors, patterns, textures, light sources, {$no_people}",
            ],
            /* ── 브랜딩: 프리미엄 배경 템플릿 ── */
            'branding' => [
                'label'      => '브랜딩',
                'sdxl_style' => 'premium brand background visual',
                'core'       => 'high-end advertising campaign BACKGROUND, luxury brand aesthetic, clean sophisticated layout with large CENTER space, Apple or Nike-level brand design, aspirational atmosphere',
                'subject'    => 'abstract brand-style background elements — premium textures, subtle patterns placed at edges only, center reserved for title',
                'color'      => 'premium palette: deep charcoal + pure white + metallic gold, or all-white with bold color edge accent',
                'quality'    => '(masterpiece:1.4), (best quality:1.3), (commercial quality:1.2), pristine, premium brand feel',
                'avoid'      => "amateur, stock-photo feel, cluttered center, neon overload, {$no_people}",
            ],
        ];

        $dir = isset( $style_directives[ $style ] ) ? $style_directives[ $style ] : $style_directives['poster'];

        /* ════════════════════════════════════════════════
           Phase A: 주제 심층 조사 — 한국 맥락 + 시각 정밀 분석
        ════════════════════════════════════════════════ */
        $research_prompt = <<<RESEARCH
당신은 한국 미디어·문화·서비스·브랜드에 정통한 비주얼 콘텐츠 전문가입니다.
아래 키워드를 심층 조사하여 최고 품질 이미지 생성에 필요한 정보를 추출하세요.

[키워드]: {$topic}

【오역 방지 — 한국 특수 맥락 필수 확인】
- "알약" → 한국 보안SW (약이 아님)
- "토스" → 핀테크앱 (동사 아님)
- "카카오" → IT대기업 (열매 아님)
- "네이버" → 검색엔진 (이웃 아님)
- "배민" → 배달앱
- "당근" → 중고거래앱 (채소 아님)
- "쿠팡" → 이커머스
- "지코" → K-pop 아티스트 (zico)
위 패턴처럼 한국 고유 맥락이 있으면 반드시 적용하세요.

【분석 항목】
1. 이 키워드가 한국 독자에게 실제로 의미하는 것
2. 최고 품질 이미지로 표현할 때 사용해야 할 구체적 시각 요소
3. 가장 임팩트 있는 단일 핵심 장면/오브젝트
4. 색상 분위기 (따뜻한/차가운/중성, 대표 색상)
5. 잘못 그릴 경우 발생할 오류

【JSON 출력 (코드블록 없이)】
{
  "actual_meaning": "실제 의미 (1문장, 정확하게)",
  "visual_context": "이미지화 대상 (구체적 장면/오브젝트, 영문 묘사 포함)",
  "hero_shot": "가장 임팩트 있는 단 하나의 시각 장면 (영어로)",
  "color_mood": "색상 분위기 (영어로, 예: warm golden tones, cool tech blues)",
  "key_visuals": ["영어 시각요소1", "영어 시각요소2", "영어 시각요소3", "영어 시각요소4"],
  "category": "앱/서비스|음식|IT기술|금융|건강|교육|라이프스타일|엔터테인먼트|인물|제품|기타",
  "wrong_interpretation": "잘못 해석 시 오류 (간결하게, 없으면 빈 문자열)"
}
RESEARCH;

        $research_body = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $research_prompt ] ] ] ],
            'generationConfig' => [ 'temperature' => 0.2, 'maxOutputTokens' => 700, 'topP' => 0.75 ],
            'tools'            => [ [ 'google_search' => (object)[] ] ],
        ];

        $research_data   = $this->call_gemini_api( $research_body, 35, 'gemini-2.5-flash-lite' );
        $research_result = [];

        if ( ! is_wp_error( $research_data ) ) {
            $research_raw = $this->extract_text( $research_data );
            if ( ! is_wp_error( $research_raw ) ) {
                $research_raw = preg_replace( '/```json\s*/i', '', $research_raw );
                $research_raw = preg_replace( '/```\s*/m', '', $research_raw );
                $parsed_res   = json_decode( trim( $research_raw ), true );
                if ( ! $parsed_res && preg_match( '/\{[\s\S]*\}/m', $research_raw, $mm ) )
                    $parsed_res = json_decode( $mm[0], true );
                if ( $parsed_res ) $research_result = $parsed_res;
            }
        }

        /* ════════════════════════════════════════════════
           Phase B: Imagen 동급 SDXL 프롬프트 생성
           — 주제 정확도 + 스타일 완성도 + 글자 제거 극대화
        ════════════════════════════════════════════════ */
        $actual_meaning  = ! empty( $research_result['actual_meaning'] )  ? $research_result['actual_meaning']  : $topic;
        $visual_context  = ! empty( $research_result['visual_context'] )  ? $research_result['visual_context']  : $topic;
        $hero_shot       = ! empty( $research_result['hero_shot'] )       ? $research_result['hero_shot']       : '';
        $color_mood      = ! empty( $research_result['color_mood'] )      ? $research_result['color_mood']      : '';
        $key_visuals_arr = ! empty( $research_result['key_visuals'] )     ? (array) $research_result['key_visuals'] : [];
        $key_visuals_str = implode( ', ', $key_visuals_arr );
        $wrong_interp    = ! empty( $research_result['wrong_interpretation'] ) ? $research_result['wrong_interpretation'] : '';

        $prompt_instruction = <<<PROMPT
당신은 Stable Diffusion XL + SDXL LoRA 전문 프롬프트 엔지니어로, Imagen · DALL-E 3 동급의 결과를 SDXL에서 구현합니다.
아래 조사 데이터를 바탕으로 [{$dir['label']}] 스타일의 완벽한 블로그 썸네일 프롬프트를 작성하세요.

━━━ 주제 조사 결과 ━━━
• 원본 키워드: {$topic}
• 실제 의미: {$actual_meaning}
• 시각화 대상: {$visual_context}
• 히어로 장면: {$hero_shot}
• 색상 분위기: {$color_mood}
• 핵심 시각 요소: {$key_visuals_str}
• ⛔ 오역 방지: {$wrong_interp}

━━━ 스타일 스펙 [{$dir['label']}] ━━━
• 핵심 디렉션: {$dir['core']}
• 주제 표현법: {$dir['subject']}
• 색상 팔레트: {$dir['color']}
• SDXL 스타일 태그: {$dir['sdxl_style']}

━━━ SDXL 프롬프트 작성 규칙 (Imagen 동급 기준) ━━━
① 주제 정확도: "{$actual_meaning}" 를 오해 없이 표현하는 시각 요소를 최우선 배치
② 구도 명시: 반드시 16:9 widescreen landscape orientation 명시
③ 품질 태그: (masterpiece:1.4), (best quality:1.3) 등 SDXL 가중치 문법 사용
④ 스타일 충실도: [{$dir['label']}] 스타일이 다른 스타일과 시각적으로 완전히 구별되어야 함
⑤ 글자·문자 완전 금지: 이미지 내 어떤 언어의 글자·기호·레터링도 절대 불가
  → 프롬프트에 "no text", "no letters", "no writing", "text-free" 반드시 포함
  → 네거티브 프롬프트에도 모든 문자 관련 토큰 반드시 포함
⑥ 인물·얼굴 완전 금지 (photo_realistic 제외): 사람, 얼굴, 캐릭터, 인물 실루엣 절대 불가
  → "no people, no person, no face, no human, no character" 반드시 포함
⑦ 텍스트 삽입 공간 확보: 이미지 중앙에 Canvas 텍스트 오버레이가 들어갈 여백 필수
  → 중앙 영역은 단순하고 대비가 명확해야 함 ("clear center area for text overlay" 포함)

━━━ 출력 형식 (순수 JSON만, 마크다운 없이) ━━━
{
  "prompt": "완성된 SDXL 영어 프롬프트 — (masterpiece:1.4) 시작, 주제 시각 요소, 스타일 디렉션, 조명/구도/색상 모두 포함, no text no letters no writing text-free 포함, 100-180단어",
  "neg_prompt": "text, letters, words, writing, font, typography, alphabet, numbers, digits, glyphs, symbols, captions, subtitles, labels, watermark, signature, logo, brand name, written words, random characters, gibberish, illegible text, Korean text, Chinese text, Japanese text, Arabic text, Latin text, Cyrillic text, (bad quality:1.4), (worst quality:1.4), blurry, out of focus, noise, grain, distorted, deformed, ugly, disfigured, bad anatomy, bad proportions, duplicate, extra limbs, mutated, malformed, low resolution, pixelated, jpeg artifacts, oversaturated"
}
PROMPT;

        $prompt_body = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt_instruction ] ] ] ],
            'generationConfig' => [ 'temperature' => 0.50, 'maxOutputTokens' => 900, 'topP' => 0.85 ],
        ];

        $prompt_data = $this->call_gemini_api( $prompt_body, 45, 'gemini-2.5-flash-lite' );

        if ( is_wp_error( $prompt_data ) ) {
            $fallback = $this->build_fallback_horde_prompt( $topic, $style, $dir, $research_result );
            wp_send_json_success( array_merge( $fallback, [
                'style_label'    => $dir['label'],
                'source'         => 'fallback',
                'topic_research' => $research_result,
            ] ) );
        }

        $raw = $this->extract_text( $prompt_data );
        if ( is_wp_error( $raw ) ) {
            $fallback = $this->build_fallback_horde_prompt( $topic, $style, $dir, $research_result );
            wp_send_json_success( array_merge( $fallback, [
                'style_label'    => $dir['label'],
                'source'         => 'fallback',
                'topic_research' => $research_result,
            ] ) );
        }

        $raw    = preg_replace( '/```json\s*/i', '', $raw );
        $raw    = preg_replace( '/```\s*/m', '', $raw );
        $parsed = json_decode( trim( $raw ), true );

        if ( ! $parsed || empty( $parsed['prompt'] ) ) {
            if ( preg_match( '/\{[\s\S]*\}/m', $raw, $mm ) )
                $parsed = json_decode( $mm[0], true );
        }

        if ( ! $parsed || empty( $parsed['prompt'] ) ) {
            $fallback = $this->build_fallback_horde_prompt( $topic, $style, $dir, $research_result );
            wp_send_json_success( array_merge( $fallback, [
                'style_label'    => $dir['label'],
                'source'         => 'fallback',
                'topic_research' => $research_result,
            ] ) );
        }

        // 품질 태그 이미 포함돼 있지 않으면 보강
        $base_prompt = trim( $parsed['prompt'] );
        if ( strpos( $base_prompt, 'masterpiece' ) === false )
            $base_prompt = '(masterpiece:1.4), (best quality:1.3), ' . $base_prompt;

        // 문자 제거 절대 토큰 보강 (항상 끝에 추가)
        $no_people_tokens = ( $style !== 'photo_realistic' ) ? 'no people, no person, no face, no human, no character, no figure, no silhouette of person, ' : '';
        $no_text_tokens = $no_people_tokens . 'no text, no letters, no writing, no words, no glyphs, no symbols, no captions, no watermark, text-free, clear center area for text overlay, clean visual background template, 16:9 widescreen landscape';
        $final_prompt   = $base_prompt . ', ' . $no_text_tokens;

        // 네거티브 프롬프트 보강
        $parsed_neg = trim( $parsed['neg_prompt'] ?? '' );
        // photo_realistic은 인물 허용, 나머지는 인물 완전 금지
        $people_neg = ( $style !== 'photo_realistic' )
            ? '(person:1.5), (people:1.5), (human:1.5), (face:1.5), (portrait:1.5), (man:1.4), (woman:1.4), (girl:1.4), (boy:1.4), (character:1.4), (figure:1.4), (body:1.3), (hands:1.3), (eyes:1.3), (anime character:1.5), (human silhouette:1.4), '
            : '';
        $universal_neg = $people_neg . '(text:1.5), (letters:1.5), (words:1.5), (writing:1.5), (font:1.4), (typography:1.4), (alphabet:1.4), (numbers:1.3), (digits:1.3), (glyphs:1.4), (symbols:1.3), (captions:1.4), (subtitles:1.4), (labels:1.3), (watermark:1.4), (signature:1.3), (logo:1.3), (brand:1.2), (written words:1.5), (random characters:1.5), (gibberish:1.5), (illegible text:1.5), (korean text:1.5), (chinese text:1.5), (japanese text:1.5), (arabic text:1.4), (latin text:1.4), (cyrillic:1.4), (bad quality:1.4), (worst quality:1.5), (low quality:1.4), blurry, out of focus, noise, distorted, deformed, ugly, bad anatomy, duplicate, extra limbs, mutated, low resolution, pixelated, jpeg artifacts, ' . $dir['avoid'];
        $final_neg = empty( $parsed_neg )
            ? $universal_neg
            : $parsed_neg . ', ' . $universal_neg;

        wp_send_json_success( [
            'prompt'         => $final_prompt,
            'neg_prompt'     => $final_neg,
            'style_label'    => $dir['label'],
            'source'         => 'gemini',
            'topic_research' => $research_result,
        ] );
    }

    /* ── 고품질 폴백 프롬프트 빌더 (Imagen 기준 구조 유지) ── */
    private function build_fallback_horde_prompt( $topic, $style, $dir, $research = [] ) {
        $visual    = ! empty( $research['visual_context'] ) ? $research['visual_context'] : $this->topic_to_visual_concept( $topic );
        $hero      = ! empty( $research['hero_shot'] )      ? $research['hero_shot']      : '';
        $color     = ! empty( $research['color_mood'] )     ? $research['color_mood']     : '';
        $kv_arr    = ! empty( $research['key_visuals'] )    ? (array) $research['key_visuals'] : [];
        $kv        = implode( ', ', array_slice( $kv_arr, 0, 3 ) );

        $subject   = $hero ?: $visual;
        $kv_str    = $kv ? ', ' . $kv : '';
        $color_str = $color ? ', ' . $color : '';

        $prompt = "(masterpiece:1.4), (best quality:1.3), {$dir['sdxl_style']}, "
                . "{$subject}{$kv_str}{$color_str}, "
                . "{$dir['core']}, "
                . "{$dir['color']}, "
                . "no text, no letters, no writing, no words, no glyphs, no captions, no watermark, text-free, 16:9 widescreen landscape";

        $neg = "(text:1.5), (letters:1.5), (words:1.5), (writing:1.5), (font:1.4), (typography:1.4), (alphabet:1.4), (glyphs:1.4), (captions:1.4), (watermark:1.4), (signature:1.3), (logo:1.3), (korean text:1.5), (chinese text:1.5), (japanese text:1.5), (random characters:1.5), (gibberish:1.5), (bad quality:1.4), (worst quality:1.5), blurry, distorted, deformed, ugly, bad anatomy, low resolution, "
             . $dir['avoid'];

        return [ 'prompt' => $prompt, 'neg_prompt' => $neg ];
    }

    /* ════════════════════════════════════════════════════════
       HORDE ENDPOINT 1: 작업 제출 → job_id 즉시 반환 (< 5초)
       JS가 이 job_id로 이후 폴링을 직접 제어
    ════════════════════════════════════════════════════════ */
    /* ════════════════════════════════════════════════════════
       AI Horde 이미지 생성 (분산형 Stable Diffusion)
       - 엔드포인트: https://stablehorde.net/api/v2/
       - SDXL/SD 1.5 모델 자동 선택
       - API 키 없으면 익명(0000000000) 사용
       - 최대 300초 폴링 / 5초 간격 체크
    ════════════════════════════════════════════════════════ */
    public function ajax_pollinations_generate() {
        check_ajax_referer( 'ai_blog_writer_nonce', 'nonce' );

        $prompt     = isset( $_POST['prompt'] )     ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) )     : '';
        $neg_prompt = isset( $_POST['neg_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['neg_prompt'] ) ) : '';

        if ( empty( $prompt ) )
            wp_send_json_error( [ 'message' => '프롬프트가 없습니다.' ] );

        $worker_url = trim( get_option( 'aibp_cf_worker_url', '' ) );
        if ( empty( $worker_url ) )
            wp_send_json_error( [ 'message' => 'Cloudflare Worker URL이 설정되지 않았습니다. 설정 페이지에서 Worker URL을 입력해주세요.' ] );

        if ( empty( $neg_prompt ) ) {
            $neg_prompt = '(text:1.5), (letters:1.5), (words:1.5), (writing:1.5), (font:1.4), (watermark:1.4), (bad quality:1.4), (worst quality:1.5), blurry, distorted, deformed, ugly, low resolution';
        }

        @set_time_limit( 120 );

        $response = wp_remote_post( $worker_url, [
            'timeout' => 115,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'prompt'          => trim( $prompt ),
                'negative_prompt' => trim( $neg_prompt ),
            ] ),
        ] );

        if ( is_wp_error( $response ) )
            wp_send_json_error( [ 'message' => 'Worker 요청 실패: ' . $response->get_error_message() ] );

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 )
            wp_send_json_error( [ 'message' => 'Worker 응답 오류 (HTTP ' . $code . ')' ] );

        // JSON 응답 시도
        $json = json_decode( $body, true );
        if ( $json && ! empty( $json['image'] ) ) {
            $img_b64  = $json['image'];
            $mime     = isset( $json['mime'] ) ? $json['mime'] : 'image/png';
            $img_body = base64_decode( $img_b64 );
            $data_url = 'data:' . $mime . ';base64,' . $img_b64;
        } else {
            // 바이너리 응답 처리
            $img_body = $body;
            $mime     = 'image/png';
            $ct       = wp_remote_retrieve_header( $response, 'content-type' );
            if ( strpos( $ct, 'jpeg' ) !== false ) $mime = 'image/jpeg';
            elseif ( strpos( $ct, 'webp' ) !== false ) $mime = 'image/webp';
            $img_b64  = base64_encode( $img_body );
            $data_url = 'data:' . $mime . ';base64,' . $img_b64;
        }

        if ( strlen( $img_body ) < 1000 )
            wp_send_json_error( [ 'message' => '이미지 데이터가 너무 작습니다. Worker 설정을 확인해주세요.' ] );

        wp_send_json_success( [
            'data_url' => $data_url,
            'mime'     => $mime,
            'model'    => 'Cloudflare Workers AI (SDXL 1.0)',
            'size_kb'  => round( strlen( $img_body ) / 1024 ),
        ] );
    }

        /* ── 한국어 주제 → 고품질 영어 시각 개념 (AI Horde 폴백용) ── */
    private function topic_to_visual_concept( $topic ) {
        $map = [
            // 건강·피트니스
            '다이어트|체중|살빼|감량|체지방'           => 'athletic woman running on scenic mountain trail at sunrise, dynamic motion blur, sports photography, fit healthy body, vibrant energy',
            '운동|헬스|피트니스|근력|트레이닝'         => 'modern gym interior with athlete lifting weights, dramatic low-angle shot, muscular silhouette against bright window, powerful composition',
            // 금융·재테크
            '재테크|투자|주식|펀드|자산|포트폴리오'    => 'dramatic financial trading floor with glowing stock charts, gold bull statue, rising green graph arrows, wealth concept, dramatic lighting',
            '부동산|아파트|집|주택|청약'               => 'stunning luxury apartment building exterior at dusk, warm interior lights glowing, modern architecture, real estate premium',
            '돈|금융|경제|은행|대출'                   => 'professional financial concept, neat stacks of gold coins and paper bills, clean marble surface, wealth and prosperity visual',
            // 건강·의료
            '건강|wellness|의학|병원|치료'             => 'bright modern hospital or wellness center, cheerful medical professional in white coat, clean hygienic atmosphere, hope and health',
            '약|영양제|비타민|supplement'              => 'colorful vitamin supplements and capsules arranged artfully on white surface, health and vitality concept, macro photography',
            // 음식·요리
            '요리|레시피|cooking|음식|맛집'            => 'exquisite gourmet food photography, beautifully plated dish with garnish, soft bokeh background, professional culinary art, appetite appeal',
            '카페|커피|coffee|베이커리'                => 'cozy artisan coffee shop, steaming latte art in ceramic cup, warm ambient lighting, inviting atmosphere, coffee culture aesthetic',
            // IT·기술
            'IT|기술|소프트웨어|프로그래밍|코딩|개발'  => 'futuristic code on holographic screen, developer hands on glowing keyboard, dark room with blue neon code streams, tech aesthetic',
            'AI|인공지능|머신러닝|딥러닝'              => 'abstract neural network visualization, glowing blue synaptic connections in deep space, artificial intelligence concept art, stunning sci-fi',
            '앱|모바일|스마트폰|어플'                  => 'modern smartphone with glowing app interface, floating holographic UI elements, clean tech product photography',
            // 교육·학습
            '교육|학습|공부|study|강의|수업'           => 'bright modern classroom or study space, focused student with open books and laptop, golden hour light, academic achievement atmosphere',
            '시험|자격증|합격|취업준비'                => 'triumphant graduate holding diploma, confetti celebration, achievement and success concept, warm joyful lighting',
            // 여행
            '여행|관광|trip|해외|여행지'               => 'breathtaking travel destination panorama, dramatic landscape with vibrant sky, adventure and exploration photography, wanderlust',
            // 라이프스타일·뷰티
            '뷰티|화장|스킨케어|beauty|화장품'         => 'luxury beauty product flatlay, premium skincare bottles on marble, fresh flowers, elegant editorial photography, glowing radiant skin',
            '패션|옷|스타일|fashion|트렌드'            => 'high-fashion editorial photography, stylish model in bold outfit, clean studio background, Vogue-quality composition',
            '반려동물|강아지|고양이|pet'               => 'adorable golden retriever in sunlit park, joyful expression, soft bokeh, warm natural light, heartwarming companion photography',
            // 비즈니스
            '창업|사업|스타트업|마케팅|비즈니스'       => 'dynamic startup office, diverse team collaborating around modern workspace, energy and innovation atmosphere, entrepreneurship',
            '취업|직장|커리어|채용|면접'               => 'confident professional in business attire, modern office skyline view, success and career achievement concept, aspirational',
            // 환경·사회
            '환경|자연|eco|green|기후|생태'            => 'stunning pristine natural landscape, lush green forest with morning mist, environmental conservation beauty, Earth appreciation',
            '정부|지원금|정책|복지|공공'               => 'clean government building exterior with flag, professional civic architecture, public service and community concept',
            // 엔터테인먼트
            '게임|gaming|e스포츠'                     => 'dramatic esports arena with glowing RGB gaming setup, intense player at computer, neon lighting, competitive gaming atmosphere',
            '영화|드라마|콘텐츠|스트리밍'             => 'cinematic film reel or movie clapperboard, dramatic lighting, Hollywood glamour concept, entertainment industry',
            '음악|music|노래|가수|아이돌'             => 'dynamic music performance, musician on stage with dramatic backlighting, concert atmosphere, passion for music',
            // 법·금융 서비스
            '법률|법|규정|계약|보험'                   => 'professional law office, scales of justice on mahogany desk, leather-bound books, authority and trustworthiness concept',
            '세금|회계|절세|신고'                      => 'clean professional financial documents and calculator, organized tax concept, precision and accuracy visual',
        ];
        foreach ( $map as $pattern => $concept ) {
            if ( preg_match( '/' . $pattern . '/iu', $topic ) ) return $concept;
        }
        $clean = preg_replace( '/[^\w\s]/u', ' ', $topic );
        $clean = mb_substr( trim( $clean ), 0, 60, 'UTF-8' );
        return 'high quality editorial concept art representing "' . $clean . '", professional studio composition, vivid colors, clear subject focus, award-winning photography or illustration quality';
    }

            /* ── AJAX: 템플릿 저장 ── */
    public function ajax_save_template() {
        check_ajax_referer( 'aibp_template_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '권한 없음' ] );

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $name          = isset( $_POST['name'] )          ? sanitize_text_field( $_POST['name'] ) : '';
        $title_x = isset( $_POST['title_x'] ) ? (float) $_POST['title_x'] : 0.05;
        $title_y = isset( $_POST['title_y'] ) ? (float) $_POST['title_y'] : 0.55;
        $title_w = isset( $_POST['title_w'] ) ? (float) $_POST['title_w'] : 0.90;
        $title_h = isset( $_POST['title_h'] ) ? (float) $_POST['title_h'] : 0.25;
        $sub_x   = isset( $_POST['sub_x'] )   ? (float) $_POST['sub_x']   : 0.05;
        $sub_y   = isset( $_POST['sub_y'] )   ? (float) $_POST['sub_y']   : 0.80;
        $sub_w   = isset( $_POST['sub_w'] )   ? (float) $_POST['sub_w']   : 0.90;
        $sub_h   = isset( $_POST['sub_h'] )   ? (float) $_POST['sub_h']   : 0.12;

        if ( ! $attachment_id || empty( $name ) )
            wp_send_json_error( [ 'message' => '이미지와 스타일명을 입력하세요.' ] );

        $preview_url = wp_get_attachment_url( $attachment_id );
        $templates   = get_option( 'aibp_thumb_templates', [] );

        $edit_idx = isset( $_POST['edit_idx'] ) && $_POST['edit_idx'] !== '' ? (int) $_POST['edit_idx'] : null;

        $entry = compact( 'attachment_id', 'name', 'preview_url',
                          'title_x', 'title_y', 'title_w', 'title_h',
                          'sub_x', 'sub_y', 'sub_w', 'sub_h' );

        if ( $edit_idx !== null && isset( $templates[ $edit_idx ] ) ) {
            $templates[ $edit_idx ] = $entry;
            $msg = '템플릿이 수정되었습니다.';
        } else {
            $templates[] = $entry;
            $msg = '템플릿이 저장되었습니다.';
        }
        update_option( 'aibp_thumb_templates', $templates );
        wp_send_json_success( [ 'message' => $msg, 'templates' => $templates ] );
    }

    /* ── AJAX: 템플릿 삭제 ── */
    public function ajax_delete_template() {
        check_ajax_referer( 'aibp_template_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '권한 없음' ] );
        $idx       = isset( $_POST['idx'] ) ? (int) $_POST['idx'] : -1;
        $templates = get_option( 'aibp_thumb_templates', [] );
        if ( ! isset( $templates[ $idx ] ) ) wp_send_json_error( [ 'message' => '존재하지 않는 템플릿입니다.' ] );
        array_splice( $templates, $idx, 1 );
        update_option( 'aibp_thumb_templates', $templates );
        wp_send_json_success( [ 'message' => '삭제되었습니다.', 'templates' => $templates ] );
    }

    /* ── 템플릿 관리 설정 페이지 ── */
    public function render_template_manager() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // 미디어 업로더 스크립트 강제 로드 (hook 이름 불일치 방지용 이중 호출)
        wp_enqueue_media();

        $templates = get_option( 'aibp_thumb_templates', [] );
        $nonce     = wp_create_nonce( 'aibp_template_nonce' );
        ?>
        <div class="wrap">
            <h1>🖼️ AI 썸네일 템플릿 관리</h1>
            <p style="color:#555;">템플릿 이미지를 업로드하고, 제목/부제목이 표시될 위치를 드래그로 지정한 뒤 저장하세요.</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;max-width:1100px;margin-top:20px;">

                <!-- 왼쪽: 등록 폼 -->
                <div style="background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:8px;">
                    <h2 style="margin-top:0;font-size:16px;">새 템플릿 추가 / 수정</h2>
                    <input type="hidden" id="aibp-edit-idx" value="">

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">1. 스타일명</label>
                        <input type="text" id="aibp-tpl-name" placeholder="예: 파란 배경 스타일"
                               style="width:100%;padding:8px 12px;border:1px solid #ccd0d4;border-radius:4px;box-sizing:border-box;">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">2. 템플릿 이미지 업로드</label>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <button type="button" id="aibp-upload-btn" class="button button-secondary">📁 미디어 라이브러리에서 선택</button>
                            <label class="button button-secondary" style="cursor:pointer;margin:0;">
                                ⬆️ 직접 업로드
                                <input type="file" id="aibp-file-input" accept="image/*"
                                       style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            </label>
                            <span id="aibp-upload-name" style="font-size:12px;color:#666;width:100%;margin-top:4px;">선택된 파일 없음</span>
                        </div>
                        <input type="hidden" id="aibp-attachment-id" value="">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">3. 텍스트 위치 지정 (드래그)</label>
                        <p style="font-size:12px;color:#666;margin:0 0 8px;">
                            이미지 위에서 <span style="color:#1a73e8;font-weight:700;">파란 박스(제목)</span>와
                            <span style="color:#e53935;font-weight:700;">빨간 박스(부제목)</span>를 드래그하여 위치를 조정하세요.
                        </p>
                        <div id="aibp-canvas-wrap" style="position:relative;border:2px dashed #ccd0d4;border-radius:4px;background:#f5f5f5;min-height:120px;overflow:hidden;user-select:none;">
                            <img id="aibp-preview-img" src="" alt="" style="width:100%;display:none;pointer-events:none;">
                            <div id="aibp-title-rect" class="aibp-rect" data-type="title"
                                 style="display:none;position:absolute;background:rgba(26,115,232,.25);border:2px solid #1a73e8;cursor:move;box-sizing:border-box;border-radius:4px;min-width:40px;min-height:24px;">
                                <div class="aibp-rect-label" style="font-size:10px;font-weight:700;color:#1a73e8;padding:2px 4px;white-space:nowrap;">제목</div>
                                <div class="aibp-resize-handle" style="position:absolute;right:0;bottom:0;width:12px;height:12px;background:#1a73e8;cursor:se-resize;border-radius:2px 0 2px 0;"></div>
                            </div>
                            <div id="aibp-sub-rect" class="aibp-rect" data-type="sub"
                                 style="display:none;position:absolute;background:rgba(229,57,53,.2);border:2px solid #e53935;cursor:move;box-sizing:border-box;border-radius:4px;min-width:40px;min-height:18px;">
                                <div class="aibp-rect-label" style="font-size:10px;font-weight:700;color:#e53935;padding:2px 4px;white-space:nowrap;">부제목</div>
                                <div class="aibp-resize-handle" style="position:absolute;right:0;bottom:0;width:12px;height:12px;background:#e53935;cursor:se-resize;border-radius:2px 0 2px 0;"></div>
                            </div>
                        </div>
                        <!-- 히든 필드 (비율 저장) -->
                        <input type="hidden" id="f-title-x" value="0.05"><input type="hidden" id="f-title-y" value="0.55">
                        <input type="hidden" id="f-title-w" value="0.90"><input type="hidden" id="f-title-h" value="0.25">
                        <input type="hidden" id="f-sub-x"   value="0.05"><input type="hidden" id="f-sub-y"   value="0.80">
                        <input type="hidden" id="f-sub-w"   value="0.90"><input type="hidden" id="f-sub-h"   value="0.12">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;">4. 폰트 파일 경로 <small style="color:#999;">(선택 — TTF 절대경로)</small></label>
                        <input type="text" id="aibp-font-path"
                               value="<?php echo esc_attr( get_option( 'aibp_thumb_font_path', '' ) ); ?>"
                               placeholder="/path/to/font.ttf"
                               style="width:100%;padding:8px 12px;border:1px solid #ccd0d4;border-radius:4px;box-sizing:border-box;">
                        <p style="font-size:11px;color:#888;margin:4px 0 0;">서버에 나눔고딕 등 한국어 TTF 폰트가 있으면 입력하세요. 없으면 GD 기본 폰트를 사용합니다.</p>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="button" id="aibp-save-tpl-btn" class="button button-primary">💾 템플릿 저장</button>
                        <button type="button" id="aibp-reset-form-btn" class="button">↺ 초기화</button>
                    </div>
                    <p id="aibp-form-msg" style="margin:10px 0 0;font-size:13px;min-height:20px;"></p>
                </div>

                <!-- 오른쪽: 저장된 템플릿 목록 -->
                <div>
                    <h2 style="font-size:16px;margin-top:0;">저장된 템플릿 (<?php echo count($templates); ?>개)</h2>
                    <div id="aibp-template-list">
                    <?php if ( empty($templates) ) : ?>
                        <p style="color:#999;font-size:13px;">아직 저장된 템플릿이 없습니다.</p>
                    <?php else : ?>
                        <?php foreach ( $templates as $i => $t ) : ?>
                            <div class="aibp-tpl-card" id="aibp-card-<?php echo $i; ?>"
                                 style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 14px;margin-bottom:12px;display:flex;gap:12px;align-items:center;">
                                <?php if ( ! empty( $t['preview_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $t['preview_url'] ); ?>" alt=""
                                         style="width:90px;height:50px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $t['name'] ); ?></div>
                                    <div style="font-size:11px;color:#888;margin-top:2px;">
                                        제목 위치: (<?php printf('%.0f%%,%.0f%%', $t['title_x']*100, $t['title_y']*100); ?>) /
                                        부제목: (<?php printf('%.0f%%,%.0f%%', $t['sub_x']*100, $t['sub_y']*100); ?>)
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                                    <button class="button button-small aibp-edit-btn" data-idx="<?php echo $i; ?>">✏️ 수정</button>
                                    <button class="button button-small aibp-del-btn"  data-idx="<?php echo $i; ?>"
                                            style="color:#d32f2f;border-color:#d32f2f;">🗑 삭제</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var ajaxUrl = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
            var nonce   = '<?php echo esc_js( $nonce ); ?>';
            var tplData = <?php echo wp_json_encode( $templates ); ?>;

            /* ── 미디어 업로더 ──────────────────────── */
            var mediaFrame = null;

            $('#aibp-upload-btn').on('click', function(e) {
                e.preventDefault();

                // wp.media 사용 가능 여부 확인
                if ( typeof wp === 'undefined' || typeof wp.media === 'undefined' ) {
                    // Fallback: 숨겨진 파일 input으로 직접 업로드
                    $('#aibp-file-input').trigger('click');
                    return;
                }

                if ( mediaFrame ) {
                    mediaFrame.open();
                    return;
                }

                mediaFrame = wp.media({
                    title:    '템플릿 이미지 선택',
                    button:   { text: '이 이미지 사용' },
                    multiple: false,
                    library:  { type: 'image' }
                });

                mediaFrame.on('select', function() {
                    var att = mediaFrame.state().get('selection').first().toJSON();
                    $('#aibp-attachment-id').val(att.id);
                    $('#aibp-upload-name').text(att.filename || att.url.split('/').pop());
                    loadPreview(att.url);
                });

                mediaFrame.open();
            });

            /* ── Fallback: 파일 직접 업로드 ─────────── */
            $('#aibp-file-input').on('change', function() {
                var file = this.files[0];
                if (!file) return;

                $('#aibp-upload-name').text('업로드 중... ' + file.name);
                $('#aibp-form-msg').text('').css('color','#555');

                var formData = new FormData();
                formData.append('action',   'aibp_upload_template_image');
                formData.append('nonce',    nonce);
                formData.append('file',     file);

                $.ajax({
                    url:         ajaxUrl,
                    type:        'POST',
                    data:        formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            $('#aibp-attachment-id').val(res.data.attachment_id);
                            $('#aibp-upload-name').text(file.name);
                            loadPreview(res.data.url);
                            $('#aibp-form-msg').text('✅ 이미지 업로드 완료').css('color','#2e7d32');
                        } else {
                            $('#aibp-upload-name').text('업로드 실패');
                            $('#aibp-form-msg').text('❌ ' + (res.data && res.data.message ? res.data.message : '업로드 실패')).css('color','#d32f2f');
                        }
                    },
                    error: function() {
                        $('#aibp-upload-name').text('오류 발생');
                        $('#aibp-form-msg').text('❌ 서버 오류. 다시 시도해주세요.').css('color','#d32f2f');
                    }
                });
            });

            /* ── 미리보기 로드 ──────────────────────── */
            function loadPreview(url) {
                var $img = $('#aibp-preview-img');
                $img.attr('src', url).show();
                $img.off('load').on('load', function() {
                    initRects();
                });
                if ($img[0].complete && $img[0].naturalWidth) {
                    initRects();
                }
            }

            /* ── 드래그/리사이즈 박스 초기화 ─────────── */
            function initRects() {
                var $wrap = $('#aibp-canvas-wrap');
                var wW = $wrap.innerWidth();
                var wH = $('#aibp-preview-img').height() || (wW * 9 / 16);
                if (wH === 0) wH = wW * 9 / 16;
                $wrap.css('height', wH + 'px');

                ['#aibp-title-rect', '#aibp-sub-rect'].forEach(function(sel) {
                    var $r   = $(sel);
                    var type = $r.data('type');
                    var px = parseFloat(type === 'title' ? $('#f-title-x').val() : $('#f-sub-x').val());
                    var py = parseFloat(type === 'title' ? $('#f-title-y').val() : $('#f-sub-y').val());
                    var pw = parseFloat(type === 'title' ? $('#f-title-w').val() : $('#f-sub-w').val());
                    var ph = parseFloat(type === 'title' ? $('#f-title-h').val() : $('#f-sub-h').val());
                    $r.css({
                        left:   (px * wW) + 'px',
                        top:    (py * wH) + 'px',
                        width:  (pw * wW) + 'px',
                        height: (ph * wH) + 'px'
                    }).show();
                    makeDraggable($r, $wrap, wW, wH);
                    makeResizable($r, $wrap, wW, wH);
                });
            }

            function saveRectValues() {
                var $wrap = $('#aibp-canvas-wrap');
                var wW = $wrap.innerWidth();
                var wH = $wrap.height();
                if (!wW || !wH) return;
                var $t = $('#aibp-title-rect'), $s = $('#aibp-sub-rect');
                var tp = $t.position(), sp = $s.position();
                $('#f-title-x').val((tp.left / wW).toFixed(4));
                $('#f-title-y').val((tp.top  / wH).toFixed(4));
                $('#f-title-w').val(($t.width()  / wW).toFixed(4));
                $('#f-title-h').val(($t.height() / wH).toFixed(4));
                $('#f-sub-x').val((sp.left / wW).toFixed(4));
                $('#f-sub-y').val((sp.top  / wH).toFixed(4));
                $('#f-sub-w').val(($s.width()  / wW).toFixed(4));
                $('#f-sub-h').val(($s.height() / wH).toFixed(4));
            }

            function makeDraggable($r, $wrap) {
                var isDragging = false, startX, startY, origL, origT;
                $r.off('mousedown.drag').on('mousedown.drag', function(e) {
                    if ($(e.target).hasClass('aibp-resize-handle')) return;
                    isDragging = true;
                    startX = e.pageX; startY = e.pageY;
                    origL  = $r.position().left;
                    origT  = $r.position().top;
                    e.preventDefault();
                });
                $(document).off('mousemove.drag' + $r.attr('id'))
                    .on('mousemove.drag' + $r.attr('id'), function(e) {
                        if (!isDragging) return;
                        var wW = $wrap.innerWidth(), wH = $wrap.height();
                        var newL = Math.max(0, Math.min(origL + (e.pageX - startX), wW - $r.width()));
                        var newT = Math.max(0, Math.min(origT + (e.pageY - startY), wH - $r.height()));
                        $r.css({ left: newL + 'px', top: newT + 'px' });
                        saveRectValues();
                    });
                $(document).off('mouseup.drag' + $r.attr('id'))
                    .on('mouseup.drag' + $r.attr('id'), function() { isDragging = false; });
            }

            function makeResizable($r, $wrap) {
                var isResizing = false, startX, startY, origW, origH;
                $r.find('.aibp-resize-handle')
                    .off('mousedown.resize')
                    .on('mousedown.resize', function(e) {
                        isResizing = true;
                        startX = e.pageX; startY = e.pageY;
                        origW  = $r.width(); origH = $r.height();
                        e.preventDefault(); e.stopPropagation();
                    });
                $(document).off('mousemove.resize' + $r.attr('id'))
                    .on('mousemove.resize' + $r.attr('id'), function(e) {
                        if (!isResizing) return;
                        var wW = $wrap.innerWidth(), wH = $wrap.height();
                        var pos = $r.position();
                        var nw = Math.max(40, Math.min(origW + (e.pageX - startX), wW - pos.left));
                        var nh = Math.max(18, Math.min(origH + (e.pageY - startY), wH - pos.top));
                        $r.css({ width: nw + 'px', height: nh + 'px' });
                        saveRectValues();
                    });
                $(document).off('mouseup.resize' + $r.attr('id'))
                    .on('mouseup.resize' + $r.attr('id'), function() { isResizing = false; });
            }

            /* ── 저장 ──────────────────────────────── */
            $('#aibp-save-tpl-btn').on('click', function() {
                var name     = $('#aibp-tpl-name').val().trim();
                var attId    = $('#aibp-attachment-id').val();
                var editIdx  = $('#aibp-edit-idx').val();
                var fontPath = $('#aibp-font-path').val().trim();

                if (!name)  { $('#aibp-form-msg').text('스타일명을 입력하세요.').css('color','#d32f2f'); return; }
                if (!attId) { $('#aibp-form-msg').text('이미지를 선택/업로드하세요.').css('color','#d32f2f'); return; }

                $('#aibp-save-tpl-btn').prop('disabled', true).text('저장 중...');
                $('#aibp-form-msg').text('').css('color','#555');

                var postData = {
                    action:        'aibp_save_template',
                    nonce:         nonce,
                    name:          name,
                    attachment_id: attId,
                    edit_idx:      editIdx,
                    title_x: $('#f-title-x').val(), title_y: $('#f-title-y').val(),
                    title_w: $('#f-title-w').val(), title_h: $('#f-title-h').val(),
                    sub_x:   $('#f-sub-x').val(),   sub_y:   $('#f-sub-y').val(),
                    sub_w:   $('#f-sub-w').val(),   sub_h:   $('#f-sub-h').val()
                };

                if (fontPath) {
                    $.post(ajaxUrl, { action: 'aibp_save_font_path', nonce: nonce, font_path: fontPath });
                }

                $.post(ajaxUrl, postData, function(res) {
                    if (res.success) {
                        $('#aibp-form-msg').text('✅ ' + res.data.message).css('color','#2e7d32');
                        setTimeout(function() { location.reload(); }, 900);
                    } else {
                        $('#aibp-form-msg').text('❌ ' + (res.data && res.data.message ? res.data.message : '저장 실패')).css('color','#d32f2f');
                        $('#aibp-save-tpl-btn').prop('disabled', false).text('💾 템플릿 저장');
                    }
                }).fail(function() {
                    $('#aibp-form-msg').text('❌ 서버 오류').css('color','#d32f2f');
                    $('#aibp-save-tpl-btn').prop('disabled', false).text('💾 템플릿 저장');
                });
            });

            /* ── 초기화 ─────────────────────────────── */
            $('#aibp-reset-form-btn').on('click', function() {
                $('#aibp-tpl-name, #aibp-attachment-id, #aibp-edit-idx').val('');
                $('#aibp-upload-name').text('선택된 파일 없음');
                $('#aibp-preview-img').hide().attr('src', '');
                $('#aibp-title-rect, #aibp-sub-rect').hide();
                $('#f-title-x').val('0.05'); $('#f-title-y').val('0.55');
                $('#f-title-w').val('0.90'); $('#f-title-h').val('0.25');
                $('#f-sub-x').val('0.05');   $('#f-sub-y').val('0.80');
                $('#f-sub-w').val('0.90');   $('#f-sub-h').val('0.12');
                $('#aibp-form-msg').text('');
                mediaFrame = null;
            });

            /* ── 삭제 ──────────────────────────────── */
            $(document).on('click', '.aibp-del-btn', function() {
                if (!confirm('이 템플릿을 삭제하시겠습니까?')) return;
                var idx = $(this).data('idx');
                var $card = $('#aibp-card-' + idx);
                $.post(ajaxUrl, { action: 'aibp_delete_template', nonce: nonce, idx: idx }, function(res) {
                    if (res.success) {
                        $card.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(res.data && res.data.message ? res.data.message : '삭제 실패');
                    }
                });
            });

            /* ── 수정 ──────────────────────────────── */
            $(document).on('click', '.aibp-edit-btn', function() {
                var idx = parseInt($(this).data('idx'), 10);
                var t   = tplData[idx];
                if (!t) return;
                $('#aibp-edit-idx').val(idx);
                $('#aibp-tpl-name').val(t.name);
                $('#aibp-attachment-id').val(t.attachment_id);
                $('#aibp-upload-name').text('기존 이미지 유지 (새로 선택하려면 업로드 버튼 클릭)');
                $('#f-title-x').val(t.title_x); $('#f-title-y').val(t.title_y);
                $('#f-title-w').val(t.title_w); $('#f-title-h').val(t.title_h);
                $('#f-sub-x').val(t.sub_x);     $('#f-sub-y').val(t.sub_y);
                $('#f-sub-w').val(t.sub_w);     $('#f-sub-h').val(t.sub_h);
                loadPreview(t.preview_url);
                $('html,body').animate({ scrollTop: 0 }, 400);
            });
        });
        </script>
        <?php
    }

    /* ── 콘텐츠 생성 ── */
    private function generate_blog_content( $topic, $type ) {
        $prompt = $this->build_prompt( $topic, $type );
        return $this->generate_with_gemini( $prompt, $topic, $type );
    }

    private function generate_with_gemini( $prompt, $topic = '', $type = 'informational' ) {
        $allowed = $this->get_allowed_html();
        $body    = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'temperature' => 0.3, 'topK' => 40, 'topP' => 0.90, 'maxOutputTokens' => 8000 ],
            'tools'            => [ [ 'google_search' => (object)[] ] ],  // ✅ Google Search Grounding: 최신 정보 자동 검색
        ];
        $data = $this->call_gemini_api( $body, 160 );
        if ( is_wp_error( $data ) ) return $data;
        $text = $this->extract_text( $data );
        if ( is_wp_error( $text ) ) return $text;

        $meta_info = $this->extract_meta_info( $text );
        // 제목은 사용자가 직접 작성 — title 필드 제거
        unset( $meta_info['title'] );
        $text      = preg_replace( '/<!--\s*(TITLE|META_DESC|SLUG|FOCUS_KEYWORD):[\s\S]*?-->\s*/i', '', $text );
        $text      = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>\s*/i', '', $text );
        $text      = preg_replace( '/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $text );
        $text      = preg_replace( '/\*(.+?)\*/us', '$1', $text );
        $text      = str_replace( '*', '', $text );
        // ── H4 태그 완전 제거: h4 → h3 상향 변환 ──
        $text      = preg_replace( '/<h4([^>]*)>/i', '<h3$1>', $text );
        $text      = preg_replace( '/<\/h4>/i', '</h3>', $text );
        $html      = wp_kses( trim( $text ), $allowed );

        /* ── 이어쓰기: 공백 제외 글자수 700자 미달 시 최대 2회 시도 ── */
        $target_chars = 700;
        // 공백 제외 글자수 계산: HTML 태그 제거 → HTML 엔티티 디코딩 → 모든 공백(유니코드 포함) 제거 → 글자수
        $plain_text   = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $char_count   = mb_strlen( preg_replace( '/[\s\x{00A0}\x{3000}]+/u', '', $plain_text ), 'UTF-8' );

        for ( $i = 0; $i < 2 && $char_count < $target_chars; $i++ ) {
            $tail    = mb_substr( wp_strip_all_tags( $html ), -500, null, 'UTF-8' );
            $remain  = $target_chars - $char_count;
            $tbl_note = in_array( $type, [ 'policy_guide', 'utility' ], true )
                ? '- <table>: 이 유형(policy_guide/utility)에서만 허용 — H3 직후 1~2개만 사용'
                : '- <table>: 이 유형에서 절대 사용 금지 — 비교·요약은 반드시 <ul>/<ol>로만';
            $img_note  = '- <img>: src는 반드시 실제 접근 가능한 URL만 사용 (가짜·임의 URL 절대 금지). 불확실하면 HTML 주석으로 대체';
            $cp      = "블로그 글이 공백제외 {$char_count}자입니다. 목표 800자까지 {$remain}자 이상 추가 작성하세요.

━━ 반드시 준수 (위반 즉시 재작성) ━━
- 허용 태그: h2/h3/p/ul/ol/li/strong/u/img (별표·한자·마크다운·SEO주석 금지)
- H1·H4 태그 절대 금지 / <title> 태그 절대 금지
{$tbl_note}
{$img_note}

━━ 구조 규칙 (새로 추가하는 섹션에도 동일 적용) ━━
- H2 섹션 1~2개 추가 (FAQ 포함 총 H2 최소 4개 유지)
- 각 H2 직후: 요약 <p> 1개 (1~2문장)
- 각 H2 안에 H3 반드시 2~3개
- 각 H3 직후: <p> 1~3문장 → 그 다음 <ul>/<ol> (li 최소 3개)
- H3 섹션에서 H4 완전 금지 — H3 다음은 반드시 <p>+<ul>/<ol> 구조
- strong: 핵심 수치·키워드에 섹션당 2~4개 (단어·구문 단위만)
- u: 중요 용어·핵심 키워드에 섹션당 1~2개
- 문장은 자연스럽게 (20~70자) / 비자연스러운 어구 절대 금지
- 새로운 관점·심화 정보만 추가 (기존 내용 반복 금지)

이전 글 끝부분:
{$tail}

이어서 (HTML만 출력):";
            $cd = $this->call_gemini_api(
                [ 'contents' => [ [ 'parts' => [ [ 'text' => $cp ] ] ] ], 'generationConfig' => [ 'temperature' => 0.3, 'maxOutputTokens' => 3000 ], 'tools' => [ [ 'google_search' => (object)[] ] ] ],
                120
            );
            if ( is_wp_error( $cd ) ) break;
            $ct = $this->extract_text( $cd );
            if ( is_wp_error( $ct ) ) break;
            $ct   = preg_replace( '/<!--[\s\S]*?-->/i', '', $ct );
            $ct   = preg_replace( '/```[\s\S]*?```/i', '', $ct );
            $ct   = preg_replace( '/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $ct );
            $ct   = preg_replace( '/\*(.+?)\*/us', '$1', $ct );
            $ct   = str_replace( '*', '', $ct );
            // ── H4 태그 완전 제거 (이어쓰기 결과에도 동일 적용) ──
            $ct   = preg_replace( '/<h4([^>]*)>/i', '<h3$1>', $ct );
            $ct   = preg_replace( '/<\/h4>/i', '</h3>', $ct );
            $html = wp_kses( $html . "\n" . trim( $ct ), $allowed );
            // 이어쓰기 후 공백 제외 글자수 재계산
            $plain_text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $char_count = mb_strlen( preg_replace( '/[\s\x{00A0}\x{3000}]+/u', '', $plain_text ), 'UTF-8' );
        }

        // ── 최종 H4 잔존 태그 제거 (프롬프트 무시로 생성된 경우 방어 처리) ──
        $html = preg_replace( '/<h4([^>]*)>/i', '<h3$1>', $html );
        $html = preg_replace( '/<\/h4>/i', '</h3>', $html );

        return [ 'html' => $html, 'meta_info' => $meta_info ];
    }

    private function extract_text( $data ) {
        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) return $data['candidates'][0]['content']['parts'][0]['text'];
        if ( isset( $data['candidates'][0]['content']['parts'] ) ) {
            $parts = [];
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) { if ( isset( $part['text'] ) ) $parts[] = $part['text']; }
            if ( ! empty( $parts ) ) return implode( "\n\n", $parts );
        }
        return new WP_Error( 'empty_response', 'API 응답에 텍스트가 없습니다.' );
    }

    private function get_allowed_html() {
        return [
            'h2' => [ 'class' => [], 'id' => [] ], 'h3' => [ 'class' => [], 'id' => [] ],
            'p'  => [ 'class' => [], 'style' => [] ], 'ul' => [ 'class' => [] ], 'ol' => [ 'class' => [] ], 'li' => [ 'class' => [] ],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'br' => [],
            'a' => [ 'href' => [], 'title' => [], 'target' => [], 'rel' => [] ],
            'img' => [ 'src' => [], 'alt' => [], 'width' => [], 'height' => [], 'class' => [], 'style' => [], 'loading' => [] ],
            'figure' => [ 'class' => [] ], 'figcaption' => [ 'class' => [] ],
            'table' => [ 'class' => [], 'border' => [], 'cellpadding' => [], 'cellspacing' => [] ],
            'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [ 'colspan' => [], 'rowspan' => [] ], 'td' => [ 'colspan' => [], 'rowspan' => [] ],
            'div' => [ 'class' => [], 'style' => [] ], 'span' => [ 'class' => [], 'style' => [] ],
        ];
    }

    private function get_allowed_html_no_table() {
        return [
            'h2' => [ 'class' => [], 'id' => [] ], 'h3' => [ 'class' => [], 'id' => [] ],
            'p'  => [ 'class' => [], 'style' => [] ], 'ul' => [ 'class' => [] ], 'ol' => [ 'class' => [] ], 'li' => [ 'class' => [] ],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'br' => [],
            'a' => [ 'href' => [], 'title' => [], 'target' => [], 'rel' => [] ],
            'img' => [ 'src' => [], 'alt' => [], 'width' => [], 'height' => [], 'class' => [], 'style' => [], 'loading' => [] ],
            'figure' => [ 'class' => [] ], 'figcaption' => [ 'class' => [] ],
            'div' => [ 'class' => [], 'style' => [] ], 'span' => [ 'class' => [], 'style' => [] ],
        ];
    }

    /* ── 프롬프트 빌드 ── */
    private function build_prompt( $topic, $type ) {
        $current_date = date( 'Y년 m월 d일' );
        $year         = date( 'Y' );

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
           ① 유사문서 완전 차단 — 매 생성마다 고유 시드
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
        $ts       = time();
        $th       = crc32( $topic );
        $micro    = (int)( microtime( true ) * 1000 ) % 997;
        $rand_ext = wp_rand( 0, 9999 ); // ✅ v3.7.0: WordPress 보안 난수 추가
        $seed_idx = abs( $ts + $th + $micro + $rand_ext ) % 20;

        $intros = [
            '도입부A: 독자가 지금 막 겪는 구체적 상황을 2문장으로 묘사하고 즉시 핵심 정보로 전환',
            '도입부B: 가장 흔한 실수 2가지를 먼저 짚고 올바른 방법으로 자연 전환',
            '도입부C: 핵심 수치(금액/기간/비율)를 첫 문장에 배치 — 역피라미드 결론 먼저',
            '도입부D: 이 글에서 다룰 3가지 핵심 포인트를 첫 단락에 명시하는 약속형',
            '도입부E: 실무 경험자 관점의 1인칭 사례 소개 → E-E-A-T 신뢰 즉시 구축',
            '도입부F: 독자가 검색창에 치는 질문 그 자체로 시작 → 즉시 답변 제공',
            '도입부G: 최신 변화·정책 변경 강조 → 신선도 어필로 클릭 유지',
            '도입부H: 비용 절감·시간 단축·리스크 회피 3가지 실익을 수치와 함께 배치',
            '도입부I: 흔한 오해를 먼저 지적하고 실제와 대비하는 교정 구조',
            '도입부J: 자가진단 체크리스트 3항목으로 시작 → 해당되면 이 글이 필요하다는 흐름',
            '도입부K: 최근 통계·연구 수치로 시작 → 신뢰도와 검색 의도 동시 공략',
            '도입부L: 성공 사례(구체적 숫자)와 실패 사례를 대조하는 스토리텔링',
            '도입부M: 비교 대상 2~3가지를 첫 문단에 나열 → 선택 의도 직격',
            '도입부N: 독자가 얻는 구체적 이득을 약속 형식으로 명시',
            '도입부O: 시간순 흐름 (예전에는~, 지금은~) → 변화 맥락으로 필요성 설득',
            '도입부P: 한 줄 요약(TL;DR) 먼저 제시 후 심화 전개',
            '도입부Q: 독자가 실제로 궁금해하는 생활 밀착형 질문으로 시작',
            '도입부R: 주변 사람 사례를 들어 공감 확보 후 해결책 제시',
            '도입부S: 관련 제도·정책의 핵심 변경 사항을 첫 줄에 배치',
            '도입부T: 이 주제를 모르면 손해 보는 이유를 3줄로 압축해 위기감 조성',
        ];
        $struct_seed = $intros[ $seed_idx ];

        $h2_styles = [
            '소제목 형식: 질문형 ("왜 ~인가?", "어떻게 ~하나?", "~이 중요한 이유?")',
            '소제목 형식: 숫자 포함형 ("3가지 핵심", "5단계 가이드", "7가지 주의사항")',
            '소제목 형식: 결과 중심형 ("~하면 달라지는 것", "~의 실제 효과", "~로 해결")',
            '소제목 형식: 직접 키워드형 ("~의 모든 것", "~를 위한 핵심", "~완벽 정리")',
        ];
        $h2_style = $h2_styles[ abs( $th + $micro ) % 4 ];

        $tone_styles = [
            '문장: 단문 위주(20~35자), 명확 빠른 호흡, 정보 전달 최우선',
            '문장: 중문 위주(35~60자), 이유·근거를 같은 문장에 포함',
            '문장: 단문·중문 교차, 강조는 단문·설명은 중문, 리듬감',
            '문장: 구어체 혼합(~입니다/~합니다+~이에요/~거든요), 친근·신뢰감',
        ];
        $tone_style  = $tone_styles[ abs( $th * 3 + $micro ) % 4 ];
        $unique_id   = "{$ts}-{$th}-{$micro}-{$rand_ext}";

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
           ② 글 유형별 고유 특성
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */

        /* ── 정보성 ── */
        $g_info = "
【정보성 — 2026 Elite SEO Content Specialist 기준 + 네이버 웹문서 1위 장악 + 애드센스 수익화 극대화】

# Role: 2026 Elite SEO Content Specialist
당신은 실전 경험이 풍부한 SEO·콘텐츠 제작 전문가입니다. 2026년 기준 구글 최신 검색 알고리즘, E-E-A-T 원칙, 네이버 C-Rank·다이아 로직을 완벽하게 이해하고 있습니다.

━━ 제목 전략 (네이버 웹문서 섹션 1위 장악 필수) ━━
- '{$topic}'와 연관된 3~5개 키워드 변형 분석 → 네이버 웹문서 순위 가장 높은 것을 메인 키워드로 선정
- 서브 키워드: 메인 키워드 포함 + 검색량 높은 연관어 추가
- 연관 키워드 최소 30개를 본문 전체에 자연 분산 배치
- '|' 절대 금지 / 연도 삽입 절대 금지

━━ 1️⃣ 메타 디스크립션 ━━
- 길이: 공백 포함 120~160자 내외
- 메인 키워드 + 서브 키워드(1개 이상) 필수 포함
- \"~에 대해 알아봅니다\" 금지 → \"2026년 기준 ~하는 방법과 주의사항을 알려드립니다\" 형태

━━ 2️⃣ 도입부 ━━
- 인사말·불필요한 서론 절대 금지
- 첫 문장: 메인 키워드 + 서브 키워드 1~2개 자연 포함
- 도입부 <p> 1개 (2~3문장 — 독자 상황 공감 → 핵심 정보 약속)

━━ ★ 3️⃣ 본문 구조 (H2/H3만 사용 — H4 완전 금지) ★ ━━
[본론 H2] FAQ 제외 반드시 3개 이상 (FAQ H2 포함 총 H2 최소 4개)
- 각 H2 직후: 섹션 요약 <p> 1개 (1~2문장)
- 각 H2 내부에 H3 반드시 2~3개

[각 H3 구조 — 핵심 규칙]
① H3 태그 직후: <p> 1~3문장 (핵심 설명, 수치·근거 포함)
② 그 다음: 반드시 <ul> 불릿포인트 (li 최소 3개)
③ H3 내부에 H4 절대 금지 — H3 → <p> → <ul> 구조가 전부

⚠️ H4 태그 완전 금지: 이 글 어디에도 h4 태그 사용 절대 불가

━━ 4️⃣ strong 태그 & 밑줄(<u>) ━━
- <strong>: 핵심 수치·키워드·결론에 섹션당 2~4개 (단어·구문 단위 — 문장 전체 strong 금지)
- <u>: 중요 용어·브랜드명·핵심 개념에 섹션당 1~2개
- 남용 금지 (전체의 10~15% 이내) — 너무 많으면 강조 효과 사라짐

━━ 5️⃣ FAQ (★ 필수 4~6개) ━━
- 위치: 마지막 본론 H2 직후
- <h2>자주 묻는 질문</h2>
- 독자가 실제로 검색하는 질문 4~6개 선정
- 각 Q: <h3>질문?</h3> / 각 A: <p>2~4문장 — 명확한 수치·조건 포함 답변</p>

━━ 6️⃣ 끝인사 절대 금지 ━━
- \"지금까지~\", \"도움이 되셨기를\", \"결론적으로\" 완전 금지
- FAQ 직후 마지막 정리 문단 <p> 1개 후 깔끔하게 종료

━━ 표(table) — 정보성 글에서 절대 사용 금지 ━━
- <table> 완전 금지 / 비교·요약은 반드시 <ul>/<ol> 리스트로만

━━ 이미지(img) 삽입 규칙 ━━
- 본론 H2 섹션 사이 2~3개 삽입
- 불확실한 URL 금지 → HTML 주석: <!-- [이미지 위치] alt: [{$topic} 관련 이미지] 800x400 -->
- alt 텍스트: 메인 키워드 포함 / loading=\"lazy\" 필수

━━ 네이버 SEO 특화 ━━
- C-Rank: 전문성(수치·출처) + 활동성(최신 날짜) + 신뢰성(경험 서술)
- 모바일 가독성: 문장 1개 최대 2줄 이내 / 단락 3~4줄 이내

━━ 애드센스 수익화 극대화 ━━
- 고단가 키워드(금융·보험·건강·법률·부동산) 자연 배치 — 본문 3~5회
- 각 H2 섹션이 단일 주제에 집중 → 애드센스 문맥 매칭 정확도 상승
- H3 불릿포인트에 관련 서비스·제품 키워드 자연 삽입
- 독자 체류 극대화: 각 섹션이 다음 섹션으로 자연 유도 (스크롤 방문 광고 노출 증가)";

        /* ── 유틸리티 ── */
        $g_util = "
【유틸리티 — 2026 Elite SEO Content Specialist + 표 2개 필수 + 네이버 웹문서 1위 + 애드센스 최적화】

# Role: 2026 Elite SEO Content Specialist
독자가 이 글 한 편으로 다운로드·설치·발급·신청을 완전히 끝낼 수 있어야 합니다.

━━ 제목 전략 ━━
- '{$topic}'와 연관된 3~5개 키워드 변형 → 네이버 웹문서 순위 가장 높은 것을 메인 키워드로
- 핵심 동작 동사 필수: 다운로드·설치·신청·발급·방법 ('|' 절대 금지, 연도 삽입 절대 금지)
- 연관 키워드 30개 이상 본문 전체 자연 분산

━━ 1️⃣ 메타 디스크립션 ━━
- 120~160자 / 메인 키워드 + 서브 키워드 포함 / 문제 해결 약속형

━━ 2️⃣ 도입부 ━━
- 인사말·불필요한 서론 절대 금지
- 도입부 <p> 1개 (1~2문장 — 무엇인지 간결 정의 + 이 글로 해결할 내용)

━━ ★ 3️⃣ 표 2개 필수 (도입부 직후, 본론 H2 전 배치) ★ ━━
⚠️ 두 표는 도입부 <p> 바로 다음, 본론 H2 시작 전에 반드시 배치

★ [표1 기본 정보] — 카테고리·운영체제·개발사·공식사이트·버전·비용·라이선스
★ [표2 사양·조건] — CPU·메모리·저장공간·운영체제 또는 자격조건·필요서류·신청기간·비용
(두 표 모두 thead+tbody 구조 / <th> 필수)

━━ ★ 4️⃣ 본문 구조 (H2/H3만 — H4 완전 금지) ★ ━━
[본론 H2] FAQ 제외 3개 이상 (FAQ 포함 총 H2 최소 4개)
- 각 H2 직후: 요약 <p> 1개 (1~2문장)
- 각 H2 내부에 H3 반드시 2~3개

[각 H3 구조 — 핵심 규칙]
① H3 직후: <p> 1~3문장 (설명·이유 포함)
② 그 다음: <ul> 또는 <ol> (li 최소 3개)
③ H3 내부 H4 절대 금지

⚠️ H4 태그 완전 금지

━━ 5️⃣ strong 태그 & 밑줄(<u>) ━━
- <strong>: 핵심 수치·단계·주의사항에 섹션당 2~4개
- <u>: 중요 용어·브랜드명에 섹션당 1~2개 / 남용 금지

━━ 6️⃣ FAQ (필수 4~6개) ━━
- 위치: 마지막 본론 H2 직후
- <h2>자주 묻는 질문</h2>
- 4~6개 Q&A / 각 Q: <h3>질문?</h3> / 각 A: <p>2~4문장 구체적 해결책</p>

━━ 끝인사 절대 금지 ━━

━━ 쉬운 설명 필수 ━━
- 전문 용어 사용 즉시 괄호로 쉬운 말 병기
- 설치·신청 단계는 <ol> 사용 (H3 직후 순서 있는 단계 전용)

━━ 이미지 삽입 ━━
- 표2 직후 + 본론 중간 총 2개 / 불확실 URL → HTML 주석 대체

━━ 네이버 SEO & 애드센스 최적화 ━━
- C-Rank: 전문성(정확 수치) + 활동성(최신 날짜) + 신뢰성(경험 서술)
- 각 H2 섹션이 단일 주제 집중 → 애드센스 문맥 광고 매칭 정확도 극대화
- 고단가 키워드(금융·보험·건강·법률) 자연 배치 — 본문 3~5회";

        /* ── 정책·공공 ── */
        $g_policy = "
【정책·공공 — 2026 Elite SEO Content Specialist + 표 선택사항(최대 2개) + 네이버 웹문서 1위 + 애드센스 최적화】

# Role: 2026 Elite SEO Content Specialist
정확한 공공 정보를 신뢰도 높게 전달하여 독자의 문제를 해결해 주는 글을 작성합니다.

━━ 제목 전략 ━━
- '{$topic}'와 연관된 3~5개 키워드 변형 → 네이버 웹문서 순위 가장 높은 것을 메인 키워드로
- 정책명 + 핵심 수혜 내용 구조 ('|' 절대 금지)
- 연도가 실제 관련된 정책이면 연도 포함 가능, 관련 없으면 금지
- 연관 키워드 30개 이상 본문 전체 자연 분산

━━ 1️⃣ 메타 디스크립션 ━━
- 120~160자 / 메인+서브 키워드 포함 / 문제 해결 약속형

━━ 2️⃣ 도입부 ━━
- 인사말·불필요한 서론 절대 금지
- 도입부 <p> 1개 (1~2문장 — 핵심 혜택 수치 먼저 + 이 글에서 다룰 내용)

━━ ★ 3️⃣ 표 (선택사항, 최대 2개) ★ ━━
⚠️ 표는 완전히 선택사항 — 아래 기준에 해당할 때만 사용
- 대상/조건/금액/기간 정보가 표가 더 명확할 때만
- 표 사용 시: 도입부 <p> 직후, 본론 H2 전 배치
- thead+tbody 구조 / <th> 필수

━━ ★ 4️⃣ 본문 구조 (H2/H3만 — H4 완전 금지) ★ ━━
[본론 H2] FAQ 제외 3개 이상 (FAQ 포함 총 H2 최소 4개)
- 각 H2 직후: 요약 <p> 1개 (1~2문장)
- 각 H2 내부에 H3 반드시 2~3개

[각 H3 구조 — 핵심 규칙]
① H3 직후: <p> 1~3문장 (핵심 정보·수치 포함)
② 그 다음: <ul> 또는 <ol> (li 최소 3개)
③ H3 내부 H4 절대 금지

⚠️ H4 태그 완전 금지

━━ 5️⃣ strong 태그 & 밑줄(<u>) ━━
- <strong>: 지원 금액·신청 기간·자격 조건 수치에 섹션당 2~4개
- <u>: 중요 정책 용어·기관명에 섹션당 1~2개 / 남용 금지

━━ 6️⃣ FAQ (필수 4~6개) ━━
- 위치: 마지막 본론 H2 직후
- <h2>자주 묻는 질문</h2>
- 4~6개 Q&A / 각 Q: <h3>질문?</h3> / 각 A: <p>2~4문장 — 수치·조건 포함 명확한 답변</p>

━━ 끝인사 절대 금지 ━━

━━ 이미지 삽입 ━━
- 본론 중간 1~2개 / 불확실 URL → HTML 주석 대체

━━ 네이버 SEO & 애드센스 최적화 ━━
- C-Rank: 전문성(정확 수치·출처) + 활동성(최신 날짜) + 신뢰성(경험 서술)
- 각 H2 섹션이 단일 주제 집중 → 애드센스 문맥 광고 매칭 정확도 극대화
- 정부 지원 관련 고단가 금융·보험·법률 키워드 자연 배치 — 본문 3~5회
- 허위·과장 표현 금지 / 검증 가능한 수치만";

        /* ── 리뷰·비교 ── */
        $g_review = "
【리뷰·비교 — 쿠팡파트너스 최적화 + H2/H3 구조 + 애드센스 문맥 매칭 극대화】
▶ 목표: 쿠팡파트너스 클릭·구매 전환 극대화 + 애드센스 고단가 광고 문맥 매칭

━━ 제목 전략 ━━
- 비교 대상 + 선정 기준 구조 (연도 삽입 절대 금지, '|' 절대 금지)
- '추천', '비교', '순위', '후기', '가성비', '쿠팡' 키워드 포함

━━ 쿠팡파트너스 최적화 ━━
- 본문 전체에 제품명·모델명 구체적으로 기재
- 가격 정보 반드시 포함 (쿠팡 기준 명시)
- 각 H3 섹션 하단 <ul> 마지막 li에 쿠팡 안내 문구 삽입:
  예: <li>쿠팡에서 <strong>최저가</strong>와 로켓배송 여부를 확인할 수 있습니다.</li>
- '로켓배송', '쿠팡 최저가', '쿠팡 할인' 키워드 본문 3~5회 자연 배치

━━ ★ 본문 구조 (H2/H3만 — H4 완전 금지) ★ ━━
[도입부] <p> 1개 (1~2문장 — 선택 기준 + 이 글의 비교 범위)

[본론 H2] FAQ 제외 3개 이상 (FAQ 포함 총 H2 최소 4개)
- 각 H2 직후: 요약 <p> 1개 (1~2문장)
- 각 H2 내부에 H3 반드시 2~3개

[각 H3 구조 — 핵심 규칙]
① H3 직후: <p> 1~3문장 (제품 설명·특징·수치 포함)
② 그 다음: <ul> 불릿포인트 (li 최소 3개 — 장점·단점·특징·쿠팡 안내 포함)
③ H3 내부 H4 절대 금지

⚠️ H4 태그 완전 금지
⚠️ 리뷰에서 표(table) 절대 금지 — 모든 비교는 <ul>/<ol>로만

━━ strong 태그 & 밑줄(<u>) ━━
- <strong>: 가격·평점·핵심 기능 수치·최종 추천 제품명에 섹션당 2~4개
- <u>: 제품명·브랜드명에 섹션당 1~2개 / 남용 금지

━━ FAQ (필수 4~6개) ━━
- <h2>자주 묻는 질문</h2>
- 4~6개 Q&A / 각 Q: <h3>질문?</h3> / 각 A: <p>2~4문장 구체적 답변</p>

━━ 이미지 삽입 ━━
- H2 섹션 사이 2~3개 / 불확실 URL → HTML 주석 대체

━━ 애드센스 문맥 매칭 극대화 ━━
- 각 H2 섹션이 단일 제품 카테고리에 집중 → 쇼핑 광고 매칭 정확도 상승
- 쇼핑 관련 고단가 키워드 자연 배치 (광고 클릭률 30% 목표)
- 제품 스펙·수치는 일상 언어로 풀어 설명 (예: '램 16GB = 여러 앱 동시 실행 가능')
- 구매 경험 없는 독자도 이해 가능한 수준";

        $type_guides = [
            'informational'     => $g_info,
            'utility'           => $g_util,
            'policy_guide'      => $g_policy,
            'review_comparison' => $g_review,
        ];
        $guide = isset( $type_guides[ $type ] ) ? $type_guides[ $type ] : $g_info;

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
           ③ FAQ 공통
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
        $faq_rule = "

━━ FAQ 섹션 필수 (반드시 4~6개 — 전 유형 공통) ━━
본문 마지막 본론 H2 직후에 반드시 포함.
독자가 실제로 검색하는 질문 형태로 작성.

⚠️ FAQ H2 태그 필수: <h2> 사용 (절대 h3·h4 사용 금지)
⚠️ 각 질문은 반드시 <h3> 사용 — 4개 이상 6개 이하

구조 (반드시 이 태그 그대로 사용):
<h2>자주 묻는 질문</h2>
<h3>질문 내용? (실제 검색어 형태)</h3>
<p>2~4문장 답변. 수치·기간·조건 포함.</p>
(4~6개 반복 / itemscope·itemtype·itemprop 절대 금지)
⚠️ FAQ 답변 <p>에 <ul> 사용 금지 — 답변은 반드시 <p>만";

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
           ⑤ 최종 프롬프트
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
        return "당신은 2026 Elite SEO Content Specialist입니다. 구글·네이버·빙 3대 검색엔진 완벽 최적화 + 애드센스 광고 문맥 매칭 극대화 + 수익화 최적화 + 유사문서·탬플릿 0%에 특화된 한국어 SEO 블로그 전문 작가입니다. 목표는 검색엔진을 위한 기계적인 글이 아닌, '사용자의 문제를 해결해 주는 글'을 작성하여 네이버·구글 상위노출을 달성하는 것입니다.

오늘 날짜: {$current_date} / 주제: '{$topic}' / 글 유형: {$type}

⚡ 최신 정보 우선: Google Search 결과를 바탕으로 최신 수치·정책·가격을 반영하세요.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚫 절대 준수 — 위반 즉시 재작성
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- 한자 0개 / 별표(*) 0개 / 마크다운 문법 0개
- 공백 제외 반드시 700자 이상 (미달 즉시 이어쓰기)
- 제목(TITLE)에 '|' 문자 절대 금지
- 본문 내 <h1> 태그 절대 사용 금지
- 본문 내 <h4> 태그 절대 사용 금지 — h4는 이 플러그인에서 완전 폐기됨
- 본문 내 <title> 태그 절대 삽입 금지
- 이미지 URL: 확인 불가한 URL 금지 — img 태그 생략하고 HTML 주석으로만 표기
- 탬플릿식 글쓰기 절대 금지 (매 생성마다 완전히 다른 구조·표현·흐름)
- 유사문서 생성 절대 금지

━━ [규칙 1] H2 개수 (★ 핵심) ━━
- FAQ H2 포함 총 H2 반드시 4개 이상
- 본론 H2: FAQ 제외 반드시 3개 이상
- FAQ H2 반드시 1개 (마지막 본론 H2 직후)

━━ [규칙 2] H3 배치 ━━
- 모든 본론 H2에 H3 반드시 2~3개
- FAQ 각 질문도 H3 사용

━━ [규칙 3] H3 직후 구조 (★ 핵심 — H4 완전 삭제) ━━
[H3 직후] 반드시 이 순서: ① <p> 1~3문장 → ② <ul>/<ol> (li 최소 3개)
[H2 직후] <p> 1개 (1~2문장 섹션 요약)
⚠️ H4 태그 완전 금지 — 어디에도 사용 불가

━━ [규칙 4] strong 태그 & 밑줄(<u>) ━━
- <strong>: 핵심 수치·키워드·결론에 섹션당 2~4개 (단어·구문 단위만 — 문장 전체 strong 금지)
- <u>: 중요 용어·브랜드명·핵심 개념에 섹션당 1~2개
- 남용 금지 (전체 텍스트의 10~15% 이내)

━━ [규칙 5] 표(table) 사용 제한 ━━
- utility: 반드시 2개 (도입부 직후 배치) / policy_guide: 선택사항 최대 2개
- informational·review_comparison: <table> 완전 금지

━━ [규칙 6] 비자연스러운 어구 완전 금지 ━━
- 반말 절대 금지 (전체 존댓말)
- 인공적 마무리: 이상으로~, 지금까지~, 살펴보았습니다, 알아보았습니다 등
- 과도한 형식: ~에 대해 알아보겠습니다, 결론적으로~, 요컨대~
- 구조 안내: 본문에서는~, 다음 섹션에서는~

━━ 기타 공통 규칙 ━━
- HTML 태그만 출력 (마크다운 코드블록 ``` 절대 금지)
- itemscope·itemtype·itemprop·<script> 본문 삽입 금지
- '광고·협찬·후원' 관련 언급 절대 금지

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔍 네이버 웹문서 섹션 1위 장악 전략 (★ 최우선 목표)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

【핵심: 워드프레스 블로그는 네이버 '웹문서' 섹션에만 노출됨】
→ 웹문서 섹션 1~3위에 반드시 진입해야 클릭 발생

① 메인 키워드 선정: '{$topic}' 관련 3~5개 키워드 변형 중 네이버 웹문서에서 가장 상위 노출될 키워드를 메인으로 선정
② 서브 키워드: 경쟁도 낮고 검색량 높은 서브 키워드를 제목과 본문 앞부분에 적극 활용
③ 연관 키워드 최소 30개: 본문 전체에 자연 분산 배치 (검색 범위 최대 확장)
④ Rank Math 포커스 키워드: 자동 입력되는 포커스 키워드로 검색 시 반드시 1순위 노출 목표
⑤ C-Rank 3요소: 전문성(정확 수치·출처) + 활동성(최신 날짜) + 신뢰성(경험 서술)
⑥ 다이아 로직: 검색 의도 100% 부합 + 독자 체류 극대화 + 역피라미드 구조
⑦ 모바일 가독성: 문장 1개 최대 2줄 이내 / 단락 3~4줄 이내

【구글 E-E-A-T + Featured Snippet】
- 핵심 키워드를 첫 150자 안에 자연 삽입
- 첫 H2 직후 정의 문장 1개 (40~60자, Featured Snippet 타겟)
- LSI 유사어 30개+ 본문 전체 자연 분산

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 애드센스 수익화 극대화 (문맥 매칭 최우선)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
① 각 H2 섹션이 단일 주제에 집중 → 애드센스 크롤러의 문맥 파악 정확도 극대화
② H3 불릿포인트에 관련 서비스·브랜드·제품 키워드 자연 삽입 → 고단가 광고 매칭
③ 고단가 키워드(금융·보험·건강·법률·부동산·쇼핑) 자연 배치 — 본문 전체 3~5회
④ 각 섹션 말미에 관련 키워드 자연 마무리 → 다음 광고 블록 매칭 품질 상승
⑤ 독자 체류 극대화: 각 섹션이 다음 섹션으로 자연 유도 (페이지뷰 × 광고 노출 증가)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 SEO 메타 정보 (첫 3줄 필수 출력)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
첫 줄  : <!-- META_DESC: [120~160자 — 메인키워드+서브키워드+문제해결약속형, \"~에 대해 알아봅니다\" 금지] -->
둘째 줄: <!-- SLUG: [핵심키워드 하이픈 연결] -->
셋째 줄: <!-- FOCUS_KEYWORD: [3~5개 쉼표 구분 — 메인키워드, 서브키워드, 롱테일키워드 포함] -->
주석 3줄 직후 첫 요소 = 반드시 <p> 태그
⚠️ TITLE 주석 출력 완전 금지 — 제목은 사용자가 직접 작성함

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔒 유사문서 완전 차단
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[고유 ID] {$unique_id} / [도입부] {$struct_seed} / [소제목] {$h2_style} / [문장] {$tone_style}

① H2 소제목: 뻔한 소제목 금지 — 검색 의도+고유 관점 조합
② 도입부: 독자 상황 직접 묘사 (인사말 없이 즉시 문제 해결형 시작)
③ 문장 리듬: 짧은 문장(20~35자)과 긴 문장(40~65자) 불규칙 혼재
④ 구어 표현: ~거든요, ~이에요, 사실 ~입니다 등 1~2개 자연 삽입
⑤ 경험 서술: '제가 직접 확인해보니', '실제로 써보면' 등 1~2회
⑥ 탬플릿 구조 완전 파괴: 예측 가능한 정보 배치 순서 반드시 깨뜨릴 것

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📌 글 유형 전용 가이드 [{$type}]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$guide}
{$faq_rule}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ 출력 전 최종 체크리스트
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ 한자 0개 / 별표 0개 / 마크다운 0개 / 공백 제외 700자 이상
✅ 탬플릿 금지 / 유사문서 금지 / TITLE 주석 출력 금지
✅ H4 태그 완전 사용 금지 — h2/h3만 사용
✅ H2(FAQ 포함 최소 4개) / 각 H2→H3(2~3개)
✅ H2 직후 <p>1개(1~2문장) / H3 직후 <p>1~3문장 → <ul>/<ol>(li 3개+)
✅ strong: 섹션당 2~4개 단어·구문 단위 / u: 섹션당 1~2개 / 남용 금지
✅ 표: utility=2개필수, policy_guide=선택(최대2개), info/review=금지
✅ FAQ: <h2>+<h3>+<p> / 반드시 4~6개 / 구체적 해결책 (적어도 4개 미만 금지)
✅ 끝인사 완전 금지 / FAQ 후 마지막 정리 문단으로 깔끔 종료
✅ 네이버: 서브키워드 제목 포함 / 연관키워드 30개+ / C-Rank 충족
✅ 구글: 첫 150자 핵심키워드 / Featured Snippet 정의 문장 / E-E-A-T
✅ 애드센스: 각 H2 단일 주제 집중 / 고단가 키워드 3~5회 / 섹션별 관련 키워드 마무리
✅ META_DESC 120~160자 / 문제해결약속형 / 단순요약 금지

지금 바로 작성하세요! (반드시 700자 이상, H4 금지, FAQ 4~6개 필수, 탬플릿 금지, 유사문서 금지)";
    }
    private function generate_seo_title( $topic, $type = 'informational' ) {
        $year = date( 'Y' );
        // 연도 허용: 정책·공공, 수익형만 (그것도 연도 관련성 있을 때)
        $year_allowed = in_array( $type, [ 'policy_guide' ], true );

        if ( $year_allowed ) {
            $pats = [
                "{$year}년 {$topic} 핵심 정보 완벽 정리",
                "{$topic} {$year}년 최신 정보 완전 분석",
                "{$year}년 {$topic} 핵심 정보와 실전 활용법",
                "{$topic} 완벽 정리 {$year}년 업데이트",
                "{$topic}에 대한 모든 것 {$year}년 최신판",
            ];
        } else {
            $pats = [
                "{$topic} 완벽 정리 꼭 알아야 할 핵심 총정리",
                "{$topic} 핵심 정보 완전 분석",
                "{$topic} 핵심 정보와 실전 활용법",
                "{$topic} 완벽 가이드 처음부터 끝까지",
                "{$topic}에 대한 모든 것 핵심만 모았습니다",
            ];
        }
        return $pats[ absint( date( 'His' ) ) % count( $pats ) ];
    }

    /* ── 메타 정보 추출 (제목 제외 — 사용자가 직접 작성) ── */
    private function extract_meta_info( $content ) {
        $info = [ 'meta_desc' => '', 'slug' => '', 'focus_keyword' => '' ];
        if ( preg_match( '/<!-- META_DESC:\s*(.+?)\s*-->/',     $content, $m ) ) $info['meta_desc']     = trim( $m[1] );
        if ( preg_match( '/<!-- SLUG:\s*(.+?)\s*-->/',          $content, $m ) ) $info['slug']          = trim( $m[1] );
        if ( preg_match( '/<!-- FOCUS_KEYWORD:\s*(.+?)\s*-->/', $content, $m ) ) $info['focus_keyword'] = trim( $m[1] );
        return $info;
    }

    /* ── SEO 주석 제거 ── */
    private function strip_seo_from_content( $html ) {
        $html = preg_replace( '/<!--\s*(TITLE|META_DESC|SLUG|FOCUS_KEYWORD):[\s\S]*?-->\s*/i', '', $html );
        $html = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>\s*/i', '', $html );
        $html = preg_replace( '/^[ \t]*(TITLE|META_DESC|SLUG|FOCUS_KEYWORD)\s*:.*$/im', '', $html );
        $html = preg_replace( '/\n{3,}/', "\n\n", $html );
        return trim( $html );
    }

    /* ── 첫 <p> 보장 ── */
    private function ensure_description_first( $html, $meta_desc = '', $topic = '' ) {
        $html = trim( $html );
        if ( empty( $html ) ) return $html;
        if ( ! preg_match( '/^\s*<(\w+)[^>]*>/i', $html, $ft ) ) return $html;
        if ( strtolower( $ft[1] ) === 'p' ) return $html;
        if ( in_array( strtolower( $ft[1] ), [ 'h1','h2','h3','h5','h6' ], true ) ) {
            if ( ! empty( $meta_desc ) ) $p = '<p>' . esc_html( $meta_desc ) . '</p>';
            elseif ( ! empty( $topic ) ) $p = '<p>' . esc_html( date('Y') . '년 ' . $topic . '에 대한 핵심 정보를 완벽하게 정리했습니다.' ) . '</p>';
            else $p = '<p>이 글에서 핵심 정보를 안내합니다.</p>';
            return $p . "\n" . $html;
        }
        return $html;
    }

    /* ── 스키마 프롬프트 ── */
    private function build_schema_prompt( $type, $title, $meta_desc, $focus_kw, $content, $post_url, $site_name ) {
        $date    = gmdate( 'c' );
        $excerpt = mb_substr( $content, 0, 3000, 'UTF-8' );
        $common  = "다음 정보로 완벽한 Google Schema.org JSON-LD를 생성하세요.\n순수 JSON만 출력. 마크다운(\`\`\`) 없이.\n\n제목: {$title}\nURL: {$post_url}\n사이트명: {$site_name}\n설명: {$meta_desc}\n키워드: {$focus_kw}\n날짜: {$date}";
        switch ( $type ) {
            case 'article':
                return "{$common}\n\n글 내용:\n{$excerpt}\n\nArticle 스키마 생성. @context, @type(Article), headline, description, datePublished, dateModified, author(@type:Person,name:'블로그 운영자'), publisher(@type:Organization,name,logo(@type:ImageObject,url:'{$post_url}/wp-content/uploads/logo.png')), mainEntityOfPage(@type:WebPage,@id:'{$post_url}'), keywords, articleSection, inLanguage('ko-KR'), wordCount 포함.";
            case 'faq':
                return "{$common}\n\n글 전체 내용:\n{$excerpt}\n\nFAQPage 스키마 생성. 글에서 질문-답변 6~8쌍 추출.\n@context, @type(FAQPage), mainEntity(Question 배열) 구조.\n각 Question: @type(Question), name(질문), acceptedAnswer(@type:Answer, text(답변)).\n질문은 실제 검색어 형태. 글에 FAQ가 없으면 본문에서 추출.";
            case 'product_review':
                return "{$common}\n\n글 내용:\n{$excerpt}\n\nProduct + AggregateRating 스키마 생성.\n@context, @type(Product), name, description, url, aggregateRating(@type:AggregateRating, ratingValue(4.0~5.0), reviewCount(10~100), bestRating:5, worstRating:1), review(@type:Review, reviewRating(@type:Rating,ratingValue,bestRating:5), author(@type:Person,name:'블로그 운영자'), reviewBody(핵심 리뷰 3문장), datePublished).";
            case 'review':
                return "{$common}\n\n글 내용:\n{$excerpt}\n\nReview 스키마 생성. 리뷰 대상(@type:영화면Movie/책이면Book/그외Thing), itemReviewed(name), reviewRating(@type:Rating,ratingValue:4.0~5.0,bestRating:5), author(@type:Person,name:'블로그 운영자'), reviewBody(핵심 요약 3문장), datePublished, publisher(@type:Organization,name:'{$site_name}').";
            case 'howto':
                return "{$common}\n\n글 내용:\n{$excerpt}\n\nHowTo 스키마 생성. @context, @type(HowTo), name, description, totalTime(ISO 8601, 예:PT30M), step(HowToStep 배열).\n각 HowToStep: @type(HowToStep), name, text, url.\n글에서 단계 3~8개 추출. 단계가 없으면 논리적 단계로 구성.";
            case 'breadcrumb':
                return "{$common}\n\nBreadcrumbList 스키마 생성. @context, @type(BreadcrumbList), itemListElement(ListItem 배열).\n각 ListItem: @type(ListItem), position(1~), name, item(URL).\n구조: 홈({$post_url}) → 카테고리(주제 기반 한글명, URL:{$post_url}/category/slug/) → 현재글(제목,URL:{$post_url}).";
            default:
                return "{$common}\n\nArticle 스키마 생성.";
        }
    }

    /* ════════════════════════════════════════════════════════
       SEO 메타태그 자동 출력 (v3.7.0 — 3대 검색엔진 상위노출 최적화)
       — title, meta description, canonical, Open Graph, Twitter Card
       — 네이버·구글·빙 크롤러 완벽 대응
    ════════════════════════════════════════════════════════ */
    public function insert_seo_meta_tags() {
        if ( ! is_singular( 'post' ) ) return;
        global $post;
        if ( ! $post ) return;

        $seo_title   = get_post_meta( $post->ID, '_ai_seo_title',     true );
        $meta_desc   = get_post_meta( $post->ID, '_ai_meta_desc',     true );
        $focus_kw    = get_post_meta( $post->ID, '_ai_focus_keyword', true );
        $slug        = get_post_meta( $post->ID, '_ai_slug',          true );

        // Rank Math / Yoast 활성 시 중복 방지
        if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) return;

        $post_url  = get_permalink( $post->ID );
        $site_name = get_bloginfo( 'name' );
        $thumb_url = get_the_post_thumbnail_url( $post->ID, 'large' ) ?: '';

        // ── 출력 ──
        echo "\n<!-- AIBP Pro SEO Meta v1.0.0 -->\n";

        // 1. Title 태그
        if ( ! empty( $seo_title ) ) {
            echo '<title>' . esc_html( $seo_title ) . ' - ' . esc_html( $site_name ) . "</title>\n";
        }

        // 2. Meta Description
        if ( ! empty( $meta_desc ) ) {
            echo '<meta name="description" content="' . esc_attr( $meta_desc ) . "\">\n";
        }

        // 3. Keywords (네이버 크롤러 대응)
        if ( ! empty( $focus_kw ) ) {
            echo '<meta name="keywords" content="' . esc_attr( $focus_kw ) . "\">\n";
        }

        // 4. Canonical URL (중복 콘텐츠 방지 — 구글/빙 필수)
        echo '<link rel="canonical" href="' . esc_url( $post_url ) . "\">\n";

        // 5. Robots (색인 허용)
        echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";

        // 6. Open Graph (페이스북·카카오·네이버 SNS 공유 최적화)
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $post_url ) . "\">\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . "\">\n";
        if ( ! empty( $seo_title ) ) {
            echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . "\">\n";
        }
        if ( ! empty( $meta_desc ) ) {
            echo '<meta property="og:description" content="' . esc_attr( $meta_desc ) . "\">\n";
        }
        if ( ! empty( $thumb_url ) ) {
            echo '<meta property="og:image" content="' . esc_url( $thumb_url ) . "\">\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
        }
        // Article 발행일
        $pub_date = get_the_date( 'c', $post->ID );
        $mod_date = get_the_modified_date( 'c', $post->ID );
        echo '<meta property="article:published_time" content="' . esc_attr( $pub_date ) . "\">\n";
        echo '<meta property="article:modified_time" content="' . esc_attr( $mod_date ) . "\">\n";

        // 7. Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        if ( ! empty( $seo_title ) ) {
            echo '<meta name="twitter:title" content="' . esc_attr( $seo_title ) . "\">\n";
        }
        if ( ! empty( $meta_desc ) ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $meta_desc ) . "\">\n";
        }
        if ( ! empty( $thumb_url ) ) {
            echo '<meta name="twitter:image" content="' . esc_url( $thumb_url ) . "\">\n";
        }

        // 8. 네이버 블로그 최적화 메타
        echo '<meta name="naver-site-verification" content="">' . "\n"; // 네이버 서치어드바이저 인증 (값은 사용자가 직접 입력)

        echo "<!-- /AIBP Pro SEO Meta -->\n\n";
    }


    public function insert_schema_markup() {
        if ( ! is_singular() ) return;
        global $post;
        if ( ! $post ) return;

        $schemas = $this->decode_schemas( get_post_meta( $post->ID, '_ai_blog_schema_markup', true ) );
        if ( empty( $schemas ) ) return;

        // <script type="application/ld+json"> 태그를 직접 생성해서 출력
        // wp_kses를 거치지 않고 순수 JSON을 사용해 html 손상 방지
        foreach ( $schemas as $s ) {
            if ( empty( $s['json'] ) && empty( $s['html'] ) ) continue;

            if ( ! empty( $s['json'] ) ) {
                // 신규 형식: json 필드에 순수 JSON 배열/객체 저장
                $json_str = is_string( $s['json'] ) ? $s['json'] : wp_json_encode( $s['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                echo '<script type="application/ld+json">' . "
" . $json_str . "
</script>
"; // phpcs:ignore
            } else {
                // 레거시 html 필드
                $html = trim( $s['html'] );
                if ( $html && substr( $html, 0, 7 ) === '<script' ) {
                    echo $html . "
"; // phpcs:ignore
                }
            }
        }
    }



    /* ── AJAX: 템플릿 이미지 직접 업로드 (Fallback) ── */
    public function ajax_upload_template_image() {
        check_ajax_referer( 'aibp_template_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '권한 없음' ] );

        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => '파일 업로드 오류 (코드: ' . ( $_FILES['file']['error'] ?? -1 ) . ')' ] );
        }

        $file = $_FILES['file'];

        // 이미지 파일만 허용
        $allowed_mime = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime  = $finfo->file( $file['tmp_name'] );
        if ( ! in_array( $mime, $allowed_mime ) ) {
            wp_send_json_error( [ 'message' => '이미지 파일만 업로드 가능합니다 (jpg/png/webp/gif).' ] );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // 업로드 처리 (WordPress 표준 방식)
        $overrides = [ 'test_form' => false, 'test_type' => true ];
        $uploaded  = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded['error'] ) ) {
            wp_send_json_error( [ 'message' => '업로드 실패: ' . $uploaded['error'] ] );
        }

        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $uploaded['file'] );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => '미디어 등록 실패: ' . $attachment_id->get_error_message() ] );
        }

        $attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => $uploaded['url'],
            'message'       => '업로드 완료',
        ] );
    }

    /* ── AJAX: 폰트 경로 저장 ── */
    public function ajax_save_font_path() {
        check_ajax_referer( 'aibp_template_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $path = isset( $_POST['font_path'] ) ? sanitize_text_field( $_POST['font_path'] ) : '';
        update_option( 'aibp_thumb_font_path', $path );
        wp_send_json_success();
    }


}

add_action( 'plugins_loaded', function() { AIBP_Pro::get_instance(); } );
