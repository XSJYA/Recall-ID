/**
 * Recall-ID - Admin Scripts 
 */
(function($) {
    'use strict';

    $(function() {
        const ajaxNonce = recall_ajax.nonce;
        
        let allIdsData = [];
        let occupiedData = {};
        let currentPage = 1;
        const perPage = 30;
        let postTypes = [];
        
        function showFeedback(msg, type) {
            // 移除已有的提示框
            $('#recall-feedback').remove();
            
            // 创建新的提示框（无关闭按钮，悬浮底部中间）
            const notice = $('<div id="recall-feedback" class="recall-notice notice-' + type + '"></div>');
            notice.html('<p>' + msg + '</p>');
            
            // 直接添加到 body
            $('body').append(notice);
            notice.hide().fadeIn(300);
            
            // 4秒后自动消失
            setTimeout(function() {
                if (notice.is(':visible')) {
                    notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            }, 4000);
        }

        // 获取文章类型列表
        function loadPostTypes() {
            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_get_post_types', nonce: ajaxNonce },
                success: function(res) {
                    if (res.success) {
                        postTypes = res.data;
                    }
                }
            });
        }
        loadPostTypes();

        function renderPage(page) {
            const start = (page - 1) * perPage;
            const end = start + perPage;
            const pageIds = allIdsData.slice(start, end);
            
            let html = '';
            if (pageIds.length === 0) {
                html = '<div class="recall-loading">暂无数据</div>';
            } else {
                pageIds.forEach(function(id) {
                    if (occupiedData[id]) {
                        html += '<div class="recall-id-chip occupied" data-id="' + id + '">';
                        html += '<span class="recall-id-num">' + id + '</span>';
                        html += '</div>';
                    } else {
                        html += '<div class="recall-id-chip available" data-id="' + id + '">';
                        html += '<span class="recall-id-num">' + id + '</span>';
                        html += '</div>';
                    }
                });
            }
            $('#recall-id-list').html(html);
            
            const totalPages = Math.ceil(allIdsData.length / perPage);
            $('#recall-current-page').text(page);
            $('#recall-total-pages').text(totalPages || 1);
            
            // 只有超过1页才显示分页按钮
            if (totalPages > 1) {
                $('#recall-pagination').show();
                $('#recall-prev').prop('disabled', page === 1);
                $('#recall-next').prop('disabled', page === totalPages);
            } else {
                $('#recall-pagination').hide();
            }
        }

        function triggerScan() {
            const btn = $('#recall-scan-btn');
            const list = $('#recall-id-list');
            
            btn.prop('disabled', true).addClass('spinning');
            list.html('<div class="recall-loading"><div class="recall-spinner"></div><p>正在扫描ID...</p></div>');

            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_scan_ids', nonce: ajaxNonce },
                success: function(res) {
                    if (res.success) {
                        allIdsData = res.data.all_ids || [];
                        occupiedData = res.data.occupied || {};
                        currentPage = 1;
                        renderPage(1);
                        
                        const avail = allIdsData.length - Object.keys(occupiedData).length;
                        const used = Object.keys(occupiedData).length;
                        $('#recall-avail-count').text(avail);
                        $('#recall-used-count').text(used);
                        $('#recall-scan-result').show();
                        
                        // 更新侧边栏可用ID
                        $('.recall-stat-box.highlight .recall-stat-num').text(avail);
                        
                        setTimeout(function() { 
                            showFeedback('扫描完成：' + avail + '个可用，' + used + '个已占用', 'success'); 
                        }, 300);
                    } else {
                        list.html('<div class="recall-loading">' + res.data.message + '</div>');
                    }
                },
                error: function() { 
                    list.html('<div class="recall-loading">请求失败</div>'); 
                },
                complete: function() { 
                    btn.prop('disabled', false).removeClass('spinning'); 
                }
            });
        }

        // 自动扫描
        $(document).ready(function() {
            if ($('#recall-id-list').data('auto-scan')) {
                setTimeout(triggerScan, 300);
            }
        });

        $('#recall-scan-btn').on('click', function(e) { e.preventDefault(); triggerScan(); });
        
        $('#recall-prev').on('click', function() { 
            if (currentPage > 1) { 
                currentPage--; 
                renderPage(currentPage); 
            } 
        });
        
        $('#recall-next').on('click', function() { 
            const totalPages = Math.ceil(allIdsData.length / perPage);
            if (currentPage < totalPages) { 
                currentPage++; 
                renderPage(currentPage); 
            } 
        });

        // 点击ID
        $('#recall-id-list').on('click', '.recall-id-chip', function() {
            const id = $(this).data('id');
            const isOccupied = $(this).hasClass('occupied');
            
            if (!isOccupied) {
                showCreateConfirm(id);
            } else {
                showDetail(id);
            }
        });

        // 创建确认弹窗
        function showCreateConfirm(id) {
            $('#recall-modal-title').text('创建ID:' + id + '草稿');
            let html = '<div class="recall-create-modal">';
            html += '<p class="recall-create-desc">选择要创建的内容类型：</p>';
            
            const types = [
                { value: 'post', label: '文章', icon: '📄' },
                { value: 'page', label: '页面', icon: '📃' }
            ];
            
            postTypes.forEach(function(t) {
                types.push({ value: t.name, label: t.label, icon: '📋' });
            });
            
            types.forEach(function(t, i) {
                html += '<label class="recall-create-option">';
                html += '<input type="radio" name="create_type" value="' + t.value + '"' + (i === 0 ? ' checked' : '') + '>';
                html += '<span class="recall-create-option-inner">' + t.icon + ' ' + t.label + '</span>';
                html += '</label>';
            });
            
            html += '</div>';
            html += '<div class="recall-create-actions">';
            html += '<button class="recall-create-cancel" id="create-cancel-btn">取消</button>';
            html += '<button class="recall-create-confirm" id="create-confirm-btn">确认创建</button>';
            html += '</div>';
            
            $('#recall-detail-modal').fadeIn(200);
            $('#recall-detail-content').html(html);
            
            $('#create-cancel-btn').on('click', function() {
                $('#recall-detail-modal').fadeOut(200);
            });
            
            $('#create-confirm-btn').on('click', function() {
                const selectedType = $('input[name="create_type"]:checked').val();
                $('#recall-detail-modal').fadeOut(200);
                createPost(id, selectedType);
            });
        }

        function createPost(id, postType) {
            if (!postType) postType = 'post';
            
            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_create_post', id: id, post_type: postType, nonce: ajaxNonce },
                success: function(res) {
                    if (res.success) {
                        showFeedback(res.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        showFeedback(res.data.message, 'error');
                    }
                },
                error: function() { showFeedback('请求失败', 'error'); }
            });
        }

        function showDetail(id) {
            $('#recall-modal-title').text('ID:' + id + '占用详情');
            const modal = $('#recall-detail-modal');
            const content = $('#recall-detail-content');
            modal.fadeIn(200);
            content.html('<div class="recall-loading"><div class="recall-spinner"></div></div>');
            
            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_check_id', id: id, nonce: ajaxNonce },
                success: function(res) {
                    if (res.success && res.data.data) {
                        let html = '';
                        res.data.data.forEach(function(item) {
                            const statusClass = 'status-' + (item.status === 'publish' ? 'publish' : (item.status === 'draft' ? 'draft' : (item.status === 'trash' ? 'trash' : 'publish')));
                            html += '<div class="recall-detail-item"><div class="recall-detail-label">标题</div><div class="recall-detail-value">' + escHtml(item.title) + '</div></div>';
                            html += '<div class="recall-detail-item"><div class="recall-detail-label">类型</div><div class="recall-detail-value"><span class="badge type">' + escHtml(item.type_name) + '</span></div></div>';
                            html += '<div class="recall-detail-item"><div class="recall-detail-label">状态</div><div class="recall-detail-value"><span class="badge ' + statusClass + '">' + escHtml(item.status_name) + '</span>';
                            if (item.view_link && item.status === 'publish') {
                                html += ' <a href="' + item.view_link + '" target="_blank" class="recall-view-link">查看</a>';
                            }
                            html += '</div></div>';
                            html += '<div class="recall-detail-item"><div class="recall-detail-label">日期</div><div class="recall-detail-value">' + escHtml(item.date) + '</div></div>';
                        });
                        content.html(html);
                    } else {
                        content.html('<div class="recall-loading">该ID未被占用</div>');
                    }
                },
                error: function() { content.html('<div class="recall-loading">请求失败</div>'); }
            });
        }

        // 关闭弹窗
        $('.recall-modal-close, .recall-modal').on('click', function(e) {
            if (e.target === $('.recall-modal')[0] || $(this).hasClass('recall-modal-close')) {
                $('#recall-detail-modal').fadeOut(200);
            }
        });
        $(document).on('keydown', function(e) { if (e.key === 'Escape') $('#recall-detail-modal').fadeOut(200); });

        // 清理数据库 - 增强版确认机制
        $('.recall-cleanup-btn').on('click', function() {
            const btn = $(this);
            const action = btn.data('action');
            
            // 第一步：预览数据
            btn.prop('disabled', true).text('预览中...');
            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_preview_cleanup', cleanup_action: action, nonce: ajaxNonce },
                success: function(previewRes) {
                    btn.prop('disabled', false).text('清理');
                    if (previewRes.success) {
                        const count = previewRes.data.count;
                        const sampleData = previewRes.data.sample_data;
                        
                        // 如果没有数据，直接提示
                        if (count === 0) {
                            showFeedback('没有找到可清理的数据', 'error');
                            return;
                        }
                        
                        // 构建预览信息
                        let previewHtml = '<div class="recall-preview-modal">';
                        previewHtml += '<h4>数据预览</h4>';
                        previewHtml += '<p class="recall-preview-summary">将要清理的数据：<strong>' + count + ' 条</strong></p>';
                        
                        // 添加示例数据
                        if (sampleData && sampleData.length > 0) {
                            previewHtml += '<div class="recall-preview-sample">';
                            previewHtml += '<h5>示例数据（前5条）：</h5>';
                            previewHtml += '<ul class="recall-preview-list">';
                            sampleData.forEach(function(item) {
                                previewHtml += '<li>';
                                if (item.post_title) {
                                    previewHtml += '<strong>标题：</strong>' + escHtml(item.post_title);
                                } else {
                                    previewHtml += '<strong>ID：</strong>' + item.ID;
                                }
                                if (item.post_modified || item.post_date) {
                                    previewHtml += ' <small>' + (item.post_modified || item.post_date) + '</small>';
                                }
                                previewHtml += '</li>';
                            });
                            previewHtml += '</ul>';
                            previewHtml += '</div>';
                        }
                        
                        // 添加严重警告
                        previewHtml += '<div class="recall-preview-warning">';
                        previewHtml += '<h5>⚠️ 严重警告</h5>';
                        previewHtml += '<ul>';
                        if (action === 'revisions') {
                            previewHtml += '<li>删除文章修订版会丢失所有历史版本</li>';
                            previewHtml += '<li>无法恢复文章的历史编辑记录</li>';
                            previewHtml += '<li>清理操作不可撤销</li>';
                        } else if (action === 'trashed') {
                            previewHtml += '<li>永久删除回收站内容，无法恢复</li>';
                            previewHtml += '<li>可能丢失重要文章的备份</li>';
                            previewHtml += '<li>操作不可撤销</li>';
                        } else if (action === 'autodrafts') {
                            previewHtml += '<li>删除自动草稿会清除未保存的工作</li>';
                            previewHtml += '<li>如果你有正在编辑的文章，请先保存</li>';
                        }
                        previewHtml += '<li>最多删除 1000 条数据（安全限制）</li>';
                        previewHtml += '</ul>';
                        previewHtml += '</div>';
                        
                        previewHtml += '<div class="recall-preview-actions">';
                        previewHtml += '<button class="recall-cancel-btn" id="preview-cancel-btn">取消</button>';
                        previewHtml += '<button class="recall-danger-btn" id="preview-confirm-btn">确认清理</button>';
                        previewHtml += '</div>';
                        
                        previewHtml += '</div>';
                        
                        // 显示预览弹窗
                        $('#recall-modal-title').text('清理确认 - ' + getActionName(action));
                        $('#recall-detail-modal').fadeIn(200);
                        $('#recall-detail-content').html(previewHtml);
                        
                        // 绑定按钮事件
                        $('#preview-cancel-btn').on('click', function() {
                            $('#recall-detail-modal').fadeOut(200);
                        });
                        
                        $('#preview-confirm-btn').on('click', function() {
                            $('#recall-detail-modal').fadeOut(200);
                            performCleanup(action);
                        });
                    } else {
                        showFeedback(previewRes.data.message, 'error');
                    }
                },
                error: function() { showFeedback('预览请求失败', 'error'); btn.prop('disabled', false).text('清理'); }
            });
        });

        // 执行清理操作
        function performCleanup(action) {
            const btn = $('.recall-cleanup-btn[data-action="' + action + '"]');
            btn.prop('disabled', true).text('处理中...');
            
            $.ajax({
                url: recall_ajax.ajax_url,
                type: 'POST',
                data: { action: 'recall_cleanup_db', cleanup_action: action, nonce: ajaxNonce },
                success: function(res) {
                    if (res.success) {
                        showFeedback(res.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        showFeedback(res.data.message, 'error');
                        btn.prop('disabled', false).text('清理');
                    }
                },
                error: function() { showFeedback('请求失败', 'error'); btn.prop('disabled', false).text('清理'); }
            });
        }

        // 获取操作名称
        function getActionName(action) {
            const names = {
                'revisions': '文章修订版',
                'autodrafts': '自动草稿',
                'trashed': '回收站'
            };
            return names[action] || action;
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    });
})(jQuery);
