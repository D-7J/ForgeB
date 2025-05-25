/**
 * اسکریپت فرانت‌اند پلاگین خواندن گوگل شیت مستقل
 */
(function($) {
    'use strict';
    
    // تابع برای بارگذاری داده‌های کاربر
    function loadUserData(element, retryCount = 0) {
    const $element = $(element);
    const spreadsheetId = $element.data('spreadsheet-id');
    const sheetTitle = $element.data('sheet-title');
    const field = $element.data('field');
    const maxRetries = standalone_gsheets.retry_attempts || 3;
    
    if (!spreadsheetId && !standalone_gsheets.default_spreadsheet_id) {
        $element.html('<p class="error">آی‌دی اسپردشیت مشخص نشده است.</p>');
        return;
    }
    
    // نمایش loading با شماره تلاش
    if (retryCount > 0) {
        $element.html('<p class="loading">' + standalone_gsheets.loading + ' (تلاش ' + (retryCount + 1) + ')</p>');
    } else {
        $element.html('<p class="loading">' + standalone_gsheets.loading + '</p>');
    }
    
    $.ajax({
        url: standalone_gsheets.ajax_url,
        type: 'POST',
        data: {
            action: 'standalone_gsheets_get_user_data',
            nonce: standalone_gsheets.nonce,
            spreadsheet_id: spreadsheetId,
            sheet_title: sheetTitle,
            field: field
        },
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                if (field) {
                    // نمایش یک فیلد خاص
                    $element.text(response.data || '');
                } else {
                    // نمایش کل داده‌ها
                    let html = '';
                    if (typeof response.data === 'object' && response.data !== null) {
                        if (Object.keys(response.data).length === 0) {
                            html = '<p class="no-data">اطلاعاتی برای نمایش یافت نشد.</p>';
                        } else {
                            Object.keys(response.data).forEach(function(sheetName) {
                                html += '<div class="sheet-data">';
                                html += '<h3 class="sheet-title">' + sheetName + '</h3>';
                                html += '<table class="gsheet-user-data">';
                                Object.keys(response.data[sheetName]).forEach(function(key) {
                                    if (key !== '_row_index' && key !== '_sheet_title' && key !== '_last_updated') {
                                        html += '<tr>';
                                        html += '<th>' + escapeHtml(key) + '</th>';
                                        html += '<td>' + escapeHtml(response.data[sheetName][key]) + '</td>';
                                        html += '</tr>';
                                    }
                                });
                                html += '</table>';
                                html += '</div>';
                            });
                        }
                    } else {
                        html = '<p class="no-data">اطلاعاتی برای نمایش یافت نشد.</p>';
                    }
                    $element.html(html);
                }
            } else {
                // در صورت خطا در response، retry کن
                handleLoadError($element, response.data, element, retryCount, maxRetries);
            }
        },
        error: function(xhr, status, error) {
            // مدیریت خطاهای مختلف
            let errorMessage = 'خطا در بارگذاری اطلاعات';
            
            if (status === 'timeout') {
                errorMessage = 'زمان انتظار به پایان رسید';
            } else if (status === 'abort') {
                errorMessage = 'درخواست لغو شد';
            } else if (xhr.status === 0) {
                errorMessage = 'عدم اتصال به اینترنت';
            } else if (xhr.status >= 500) {
                errorMessage = 'خطای سرور';
            }
            
            handleLoadError($element, errorMessage, element, retryCount, maxRetries);
        }
    });
}
// تابع مدیریت خطا و retry
function handleLoadError($element, errorMessage, element, retryCount, maxRetries) {
    if (retryCount < maxRetries) {
        // نمایش پیام retry
        $element.html('<p class="loading" style="color: orange;">تلاش مجدد در ' + (retryCount + 1) + ' ثانیه...</p>');
        
        // retry با تاخیر افزایشی
        setTimeout(function() {
            loadUserData(element, retryCount + 1);
        }, (retryCount + 1) * 1000);
    } else {
        // نمایش خطای نهایی با دکمه تلاش مجدد
        $element.html(
            '<div class="error-container">' +
                '<p class="error">' + escapeHtml(errorMessage) + '</p>' +
                '<button class="gsheet-retry-btn button button-small">تلاش مجدد</button>' +
            '</div>'
        );
        
        // اضافه کردن event handler برای دکمه retry
        $element.find('.gsheet-retry-btn').on('click', function(e) {
            e.preventDefault();
            loadUserData(element, 0);
        });
    }
}
// تابع escape HTML برای امنیت
function escapeHtml(text) {
    if (typeof text !== 'string') {
        return text;
    }
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
    
    // تابع برای بارگذاری مقدار سلول
    function loadCellValue(element, retryCount = 0) {
    const $element = $(element);
    const spreadsheetId = $element.data('spreadsheet-id');
    const sheetTitle = $element.data('sheet-title');
    const cell = $element.data('cell');
    const maxRetries = standalone_gsheets.retry_attempts || 3;
    
    if (!spreadsheetId && !standalone_gsheets.default_spreadsheet_id) {
        $element.html('<p class="error">آی‌دی اسپردشیت مشخص نشده است.</p>');
        return;
    }
    
    if (!sheetTitle || !cell) {
        $element.html('<p class="error">نام شیت و سلول مشخص نشده است.</p>');
        return;
    }
    
    // نمایش loading
    if (retryCount > 0) {
        $element.html('<span class="loading">' + standalone_gsheets.loading + ' (تلاش ' + (retryCount + 1) + ')</span>');
    } else {
        $element.html('<span class="loading">' + standalone_gsheets.loading + '</span>');
    }
    
    $.ajax({
        url: standalone_gsheets.ajax_url,
        type: 'POST',
        data: {
            action: 'standalone_gsheets_get_cell_value',
            nonce: standalone_gsheets.nonce,
            spreadsheet_id: spreadsheetId,
            sheet_title: sheetTitle,
            cell: cell
        },
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                $element.text(response.data || '');
            } else {
                handleCellLoadError($element, response.data, element, retryCount, maxRetries);
            }
        },
        error: function(xhr, status, error) {
            let errorMessage = 'خطا در بارگذاری';
            
            if (status === 'timeout') {
                errorMessage = 'زمان انتظار به پایان رسید';
            } else if (xhr.status === 0) {
                errorMessage = 'عدم اتصال';
            }
            
            handleCellLoadError($element, errorMessage, element, retryCount, maxRetries);
        }
    });
}
function handleCellLoadError($element, errorMessage, element, retryCount, maxRetries) {
    if (retryCount < maxRetries) {
        setTimeout(function() {
            loadCellValue(element, retryCount + 1);
        }, (retryCount + 1) * 1000);
    } else {
        $element.html('<span class="error" title="' + escapeHtml(errorMessage) + '">⚠️ خطا</span>');
    }
}
    
    // اجرای خودکار در زمان بارگذاری صفحه
    $(document).ready(function() {
        // بارگذاری داده‌های کاربر برای عناصر مشخص شده
        $('.gsheet-auto-load').each(function() {
            loadUserData(this);
        });
        
        // بارگذاری مقدار سلول برای عناصر مشخص شده
        $('.gsheet-cell-auto-load').each(function() {
            loadCellValue(this);
        });
        
        // رفرش داده‌ها با کلیک روی دکمه
        $(document).on('click', '.gsheet-refresh', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            if (target) {
                const $target = $(target);
                if ($target.hasClass('gsheet-cell-auto-load')) {
                    loadCellValue($target[0]);
                } else {
                    loadUserData($target[0]);
                }
            }
        });
        
        // رفرش همه داده‌ها
        $(document).on('click', '.gsheet-refresh-all', function(e) {
            e.preventDefault();
            $('.gsheet-auto-load').each(function() {
                loadUserData(this);
            });
            $('.gsheet-cell-auto-load').each(function() {
                loadCellValue(this);
            });
        });
        
        // نمایش/مخفی کردن جزئیات
        $(document).on('click', '.gsheet-toggle-details', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            if (target) {
                $(target).toggle();
                const $button = $(this);
                if ($(target).is(':visible')) {
                    $button.text($button.data('hide-text') || 'مخفی کردن جزئیات');
                } else {
                    $button.text($button.data('show-text') || 'نمایش جزئیات');
                }
            }
        });
        
        // افزودن انیمیشن hover برای جداول
        $(document).on('mouseenter', '.gsheet-user-data tr', function() {
            $(this).addClass('hover');
        }).on('mouseleave', '.gsheet-user-data tr', function() {
            $(this).removeClass('hover');
        });
        
        // کپی کردن مقدار سلول با کلیک
        $(document).on('click', '.gsheet-copy-value', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            if (target) {
                const $target = $(target);
                const value = $target.text();
                
                // کپی به کلیپ‌بورد
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(value).then(function() {
                        showNotification('مقدار کپی شد!', 'success');
                    });
                } else {
                    // روش قدیمی برای مرورگرهای قدیمی
                    const textArea = document.createElement('textarea');
                    textArea.value = value;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showNotification('مقدار کپی شد!', 'success');
                }
            }
        });
    });
    
    // تابع نمایش اعلان
    function showNotification(message, type) {
        const $notification = $('<div class="gsheet-notification ' + type + '">' + message + '</div>');
        $('body').append($notification);
        
        $notification.fadeIn(200).delay(2000).fadeOut(200, function() {
            $(this).remove();
        });
    }
    
    // تابع عمومی برای بارگذاری داده‌ها
    window.standaloneGsheetsLoadData = function(selector, options) {
        const $elements = $(selector);
        $elements.each(function() {
            const $element = $(this);
            if (options) {
                Object.keys(options).forEach(function(key) {
                    $element.data(key, options[key]);
                });
            }
            loadUserData(this);
        });
    };
    
    // تابع عمومی برای بارگذاری مقدار سلول
    window.standaloneGsheetsLoadCell = function(selector, options) {
        const $elements = $(selector);
        $elements.each(function() {
            const $element = $(this);
            if (options) {
                Object.keys(options).forEach(function(key) {
                    $element.data(key, options[key]);
                });
            }
            loadCellValue(this);
        });
    };
    
    // تابع عمومی برای رفرش داده‌ها
    window.standaloneGsheetsRefresh = function(selector) {
        const $elements = $(selector);
        $elements.each(function() {
            const $element = $(this);
            $element.html('<p>' + standalone_gsheets.loading + '</p>');
            if ($element.hasClass('gsheet-cell-auto-load')) {
                loadCellValue(this);
            } else {
                loadUserData(this);
            }
        });
    };
    
    // تابع برای دریافت مقدار یک فیلد خاص
    window.standaloneGsheetsGetField = function(fieldName, callback, options) {
        options = options || {};
        
        $.ajax({
            url: standalone_gsheets.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_get_user_data',
                nonce: standalone_gsheets.nonce,
                spreadsheet_id: options.spreadsheet_id,
                sheet_title: options.sheet_title,
                field: fieldName
            },
            success: function(response) {
                if (response.success) {
                    callback(null, response.data);
                } else {
                    callback(response.data, null);
                }
            },
            error: function() {
                callback('خطا در بارگذاری اطلاعات', null);
            }
        });
    };
    
    // تابع برای دریافت مقدار سلول
    window.standaloneGsheetsGetCellValue = function(sheetTitle, cell, callback, options) {
        options = options || {};
        
        $.ajax({
            url: standalone_gsheets.ajax_url,
            type: 'POST',
            data: {
                action: 'standalone_gsheets_get_cell_value',
                nonce: standalone_gsheets.nonce,
                spreadsheet_id: options.spreadsheet_id,
                sheet_title: sheetTitle,
                cell: cell
            },
            success: function(response) {
                if (response.success) {
                    callback(null, response.data);
                } else {
                    callback(response.data, null);
                }
            },
            error: function() {
                callback('خطا در بارگذاری اطلاعات', null);
            }
        });
    };
    
})(jQuery);