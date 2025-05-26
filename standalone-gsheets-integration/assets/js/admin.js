/**
 * اسکریپت بخش مدیریت پلاگین - نسخه 2.0 با API v4 (بهبود یافته)
 */
(function($) {
    'use strict';
    
    let healthCheckInterval;
    let connectionTestTimeout;
    let currentRequest = null;
    
    $(document).ready(function() {
        initializeAdminInterface();
        bindEventHandlers();
        startPeriodicHealthCheck();
    });
    
    /**
     * مقداردهی اولیه رابط مدیریت
     */
    function initializeAdminInterface() {
        // اضافه کردن بج نسخه API
        if ($('.wrap h1').length && !$('.gsheet-api-badge').length) {
            $('.wrap h1').append(' <span class="gsheet-api-badge">API v4</span>');
        }
        
        // اضافه کردن tooltip ها
        addTooltips();
        
        // بررسی اولیه وضعیت اتصال
        checkConnectionStatus();
        
        // نمایش پیام‌های راهنما
        showContextualHelp();
        
        // مقداردهی تب‌ها اگر وجود دارند
        if ($('.nav-tab-wrapper').length) {
            initializeTabs();
        }
    }
    
    /**
     * اتصال event handler ها
     */
    function bindEventHandlers() {
        // تست اتصال
        $('#test-connection-btn').on('click', handleConnectionTest);
        
        // پاک کردن کش
        $('#clear-cache-btn').on('click', handleClearCache);
        
        // بررسی سلامت
        $('#run-health-check-btn').on('click', handleHealthCheck);
        
        // صدور و وارد کردن تنظیمات
        $('#export-settings-btn').on('click', handleExportSettings);
        $('#import-settings-btn').on('click', handleImportSettings);
        
        // کپی شورت‌کد
        $(document).on('click', '.copy-shortcode', handleCopyShortcode);
        $(document).on('click', '.quick-shortcodes code', handleQuickShortcodeCopy);
        
        // اعتبارسنجی فرم
        $('form').on('submit', validateForm);
        
        // بررسی فایل اعتبارنامه
        $('input[name="credentials_file"]').on('change', validateCredentialsFile);
        
        // پیش‌نمایش آی‌دی اسپردشیت
        $('input[name="standalone_gsheets_settings[spreadsheet_id]"]').on('input', previewSpreadsheetId);
        
        // مدیریت تنظیمات پیشرفته
        $('.advanced-toggle').on('click', toggleAdvancedSettings);
        
        // Auto-save تنظیمات
        $('.auto-save').on('change', autoSaveSettings);
        
        // افزودن انیمیشن‌های تعاملی
        addInteractiveAnimations();
    }
    
    /**
     * تست اتصال به گوگل شیت
     */
    function handleConnectionTest() {
        const $button = $('#test-connection-btn');
        const $result = $('#test-result');
        const spreadsheet_id = $('#test-spreadsheet-id').val() || $('input[name="standalone_gsheets_settings[spreadsheet_id]"]').val();
        const originalText = $button.text();
        
        // لغو درخواست قبلی اگر وجود دارد
        if (currentRequest) {
            currentRequest.abort();
        }
        
        // جلوگیری از درخواست‌های متعدد
        if ($button.prop('disabled')) {
            return;
        }
        
        $button.prop('disabled', true);
        $button.html(standalone_gsheets_admin.strings.testing + ' <span class="loading-spinner"></span>');
        $result.html('<p class="testing-message">' + standalone_gsheets_admin.strings.testing + '</p>').show();
        
        // تنظیم timeout
        connectionTestTimeout = setTimeout(() => {
            $button.prop('disabled', false);
            $button.text(originalText);
            $result.html('<div class="notice notice-error"><p>تست اتصال خیلی طول کشید. لطفاً دوباره تلاش کنید.</p></div>');
            if (currentRequest) {
                currentRequest.abort();
            }
        }, 30000);
        
        currentRequest = $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_test_connection',
                nonce: standalone_gsheets_admin.nonce,
                spreadsheet_id: spreadsheet_id
            },
            success: function(response) {
                clearTimeout(connectionTestTimeout);
                $button.prop('disabled', false);
                $button.text(originalText);
                currentRequest = null;
                
                if (response.success) {
                    let html = '<div class="notice notice-success" style="padding: 15px; margin: 0;">';
                    html += '<p><strong>✅ ' + standalone_gsheets_admin.strings.success + '</strong></p>';
                    html += '<p>' + (response.data.message || 'اتصال موفق بود') + '</p>';
                    
                    if (response.data.spreadsheet_info) {
                        html += '<div class="connection-details">';
                        html += '<h4>📊 اطلاعات اسپردشیت:</h4>';
                        html += '<ul>';
                        html += '<li><strong>عنوان:</strong> ' + escapeHtml(response.data.spreadsheet_info.title || 'نامشخص') + '</li>';
                        if (response.data.spreadsheet_info.locale) {
                            html += '<li><strong>منطقه:</strong> ' + escapeHtml(response.data.spreadsheet_info.locale) + '</li>';
                        }
                        if (response.data.spreadsheet_info.timezone) {
                            html += '<li><strong>منطقه زمانی:</strong> ' + escapeHtml(response.data.spreadsheet_info.timezone) + '</li>';
                        }
                        html += '<li><strong>تعداد شیت‌ها:</strong> ' + (response.data.total_sheets || 0) + '</li>';
                        if (response.data.visible_sheets && response.data.visible_sheets !== response.data.total_sheets) {
                            html += '<li><strong>شیت‌های قابل مشاهده:</strong> ' + response.data.visible_sheets + '</li>';
                        }
                        html += '</ul>';
                        html += '</div>';
                        
                        if (response.data.sheets && response.data.sheets.length > 0) {
                            html += '<div class="sheet-list">';
                            html += '<h4>📋 لیست شیت‌ها:</h4>';
                            html += '<div class="sheet-tags">';
                            response.data.sheets.forEach(function(sheet) {
                                html += '<span class="sheet-tag">' + escapeHtml(sheet) + '</span>';
                            });
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        if (response.data.features) {
                            html += '<div class="api-features">';
                            html += '<h4>🚀 قابلیت‌های API v4:</h4>';
                            html += '<ul class="feature-list">';
                            Object.keys(response.data.features).forEach(function(feature) {
                                if (response.data.features[feature]) {
                                    const featureName = feature.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                    html += '<li>✅ ' + featureName + '</li>';
                                }
                            });
                            html += '</ul>';
                            html += '</div>';
                        }
                        
                        if (response.data.performance) {
                            html += '<div class="performance-stats">';
                            html += '<h4>📈 آمار عملکرد:</h4>';
                            html += '<ul>';
                            Object.keys(response.data.performance).forEach(function(key) {
                                const keyName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                html += '<li><strong>' + keyName + ':</strong> ' + response.data.performance[key] + '</li>';
                            });
                            html += '</ul>';
                            html += '</div>';
                        }
                    }
                    
                    html += '</div>';
                    $result.html(html);
                    
                    // نمایش پیام موفقیت موقت
                    showTemporaryMessage('اتصال API v4 موفق بود!', 'success');
                    
                } else {
                    let errorMessage = response.data || 'خطای نامشخص';
                    let html = '<div class="notice notice-error" style="padding: 15px; margin: 0;">';
                    html += '<p><strong>❌ ' + standalone_gsheets_admin.strings.error + '</strong></p>';
                    html += '<p>' + escapeHtml(errorMessage) + '</p>';
                    
                    // اضافه کردن راهنمای رفع مشکل
                    html += '<div class="troubleshooting">';
                    html += '<h4>🔧 راهنمای رفع مشکل:</h4>';
                    html += '<ul>';
                    html += '<li>بررسی کنید که فایل اعتبارنامه صحیح آپلود شده باشد</li>';
                    html += '<li>آی‌دی اسپردشیت را بررسی کنید (باید 44 کاراکتر باشد)</li>';
                    html += '<li>مطمئن شوید Service Account دسترسی به اسپردشیت دارد</li>';
                    html += '<li>بررسی کنید که Google Sheets API در پروژه گوگل فعال باشد</li>';
                    html += '</ul>';
                    html += '</div>';
                    
                    html += '</div>';
                    $result.html(html);
                }
            },
            error: function(xhr, status, error) {
                clearTimeout(connectionTestTimeout);
                $button.prop('disabled', false);
                $button.text(originalText);
                currentRequest = null;
                
                if (status === 'abort') {
                    return; // درخواست لغو شده
                }
                
                let errorMessage = standalone_gsheets_admin.strings.connection_error || 'خطا در اتصال به سرور';
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMessage = errorData.data || errorMessage;
                    } catch (e) {
                        errorMessage = xhr.statusText || errorMessage;
                    }
                }
                
                $result.html('<div class="notice notice-error" style="padding: 15px; margin: 0;">' +
                           '<p><strong>' + standalone_gsheets_admin.strings.error + '</strong></p>' +
                           '<p>' + escapeHtml(errorMessage) + '</p>' +
                           '<p><small>Status: ' + status + ' | Error: ' + error + '</small></p>' +
                           '</div>');
            }
        });
    }
    
    /**
     * پاک کردن کش
     */
    function handleClearCache() {
        const $button = $('#clear-cache-btn');
        const originalText = $button.text();
        
        if ($button.prop('disabled')) {
            return;
        }
        
        $button.prop('disabled', true);
        $button.text(standalone_gsheets_admin.strings.cache_clearing || 'در حال پاک کردن کش...');
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_clear_cache',
                nonce: standalone_gsheets_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showTemporaryMessage(standalone_gsheets_admin.strings.cache_cleared || 'کش پاک شد', 'success');
                } else {
                    showTemporaryMessage('خطا در پاک کردن کش: ' + (response.data || 'خطای نامشخص'), 'error');
                }
            },
            error: function() {
                showTemporaryMessage('خطا در ارتباط با سرور', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text(originalText);
            }
        });
    }
    
    /**
     * بررسی سلامت سیستم
     */
    function handleHealthCheck() {
        const $button = $('#run-health-check-btn');
        const $results = $('#health-check-results');
        const originalText = $button.text();
        
        if ($button.prop('disabled')) {
            return;
        }
        
        $button.prop('disabled', true);
        $button.text(standalone_gsheets_admin.strings.health_checking || 'در حال بررسی...');
        $results.html('<div class="checking-health"><p>🔍 در حال بررسی سلامت سیستم...</p></div>').show();
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_health_check',
                nonce: standalone_gsheets_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const health = response.data;
                    let html = '<div class="health-check-results">';
                    
                    // وضعیت کلی
                    html += '<div class="overall-status">';
                    html += '<h3>وضعیت کلی: ';
                    
                    if (health.overall_status === 'healthy') {
                        html += '<span class="status-healthy">✅ سالم</span>';
                    } else if (health.overall_status === 'warning') {
                        html += '<span class="status-warning">⚠️ هشدار</span>';
                    } else {
                        html += '<span class="status-critical">❌ بحرانی</span>';
                    }
                    
                    html += '</h3>';
                    html += '</div>';
                    
                    // خلاصه
                    html += '<div class="health-summary">';
                    html += '<p><strong>خلاصه:</strong> ';
                    html += (health.summary.passed || 0) + ' موفق، ';
                    html += (health.summary.warnings || 0) + ' هشدار، ';
                    html += (health.summary.errors || 0) + ' خطا';
                    html += '</p>';
                    html += '</div>';
                    
                    // جزئیات بررسی‌ها
                    html += '<div class="health-details">';
                    html += '<h4>📋 جزئیات بررسی‌ها:</h4>';
                    html += '<div class="health-checks">';
                    
                    Object.keys(health.checks).forEach(function(checkName) {
                        const check = health.checks[checkName];
                        let icon = '✅';
                        let statusClass = 'check-pass';
                        
                        if (check.status === 'warning') {
                            icon = '⚠️';
                            statusClass = 'check-warning';
                        } else if (check.status === 'fail') {
                            icon = '❌';
                            statusClass = 'check-fail';
                        }
                        
                        html += '<div class="health-check-item ' + statusClass + '">';
                        html += '<div class="check-header">';
                        html += '<span class="check-icon">' + icon + '</span>';
                        html += '<strong class="check-name">' + checkName.replace(/_/g, ' ') + '</strong>';
                        html += '</div>';
                        html += '<div class="check-message">' + escapeHtml(check.message) + '</div>';
                        
                        if (check.details && Object.keys(check.details).length > 0) {
                            html += '<div class="check-details">';
                            html += '<button class="toggle-details" data-target="details-' + checkName + '">نمایش جزئیات</button>';
                            html += '<div class="details-content" id="details-' + checkName + '" style="display: none;">';
                            html += '<pre>' + JSON.stringify(check.details, null, 2) + '</pre>';
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    html += '</div>';
                    
                    // توصیه‌ها
                    if (health.recommendations && health.recommendations.length > 0) {
                        html += '<div class="health-recommendations">';
                        html += '<h4>💡 توصیه‌ها:</h4>';
                        html += '<ul>';
                        health.recommendations.forEach(function(recommendation) {
                            html += '<li>' + escapeHtml(recommendation) + '</li>';
                        });
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    $results.html(html);
                    
                    // اضافه کردن event handler برای toggle جزئیات
                    $('.toggle-details').on('click', function() {
                        const target = $(this).data('target');
                        $('#' + target).slideToggle(300);
                        $(this).text(function(i, text) {
                            return text === 'نمایش جزئیات' ? 'مخفی کردن جزئیات' : 'نمایش جزئیات';
                        });
                    });
                    
                } else {
                    $results.html('<div class="notice notice-error"><p>خطا در بررسی سلامت: ' + escapeHtml(response.data || 'خطای نامشخص') + '</p></div>');
                }
            },
            error: function() {
                $results.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور برای بررسی سلامت</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text(originalText);
            }
        });
    }
    
    /**
     * صدور تنظیمات
     */
    function handleExportSettings() {
        const $button = $('#export-settings-btn');
        const $textarea = $('#import-export-data');
        
        if ($button.prop('disabled')) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_export_settings',
                nonce: standalone_gsheets_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const exportData = JSON.stringify(response.data, null, 2);
                    $textarea.val(exportData).slideDown(300).focus();
                    showTemporaryMessage('تنظیمات صادر شد', 'success');
                    
                    // کپی خودکار به کلیپ‌بورد
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(exportData).then(function() {
                            showTemporaryMessage('تنظیمات در کلیپ‌بورد کپی شد', 'info');
                        }).catch(function(err) {
                            console.error('خطا در کپی کردن:', err);
                        });
                    }
                } else {
                    showTemporaryMessage('خطا در صدور تنظیمات: ' + escapeHtml(response.data || 'خطای نامشخص'), 'error');
                }
            },
            error: function() {
                showTemporaryMessage('خطا در ارتباط با سرور', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * وارد کردن تنظیمات
     */
    function handleImportSettings() {
        const $button = $('#import-settings-btn');
        const $textarea = $('#import-export-data');
        const importData = $textarea.val().trim();
        
        if (!importData) {
            $textarea.slideDown(300).focus();
            showTemporaryMessage('لطفاً داده‌های JSON را وارد کنید', 'warning');
            return;
        }
        
        try {
            JSON.parse(importData); // بررسی صحت JSON
        } catch (e) {
            showTemporaryMessage('فرمت JSON نامعتبر است: ' + e.message, 'error');
            return;
        }
        
        if (!confirm('آیا مطمئن هستید که می‌خواهید تنظیمات فعلی را جایگزین کنید؟')) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_import_settings',
                nonce: standalone_gsheets_admin.nonce,
                import_data: importData
            },
            success: function(response) {
                if (response.success) {
                    showTemporaryMessage('تنظیمات وارد شد. صفحه در حال بارگذاری مجدد...', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showTemporaryMessage('خطا در وارد کردن تنظیمات: ' + escapeHtml(response.data || 'خطای نامشخص'), 'error');
                }
            },
            error: function() {
                showTemporaryMessage('خطا در ارتباط با سرور', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * کپی شورت‌کد
     */
    function handleCopyShortcode(e) {
        e.preventDefault();
        
        const $pre = $(this).siblings('pre');
        let text = $pre.length ? $pre.text() : $(this).data('shortcode');
        
        if (!text) {
            showTemporaryMessage('شورت‌کد یافت نشد', 'error');
            return;
        }
        
        copyToClipboard(text, 'شورت‌کد کپی شد!');
    }
    
    /**
     * کپی شورت‌کد سریع
     */
    function handleQuickShortcodeCopy(e) {
        e.preventDefault();
        
        const text = $(this).text();
        copyToClipboard(text, 'شورت‌کد کپی شد!');
    }
    
    /**
     * کپی به کلیپ‌بورد
     */
    function copyToClipboard(text, successMessage) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showTemporaryMessage(successMessage, 'success');
            }).catch(function(err) {
                fallbackCopyToClipboard(text, successMessage);
            });
        } else {
            fallbackCopyToClipboard(text, successMessage);
        }
    }
    
    /**
     * روش جایگزین کپی به کلیپ‌بورد
     */
    function fallbackCopyToClipboard(text, successMessage) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            showTemporaryMessage(successMessage, 'success');
        } catch (err) {
            showTemporaryMessage('خطا در کپی کردن', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    /**
     * اعتبارسنجی فرم
     */
    function validateForm(e) {
        let isValid = true;
        const errors = [];
        
        // بررسی فایل اعتبارنامه
        const credentialsFile = $('input[name="credentials_file"]').val();
        const existingCredentials = $('input[name="standalone_gsheets_settings[credentials_path]"]').val();
        
        if (!credentialsFile && !existingCredentials) {
            errors.push('لطفاً فایل اعتبارنامه Google را آپلود کنید.');
            $('input[name="credentials_file"]').addClass('error');
            isValid = false;
        }
        
        // بررسی آی‌دی اسپردشیت
        const $spreadsheetInput = $('input[name="standalone_gsheets_settings[spreadsheet_id]"]');
        const spreadsheetId = $spreadsheetInput.val().trim();
        
        if (!spreadsheetId) {
            errors.push('لطفاً آی‌دی اسپردشیت را وارد کنید.');
            $spreadsheetInput.addClass('error');
            isValid = false;
        } else if (!isValidSpreadsheetId(spreadsheetId)) {
            errors.push('فرمت آی‌دی اسپردشیت نامعتبر است.');
            $spreadsheetInput.addClass('error');
            isValid = false;
        }
        
        // بررسی زمان کش
        const $cacheInput = $('input[name="standalone_gsheets_settings[cache_time]"]');
        const cacheTime = parseInt($cacheInput.val());
        
        if (isNaN(cacheTime) || cacheTime < 60 || cacheTime > 3600) {
            errors.push('زمان کش باید بین 60 تا 3600 ثانیه باشد.');
            $cacheInput.addClass('error');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showTemporaryMessage(errors.join('<br>'), 'error', 6000);
            
            // Focus on first error field
            $('.error').first().focus();
        }
        
        return isValid;
    }
    
    /**
     * اعتبارسنجی فایل اعتبارنامه
     */
    function validateCredentialsFile() {
        const file = this.files[0];
        const $input = $(this);
        
        if (!file) {
            return;
        }
        
        // بررسی نوع فایل
        if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
            showTemporaryMessage('فقط فایل‌های JSON مجاز هستند.', 'error');
            $input.val('');
            return;
        }
        
        // بررسی اندازه فایل (حداکثر 1MB)
        if (file.size > 1024 * 1024) {
            showTemporaryMessage('حجم فایل نباید بیشتر از 1 مگابایت باشد.', 'error');
            $input.val('');
            return;
        }
        
        // بررسی محتوای JSON
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const credentials = JSON.parse(e.target.result);
                
                // بررسی فیلدهای ضروری
                const requiredFields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
                const missingFields = requiredFields.filter(field => !credentials[field]);
                
                if (missingFields.length > 0) {
                    showTemporaryMessage('فیلدهای ضروری یافت نشد: ' + missingFields.join(', '), 'error');
                    $input.val('');
                    return;
                }
                
                if (credentials.type !== 'service_account') {
                    showTemporaryMessage('فایل باید از نوع Service Account باشد.', 'error');
                    $input.val('');
                    return;
                }
                
                showTemporaryMessage('✅ فایل اعتبارنامه معتبر است.', 'success');
                
            } catch (error) {
                showTemporaryMessage('فایل JSON معتبر نیست: ' + error.message, 'error');
                $input.val('');
            }
        };
        
        reader.readAsText(file);
    }
    
    /**
     * پیش‌نمایش آی‌دی اسپردشیت
     */
    function previewSpreadsheetId() {
        const value = $(this).val().trim();
        let $preview = $(this).siblings('.spreadsheet-id-preview');
        
        if (!$preview.length) {
            $preview = $('<div class="spreadsheet-id-preview"></div>');
            $(this).after($preview);
        }
        
        if (value.length > 20) {
            const preview = '...' + value.substr(-20);
            $preview.html('<strong>پیش‌نمایش:</strong> ' + escapeHtml(preview));
            
            if (isValidSpreadsheetId(value)) {
                $preview.addClass('valid').removeClass('invalid');
                $preview.append(' <span style="color: green;">✓</span>');
            } else {
                $preview.addClass('invalid').removeClass('valid');
                $preview.append(' <span style="color: red;">⚠ فرمت نامعتبر</span>');
            }
            
            $preview.slideDown(200);
        } else if (value) {
            $preview.html('<strong>پیش‌نمایش:</strong> ' + escapeHtml(value));
            $preview.removeClass('valid invalid').slideDown(200);
        } else {
            $preview.slideUp(200);
        }
    }
    
    /**
     * بررسی صحت فرمت آی‌دی اسپردشیت
     */
    function isValidSpreadsheetId(id) {
        // Google Spreadsheet ID معمولاً 44 کاراکتر دارد و شامل حروف، اعداد، - و _ است
        const pattern = /^[a-zA-Z0-9-_]{44}$/;
        const loosePattern = /^[a-zA-Z0-9-_]{20,}$/;
        
        return pattern.test(id) || loosePattern.test(id);
    }
    
    /**
     * تبدیل تنظیمات پیشرفته
     */
    function toggleAdvancedSettings() {
        const $section = $('.advanced-settings-section');
        const $toggle = $(this);
        
        $section.slideToggle(300, function() {
            const isVisible = $section.is(':visible');
            $toggle.text(isVisible ? 'مخفی کردن تنظیمات پیشرفته' : 'نمایش تنظیمات پیشرفته');
            
            // ذخیره وضعیت
            localStorage.setItem('gsheets_advanced_visible', isVisible);
        });
    }
    
    /**
     * ذخیره خودکار تنظیمات
     */
    function autoSaveSettings() {
        const $field = $(this);
        const fieldName = $field.attr('name');
        const fieldValue = $field.is(':checkbox') ? ($field.is(':checked') ? '1' : '0') : $field.val();
        
        // نمایش نشانگر ذخیره
        showAutoSaveIndicator($field, 'saving');
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_auto_save',
                nonce: standalone_gsheets_admin.nonce,
                field_name: fieldName,
                field_value: fieldValue
            },
            success: function(response) {
                if (response.success) {
                    showAutoSaveIndicator($field, 'saved');
                } else {
                    showAutoSaveIndicator($field, 'error');
                }
            },
            error: function() {
                showAutoSaveIndicator($field, 'error');
            }
        });
    }
    
    /**
     * نمایش نشانگر ذخیره خودکار
     */
    function showAutoSaveIndicator($field, status) {
        let $indicator = $field.siblings('.auto-save-indicator');
        
        if (!$indicator.length) {
            $indicator = $('<span class="auto-save-indicator"></span>');
            $field.after($indicator);
        }
        
        $indicator.removeClass('saving saved error').stop(true, true);
        
        switch (status) {
            case 'saving':
                $indicator.addClass('saving').text('در حال ذخیره...').fadeIn(200);
                break;
            case 'saved':
                $indicator.addClass('saved').text('✓ ذخیره شد').fadeIn(200);
                setTimeout(() => $indicator.fadeOut(300), 2000);
                break;
            case 'error':
                $indicator.addClass('error').text('✗ خطا در ذخیره').fadeIn(200);
                setTimeout(() => $indicator.fadeOut(300), 3000);
                break;
        }
    }
    
    /**
     * اضافه کردن tooltip ها
     */
    function addTooltips() {
        // افزودن tooltip برای دکمه‌ها
        $('#test-connection-btn').attr('title', 'تست اتصال به Google Sheets API v4');
        $('#clear-cache-btn').attr('title', 'پاک کردن تمام کش‌های ذخیره شده');
        $('#export-settings-btn').attr('title', 'صدور تنظیمات فعلی به فرمت JSON');
        $('#import-settings-btn').attr('title', 'وارد کردن تنظیمات از فرمت JSON');
        $('#run-health-check-btn').attr('title', 'بررسی وضعیت و سلامت سیستم');
        
        // افزودن دکمه کپی به شورت‌کدها
        $('.standalone-gsheets-admin pre, .shortcode-examples pre').each(function() {
            const $pre = $(this);
            if (!$pre.siblings('.copy-shortcode').length && $pre.text().trim()) {
                const $copyBtn = $('<button type="button" class="button button-small copy-shortcode">📋 کپی</button>');
                $pre.after($copyBtn);
            }
        });
    }
    
    /**
     * بررسی وضعیت اتصال
     */
    function checkConnectionStatus() {
        let $statusIndicator = $('.connection-status-indicator');
        
        if (!$statusIndicator.length) {
            $statusIndicator = $('<div class="connection-status-indicator" style="margin-bottom: 20px;"></div>');
            $('.wrap h1').after($statusIndicator);
        }
        
        $.ajax({
            url: standalone_gsheets_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_test_connection',
                nonce: standalone_gsheets_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $statusIndicator.html('<span class="connection-status connected">🟢 متصل به Google Sheets API v4</span>');
                } else {
                    $statusIndicator.html('<span class="connection-status disconnected">🔴 عدم اتصال به Google Sheets</span>');
                }
            },
            error: function() {
                $statusIndicator.html('<span class="connection-status error">⚠️ خطا در بررسی اتصال</span>');
            }
        });
    }
    
    /**
     * شروع بررسی دوره‌ای سلامت
     */
    function startPeriodicHealthCheck() {
        // بررسی هر 5 دقیقه
        healthCheckInterval = setInterval(checkConnectionStatus, 300000);
    }
    
    /**
     * نمایش راهنمای متنی
     */
    function showContextualHelp() {
        // اضافه کردن راهنمای کوتاه
        if (!$('.contextual-help').length) {
            const helpHtml = `
                <div class="contextual-help">
                    <h4>💡 راهنمای سریع API v4:</h4>
                    <ul>
                        <li><strong>بهترین عملکرد:</strong> از batch operations استفاده کنید</li>
                        <li><strong>کش:</strong> زمان کش پیشنهادی 300 ثانیه (5 دقیقه)</li>
                        <li><strong>Rate Limiting:</strong> حداکثر 100 درخواست در 100 ثانیه</li>
                        <li><strong>امنیت:</strong> فایل اعتبارنامه در پوشه محافظت شده نگهداری می‌شود</li>
                    </ul>
                    <button type="button" class="button-link dismiss-help">بستن راهنما</button>
                </div>
            `;
            
            $('.wrap h1').after(helpHtml);
            
            $('.dismiss-help').on('click', function() {
                $('.contextual-help').slideUp(300);
                localStorage.setItem('gsheets_help_dismissed', 'true');
            });
            
            // اگر قبلاً بسته شده، نمایش نده
            if (localStorage.getItem('gsheets_help_dismissed') === 'true') {
                $('.contextual-help').hide();
            }
        }
    }
    
    /**
     * نمایش پیام موقت
     */
    function showTemporaryMessage(message, type = 'info', duration = 4000) {
        // حذف پیام‌های قبلی
        $('.temporary-message').remove();
        
        const typeClass = {
            'success': 'notice-success',
            'error': 'notice-error', 
            'warning': 'notice-warning',
            'info': 'notice-info'
        }[type] || 'notice-info';
        
        const $message = $(`
            <div class="notice ${typeClass} temporary-message is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">بستن این اعلان</span>
                </button>
            </div>
        `);
        
        $('body').append($message);
        
        // اضافه کردن event برای بستن دستی
        $message.find('.notice-dismiss').on('click', function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // حذف خودکار
        setTimeout(() => {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, duration);
    }
    
    /**
     * مدیریت تب‌های تنظیمات
     */
    function initializeTabs() {
        const $tabs = $('.nav-tab-wrapper .nav-tab');
        const $contents = $('.tab-content');
        
        if (!$tabs.length) return;
        
        $tabs.on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).attr('href');
            
            if (!targetTab || !$(targetTab).length) return;
            
            // به‌روزرسانی تب‌ها
            $tabs.removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // به‌روزرسانی محتوا
            $contents.hide();
            $(targetTab).fadeIn(200);
            
            // ذخیره تب فعال
            localStorage.setItem('gsheets_active_tab', targetTab);
        });
        
        // بازیابی تب فعال
        const activeTab = localStorage.getItem('gsheets_active_tab');
        if (activeTab && $(activeTab).length) {
            $('a[href="' + activeTab + '"]').click();
        }
    }
    
    /**
     * اضافه کردن انیمیشن‌های تعاملی
     */
    function addInteractiveAnimations() {
        // انیمیشن برای دکمه‌ها
        $('.button').on('mouseenter', function() {
            $(this).addClass('button-hover');
        }).on('mouseleave', function() {
            $(this).removeClass('button-hover');
        });
        
        // انیمیشن برای کارت‌ها
        $('.card').on('mouseenter', function() {
            $(this).addClass('card-hover');
        }).on('mouseleave', function() {
            $(this).removeClass('card-hover');
        });
        
        // انیمیشن برای فیلدهای فرم
        $('input[type="text"], input[type="number"], textarea').on('focus', function() {
            $(this).addClass('field-focus');
        }).on('blur', function() {
            $(this).removeClass('field-focus error');
        });
    }
    
    /**
     * Escape HTML برای امنیت
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * پاکسازی منابع هنگام خروج
     */
    $(window).on('beforeunload', function() {
        if (healthCheckInterval) {
            clearInterval(healthCheckInterval);
        }
        if (connectionTestTimeout) {
            clearTimeout(connectionTestTimeout);
        }
        if (currentRequest) {
            currentRequest.abort();
        }
    });
    
})(jQuery);