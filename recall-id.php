<?php
/**
 * Plugin Name:       Recall-ID 
 * Plugin URI:        https://github.com/XSJYA/Recall-ID
 * Description:       Reuse deleted post ID – bring back lost ID.
 * Version:           1.1.3
 * Author:            XSJYA
 * Author URI:        https://www.xsjya.com/
 * License:           GPL v2 or later
 * Text Domain:       recall-id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SGP_Recall_ID_Enhanced' ) ) {

    class SGP_Recall_ID_Enhanced {

        const VERSION = '1.1.3';
        const MAX_UNUSED_IDS = 100;
        const MAX_DELETE_LIMIT = 1000; // 最大删除限制
        private $mysql_version = null;

        public function __construct() {
            add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            add_action( 'wp_ajax_recall_create_post', [ $this, 'ajax_create_post' ] );
            add_action( 'wp_ajax_recall_preview_cleanup', [ $this, 'ajax_preview_cleanup' ] );
            add_action( 'wp_ajax_recall_cleanup_db', [ $this, 'ajax_cleanup_db' ] );
            add_action( 'wp_ajax_recall_scan_ids', [ $this, 'ajax_scan_ids' ] );
            add_action( 'wp_ajax_recall_check_id', [ $this, 'ajax_check_id' ] );
            add_action( 'wp_ajax_recall_get_post_types', [ $this, 'ajax_get_post_types' ] );
            
            // 添加插件列表页的"设置"链接
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ] );
        }
        
        /**
         * 在插件列表页添加"设置"链接（放在禁用按钮后面）
         */
        public function add_settings_link( $links ) {
            $settings_link = '<a href="tools.php?page=recall-id">设置</a>';
            $links[] = $settings_link;
            return $links;
        }

        public function add_admin_menu() {
            add_management_page(
                'Recall-ID',
                'Recall-ID',
                'manage_options',
                'recall-id',
                [ $this, 'render_options_page' ]
            );
        }

        public function render_options_page() {
            global $wpdb;

            $revisions_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
            $autodrafts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
            $trashed_posts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
            $total_posts = wp_count_posts()->publish;
            
            // 获取自定义文章类型
            $custom_types = get_post_types( [ '_builtin' => false, 'public' => true ], 'objects' );
            ?>
            <div class="wrap recall-id-wrap">
                <div class="recall-header">
                    <div class="recall-header-left">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <div>
                            <h1>Recall-ID</h1>
                            <p>找回丢失的文章ID，释放数据库空间。</p>
                        </div>
                    </div>
                    <button class="recall-refresh-btn" id="recall-scan-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        <span>重新扫描</span>
                    </button>
                </div>

                <div id="recall-feedback" class="recall-notice" style="display:none;"></div>

                <div class="recall-dashboard">
                    <div class="recall-main">
                        <div class="recall-card">
                            <div class="recall-card-header">
                                <div class="recall-card-title-row">
                                    <div class="recall-card-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                        <span>ID列表</span>
                                    </div>
                                </div>
                            </div>
                            <div class="recall-id-grid" id="recall-id-list" data-auto-scan="true">
                                <div class="recall-loading"><div class="recall-spinner"></div><p>正在扫描ID...</p></div>
                            </div>
                            <div class="recall-pagination" id="recall-pagination" style="display:none;">
                                <button class="recall-page-btn" id="recall-prev" disabled><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
                                <span class="recall-page-info"><span id="recall-current-page">1</span> / <span id="recall-total-pages">1</span></span>
                                <button class="recall-page-btn" id="recall-next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
                            </div>
                            <div class="recall-scan-result" id="recall-scan-result" style="display:none;">
                                <span class="recall-avail"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> <strong id="recall-avail-count">0</strong> 可用</span>
                                <span class="recall-used"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg> <strong id="recall-used-count">0</strong> 已占用</span>
                            </div>
                        </div>

                        <div class="recall-card">
                            <div class="recall-card-header">
                                <div class="recall-card-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                    <span>数据库优化</span>
                                </div>
                            </div>
                            <p class="recall-cleanup-desc">清理数据库中的冗余数据，释放空间（操作不可逆，请谨慎操作）</p>
                            <div class="recall-cleanup-list">
                                <div class="recall-cleanup-item">
                                    <div class="recall-cleanup-info">
                                        <span class="recall-cleanup-label">文章修订版</span>
                                        <span class="recall-cleanup-count"><?php echo number_format_i18n( $revisions_count ); ?> 条 <small>历史版本的快照，会占用数据库空间</small></span>
                                    </div>
                                    <button class="recall-cleanup-btn" data-action="revisions" data-confirm="确定要删除所有文章修订版本吗？此操作不可恢复！" <?php disabled( $revisions_count, 0 ); ?>>清理</button>
                                </div>
                                <div class="recall-cleanup-item">
                                    <div class="recall-cleanup-info">
                                        <span class="recall-cleanup-label">自动草稿</span>
                                        <span class="recall-cleanup-count"><?php echo number_format_i18n( $autodrafts_count ); ?> 条 <small>点击新建文章时自动创建的空白草稿</small></span>
                                    </div>
                                    <button class="recall-cleanup-btn" data-action="autodrafts" data-confirm="确定要删除所有自动草稿吗？" <?php disabled( $autodrafts_count, 0 ); ?>>清理</button>
                                </div>
                                <div class="recall-cleanup-item">
                                    <div class="recall-cleanup-info">
                                        <span class="recall-cleanup-label">回收站</span>
                                        <span class="recall-cleanup-count"><?php echo number_format_i18n( $trashed_posts_count ); ?> 条 <small>已删除的文章，仍然占用数据库空间</small></span>
                                    </div>
                                    <button class="recall-cleanup-btn" data-action="trashed" data-confirm="确定要永久清空回收站吗？此操作不可恢复！" <?php disabled( $trashed_posts_count, 0 ); ?>>清理</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="recall-sidebar">
                        <div class="recall-stats-card">
                            <h3>数据概览</h3>
                            <div class="recall-stats-grid">
                                <div class="recall-stat-box">
                                    <span class="recall-stat-num"><?php echo number_format_i18n( $total_posts ); ?></span>
                                    <span class="recall-stat-label">已发布</span>
                                </div>
                                <div class="recall-stat-box">
                                    <span class="recall-stat-num"><?php echo number_format_i18n( $revisions_count ); ?></span>
                                    <span class="recall-stat-label">修订版</span>
                                </div>
                                <div class="recall-stat-box">
                                    <span class="recall-stat-num"><?php echo number_format_i18n( $trashed_posts_count ); ?></span>
                                    <span class="recall-stat-label">回收站</span>
                                </div>
                                <div class="recall-stat-box highlight">
                                    <span class="recall-stat-num">-</span>
                                    <span class="recall-stat-label">可用ID</span>
                                </div>
                            </div>
                        </div>

                        <div class="recall-guide-card">
                            <h4><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> 使用指南</h4>
                            <div class="recall-guide-list">
                                <div class="recall-guide-item">
                                    <span class="recall-guide-icon green"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                    <div><strong>绿色 = 可用</strong><p>该ID未被使用，点击可创建新内容</p></div>
                                </div>
                                <div class="recall-guide-item">
                                    <span class="recall-guide-icon red"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg></span>
                                    <div><strong>红色 = 已占用</strong><p>该ID已被内容使用，点击查看详情</p></div>
                                </div>
                            </div>
                        </div>

                        <div class="recall-tip-card">
                            <h4><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> 为什么ID会空缺？</h4>
                            <ul>
                                <li>删除文章后ID不会复用</li>
                                <li>WordPress的ID是递增的</li>
                                <li>可以用旧ID创建新文章</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div id="recall-detail-modal" class="recall-modal" style="display:none;">
                <div class="recall-modal-content">
                    <div class="recall-modal-header">
                        <h3 id="recall-modal-title">ID占用详情</h3>
                        <button class="recall-modal-close">&times;</button>
                    </div>
                    <div class="recall-modal-body" id="recall-detail-content"></div>
                </div>
            </div>
            <?php
        }

        public function enqueue_admin_scripts( $hook ) {
            if ( 'tools_page_recall-id' !== $hook ) {
                return;
            }
            wp_enqueue_style( 'recall-id-css', plugins_url( 'assets/recall.css', __FILE__ ), [], self::VERSION );
            wp_enqueue_script( 'recall-id-js', plugins_url( 'assets/recall.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
            wp_localize_script( 'recall-id-js', 'recall_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'recall_ajax_nonce' )
            ] );
        }

        private function get_unused_ids() {
            global $wpdb;
            
            // 获取最大ID
            $max_id = $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
            if ( ! $max_id || $max_id < 2 ) {
                return [];
            }
            $max_id = intval( $max_id );
            
            // 限制最大ID，避免性能问题
            $max_id = min( $max_id, apply_filters( 'recall_id_max_scan', 5000 ) );
            
            // 使用更高效的方法获取未使用的ID
            $used_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ID BETWEEN 1 AND {$max_id} ORDER BY ID" );
            $used_set = array_flip( $used_ids );
            
            $unused = [];
            for ( $i = 1; $i <= $max_id && count( $unused ) < self::MAX_UNUSED_IDS; $i++ ) {
                if ( ! isset( $used_set[$i] ) ) {
                    $unused[] = $i;
                }
            }
            
            return $unused;
        }

        public function ajax_scan_ids() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'publish_posts' ) ) {
                wp_send_json_error( [ 'message' => '权限不足' ] );
            }

            global $wpdb;
            
            // 获取最大ID
            $max_id = $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
            if ( ! $max_id ) {
                $max_id = 100;
            }
            $max_id = intval( $max_id );
            
            // 限制最大扫描ID，避免性能问题（最多扫描10000个ID）
            $scan_limit = apply_filters( 'recall_id_scan_limit', 10000 );
            if ( $max_id > $scan_limit ) {
                $max_id = $scan_limit;
            }

            // 分批获取已存在的ID，避免内存溢出
            $existing = [];
            $chunk_size = 1000;
            $offset = 1;
            
            while ( $offset <= $max_id ) {
                $end = min( $offset + $chunk_size - 1, $max_id );
                $results = $wpdb->get_col( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d",
                    $offset,
                    $end
                ) );
                foreach ( $results as $id ) {
                    $existing[$id] = true;
                }
                $offset += $chunk_size;
            }

            // 生成ID列表
            $all_ids = range( 1, $max_id );

            // 获取占用详情（分批查询）
            $occupied = [];
            $offset = 1;
            while ( $offset <= $max_id ) {
                $end = min( $offset + $chunk_size - 1, $max_id );
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d",
                    $offset,
                    $end
                ) );
                foreach ( $results as $post ) {
                    $type_obj = get_post_type_object( $post->post_type );
                    $occupied[$post->ID] = [
                        'id' => $post->ID,
                        'title' => $post->post_title ?: '(无标题)',
                        'type' => $post->post_type,
                        'type_name' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
                        'status' => $post->post_status,
                        'view_link' => $post->post_status === 'publish' ? get_permalink( $post->ID ) : ''
                    ];
                }
                $offset += $chunk_size;
            }

            wp_send_json_success( [
                'all_ids' => $all_ids,
                'occupied' => $occupied,
                'max_id' => $max_id
            ] );
        }

        public function ajax_check_id() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'publish_posts' ) ) {
                wp_send_json_error( [ 'message' => '权限不足' ] );
            }

            $post_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            if ( $post_id <= 0 ) {
                wp_send_json_error( [ 'message' => '无效的ID' ] );
            }

            global $wpdb;
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status, post_date, post_author FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

            if ( empty( $results ) ) {
                wp_send_json_success( [ 'message' => '该ID未被占用', 'data' => null ] );
            }

            $data = [];
            foreach ( $results as $post ) {
                $type_obj = get_post_type_object( $post->post_type );
                $status_names = [ 'publish' => '已发布', 'draft' => '草稿', 'pending' => '待审核', 'trash' => '回收站', 'private' => '私密', 'auto-draft' => '自动草稿' ];
                $data[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title ?: '(无标题)',
                    'type_name' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
                    'status_name' => $status_names[ $post->post_status ] ?? $post->post_status,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'view_link' => $post->post_status === 'publish' ? get_permalink( $post->ID ) : ''
                ];
            }

            wp_send_json_success( [ 'message' => '该ID被占用', 'data' => $data ] );
        }

        public function ajax_create_post() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'publish_posts' ) ) {
                wp_send_json_error( [ 'message' => '权限不足' ] );
            }

            $post_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            $post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';

            if ( $post_id <= 0 || ! post_type_exists( $post_type ) ) {
                wp_send_json_error( [ 'message' => '参数无效' ] );
            }

            global $wpdb;
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
            if ( $exists ) {
                wp_send_json_error( [ 'message' => '该ID已被占用' ] );
            }

            $now = current_time( 'mysql' );
            $type_obj = get_post_type_object( $post_type );
            $type_name = $type_obj ? $type_obj->labels->singular_name : $post_type;

            $result = $wpdb->insert( $wpdb->posts, [
                'ID' => $post_id,
                'post_author' => get_current_user_id(),
                'post_date' => $now,
                'post_date_gmt' => get_gmt_from_date( $now ),
                'post_title' => 'id' . $post_id,
                'post_status' => 'draft',
                'post_name' => $post_id,
                'post_modified' => $now,
                'post_modified_gmt' => get_gmt_from_date( $now ),
                'post_type' => $post_type,
                'to_ping' => '',
                'pinged' => '',
                'ping_status' => 'closed',
                'comment_status' => 'closed',
            ] );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => '创建失败' ] );
            }

            $edit_link = get_edit_post_link( $post_id, '' );
            wp_send_json_success( [ 'message' => "成功创建{$type_name}草稿 ID:{$post_id}", 'edit_link' => $edit_link ] );
        }

        /**
         * 清理数据库前的预览
         */
        public function ajax_preview_cleanup() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => '权限不足' ] );
            }

            global $wpdb;
            $action = isset( $_POST['cleanup_action'] ) ? sanitize_key( $_POST['cleanup_action'] ) : '';

            $count = 0;
            $sample_data = [];
            switch ( $action ) {
                case 'revisions': 
                    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
                    // 预览少量修订版信息
                    $sample_data = $wpdb->get_results( "SELECT ID, post_title, post_modified FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 5" );
                    break;
                case 'autodrafts': 
                    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
                    $sample_data = $wpdb->get_results( "SELECT ID, post_date FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 5" );
                    break;
                case 'trashed': 
                    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
                    $sample_data = $wpdb->get_results( "SELECT ID, post_title, post_modified FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 5" );
                    break;
                default: wp_send_json_error( [ 'message' => '无效操作' ] ); return;
            }

            wp_send_json_success( [
                'count' => $count,
                'sample_data' => $sample_data,
                'action_name' => $action
            ] );
        }

        /**
         * 清理数据库
         */
        public function ajax_cleanup_db() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => '权限不足' ] );
            }

            global $wpdb;
            $action = isset( $_POST['cleanup_action'] ) ? sanitize_key( $_POST['cleanup_action'] ) : '';
            $force = isset( $_POST['force'] ) ? boolval( $_POST['force'] ) : false;

            // 最大删除数量限制
            $max_delete_limit = self::MAX_DELETE_LIMIT;
            
            $query = '';
            switch ( $action ) {
                case 'revisions': 
                    $query = "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT {$max_delete_limit}"; 
                    break;
                case 'autodrafts': 
                    $query = "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT {$max_delete_limit}"; 
                    break;
                case 'trashed': 
                    $query = "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT {$max_delete_limit}"; 
                    break;
                default: wp_send_json_error( [ 'message' => '无效操作' ] ); return;
            }

            $deleted = $wpdb->query( $query );
            if ( $deleted === false ) {
                wp_send_json_error( [ 'message' => '清理失败' ] );
            }

            // 清理修订版后删除关联的元数据
            if ( $deleted > 0 && $action === 'revisions' ) {
                $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL LIMIT {$max_delete_limit}" );
            }

            wp_send_json_success( [ 
                'message' => "清理成功！共删除 {$deleted} 条数据（最多 {$max_delete_limit} 条）", 
                'deleted' => $deleted,
                'limit' => $max_delete_limit
            ] );
        }

        public function ajax_get_post_types() {
            check_ajax_referer( 'recall_ajax_nonce', 'nonce' );
            
            $types = get_post_types( [ '_builtin' => false, 'public' => true ], 'objects' );
            $result = [];
            foreach ( $types as $type ) {
                $result[] = [ 'name' => $type->name, 'label' => $type->labels->singular_name ];
            }
            wp_send_json_success( $result );
        }
    }

    new SGP_Recall_ID_Enhanced();
}
