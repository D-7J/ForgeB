/**
 * WoW Raid Form Manager - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    // Global variables
    var currentSelectedRaid = null;
    var currentRaidId = null;
    var currentRaidInfo = null;
    var showPast = false;
    var currentRaids = [];
    
    // Raid Form Submission
    $("#wow-raid-form").on("submit", function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $("#submit-raid-btn");
        const $loading = $("#form-loading");
        const $messages = $("#form-messages");
        
        $messages.empty();
        
        $btn.prop("disabled", true);
        $loading.show();
        
        // Parse time input
        var timeValue = $("#raid-time").val();
        var timeParts = timeValue.split(":");
        var hour = parseInt(timeParts[0]);
        var minute = parseInt(timeParts[1]);
        
        const formData = {
            action: "create_wow_raid",
            wow_raid_nonce: $("input[name=wow_raid_nonce]").val(),
            raid_date: $("#raid-date").val(),
            raid_hour: hour,
            raid_minute: minute,
            raid_name: $("#raid-name").val(),
            loot_type: $("#loot-type").val(),
            difficulty: $("#difficulty").val(),
            boss_count: $("#boss-count").val(),
            available_spots: $("#available-spots").val(),
            raid_leader: $("#raid-leader").val(),
            gold_collector: $("#gold-collector").val(),
            notes: $("#notes").val()
        };
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: formData,
            success: function(response) {
                $btn.prop("disabled", false);
                $loading.hide();
                
                if (response.success) {
                    $messages.html('<div class="form-success">' + response.data.message + '</div>');
                    $form[0].reset();
                    $("#raid-name").val("Undermine");
                    
                    $(document).trigger("raid_created");
                    
                    if (typeof window.loadRaids === "function") {
                        setTimeout(window.loadRaids, 1000);
                    }
                } else {
                    $messages.html('<div class="form-error">' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop("disabled", false);
                $loading.hide();
                $messages.html('<div class="form-error">Connection error. Please try again.</div>');
            }
        });
    });
    
    // Optimized AJAX function for raid dashboard
    window.refreshRaidsList = function() {
        $("#raids-list-container").html('<p class="loading">Loading raids...</p>');
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: { action: "get_user_raids" },
            success: function(response) {
                if (response.success && response.data.raids) {
                    currentRaids = response.data.raids;
                    renderRaidsList();
                    $("#raid-count-display").text("(" + currentRaids.length + ")");
                } else {
                    $("#raids-list-container").html("<p>No raids found.</p>");
                }
            },
            error: function() {
                $("#raids-list-container").html('<p class="error">Failed to load raids.</p>');
            }
        });
    };
    
    // Optimized render function
    function renderRaidsList() {
        var upcoming = currentRaids.filter(r => r.status !== "done" && r.status !== "cancelled");
        var past = currentRaids.filter(r => r.status === "done" || r.status === "cancelled");
        
        var html = "";
        
        if (upcoming.length > 0) {
            html += '<div class="raids-section"><h4>Upcoming Raids</h4>';
            upcoming.forEach(function(raid) {
                html += createSimpleRaidCard(raid);
            });
            html += "</div>";
        }
        
        if (past.length > 0) {
            html += '<div class="raids-section past-raids' + (showPast ? "" : " hidden") + '"><h4>Past Raids</h4>';
            past.forEach(function(raid) {
                html += createSimpleRaidCard(raid);
            });
            html += "</div>";
        }
        
        $("#raids-list-container").html(html || "<p>No raids scheduled.</p>");
        bindSimpleActions();
    }
    
    // Simplified raid card
    function createSimpleRaidCard(raid) {
        var statusClass = "raid-status-" + raid.status;
        return '<div class="simple-raid-card ' + statusClass + '" data-id="' + raid.id + '">' +
               '<div class="raid-title">' + raid.name + ' - ' + raid.date + ' @ ' + raid.time + '</div>' +
               '<div class="raid-info">' + raid.difficulty + ' | ' + raid.loot_type + ' | ' + raid.spots + ' spots</div>' +
               '<div class="raid-leaders">RL: ' + raid.leader + ' | GC: ' + raid.gold_collector + '</div>' +
               (raid.can_edit ? '<div class="raid-actions">' + getActionButtons(raid) + '</div>' : '') +
               '</div>';
    }
    
    // Simplified action buttons
    function getActionButtons(raid) {
        var buttons = "";
        if (raid.status !== "done" && raid.status !== "cancelled") {
            if (!raid.is_locked) {
                buttons += '<button class="btn-sm btn-lock" data-id="' + raid.id + '">Lock</button>';
            } else {
                buttons += '<button class="btn-sm btn-unlock" data-id="' + raid.id + '">Unlock</button>';
            }
            if (raid.is_past_time || raid.is_locked) {
                buttons += '<button class="btn-sm btn-done" data-id="' + raid.id + '">Done</button>';
            }
            buttons += '<button class="btn-sm btn-cancel" data-id="' + raid.id + '">Cancel</button>';
        }
        return buttons;
    }
    
    // Bind actions
    function bindSimpleActions() {
        $(".btn-lock, .btn-unlock, .btn-done, .btn-cancel").off("click").on("click", function() {
            var $btn = $(this);
            var raidId = $btn.data("id");
            var action = $btn.hasClass("btn-lock") ? "locked" : 
                       $btn.hasClass("btn-unlock") ? "active" :
                       $btn.hasClass("btn-done") ? "done" : "cancelled";
            
            if (action === "cancelled" && !confirm("Cancel this raid?")) return;
            
            $btn.prop("disabled", true);
            
            $.post(wow_raid_ajax.ajax_url, {
                action: "update_raid_status",
                raid_id: raidId,
                status: action
            }, function(response) {
                if (response.success) {
                    refreshRaidsList();
                } else {
                    alert("Error: " + response.data);
                    $btn.prop("disabled", false);
                }
            });
        });
    }
    
    // Toggle past raids
    window.togglePastRaidsList = function() {
        showPast = !showPast;
        $(".past-raids").toggleClass("hidden");
        $("#toggle-past-btn").html(showPast ? 
            '<i class="fas fa-eye-slash"></i> Hide Past' : 
            '<i class="fas fa-eye"></i> Show Past');
    };
    
    // Initial load if dashboard exists
    if ($("#raids-list-container").length) {
        refreshRaidsList();
        setInterval(refreshRaidsList, 60000);
    }
    
    // Listen for form submissions
    $(document).on("raid_created", refreshRaidsList);
    
    // Booking form functionality
    function initBookingForm() {
        if ($("#wow-booking-form").length === 0) return;
        
        loadAvailableRaids();
        
        // Handle raid selection
        $(document).on("click", ".raid-card.selectable", function() {
            var raidData = $(this).data("raid");
            selectRaid(raidData);
        });
        
        // Handle booking type change
        $("#booking-type").on("change", function() {
            var bookingType = $(this).val();
            if (bookingType === "Specific Bosses") {
                $("#bosses-selection").show();
            } else {
                $("#bosses-selection").hide();
            }
        });
        
        // Handle armor type selection for VIP raids
        $("#armor-type").on("change", function() {
            var selectedArmor = $(this).val();
            var raidId = $("#selected-raid-id").val();
            
            if (selectedArmor && raidId && currentSelectedRaid && currentSelectedRaid.loot_type === "VIP") {
                loadArmorQueueStatus(raidId, selectedArmor);
            } else {
                $("#armor-queue-status").hide();
            }
        });
        
        // Handle booking form submission
        $("#wow-booking-form").on("submit", function(e) {
            e.preventDefault();
            submitBooking();
        });
        
        // Cancel booking form
        $("#cancel-booking-btn").on("click", function() {
            cancelBookingForm();
        });
    }
    
    function loadAvailableRaids() {
        $("#raids-list-container").html("<p>Loading available raids...</p>");
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: { action: "get_available_raids" },
            success: function(response) {
                if (response.success) {
                    renderAvailableRaids(response.data.raids);
                } else {
                    $("#raids-list-container").html('<p class="error">Error loading raids: ' + response.data + '</p>');
                }
            },
            error: function() {
                $("#raids-list-container").html('<p class="error">Failed to load raids. Please try again.</p>');
            }
        });
    }
    
    function renderAvailableRaids(raids) {
        if (raids.length === 0) {
            $("#raids-list-container").html("<p>No available raids found.</p>");
            return;
        }
        
        var html = '<div class="raids-grid">';
        
        raids.forEach(function(raid) {
            var statusClass = "raid-" + raid.status;
            var isSelectable = raid.status === "active" || raid.status === "locked";
            
            html += '<div class="raid-card ' + statusClass + (isSelectable ? " selectable" : "") + '" data-raid=\'' + JSON.stringify(raid) + '\'>';
            html += '<div class="raid-header">';
            html += '<h4>' + raid.name + '</h4>';
            html += '<span class="loot-badge loot-' + raid.loot_type.toLowerCase() + '">' + raid.loot_type + '</span>';
            html += '</div>';
            html += '<div class="raid-details">';
            html += '<p><strong>Date:</strong> ' + raid.date + ' @ ' + raid.time + '</p>';
            html += '<p><strong>Difficulty:</strong> ' + raid.difficulty + '</p>';
            html += '<p><strong>Bosses:</strong> ' + raid.boss_count + ' | <strong>Spots:</strong> ' + raid.spots + '</p>';
            html += '<p><strong>Leader:</strong> ' + raid.leader + '</p>';
            html += '<p><strong>Status:</strong> ' + raid.status.charAt(0).toUpperCase() + raid.status.slice(1) + '</p>';
            html += '</div>';
            
            if (isSelectable) {
                html += '<div class="raid-actions">';
                html += '<button class="btn-select">Select for Booking</button>';
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        $("#raids-list-container").html(html);
    }
    
    function selectRaid(raidData) {
        currentSelectedRaid = raidData;
        $("#selected-raid-id").val(raidData.id);
        
        // Show raid info
        var raidInfo = '<div class="raid-info-compact">';
        raidInfo += '<strong>' + raidData.name + '</strong> - ' + raidData.date + ' @ ' + raidData.time;
        raidInfo += ' | ' + raidData.difficulty + ' | ' + raidData.loot_type;
        raidInfo += '</div>';
        $("#selected-raid-details").html(raidInfo);
        $("#raid-info-display").show();
        
        // Show/hide armor selection for VIP raids
        if (raidData.loot_type === "VIP") {
            $("#armor-selection").show();
        } else {
            $("#armor-selection").hide();
        }
        
        // Hide raids list and show booking form
        $("#available-raids-section").hide();
        $("#wow-booking-form").show();
        $("#cancel-booking-btn").show();
    }
    
    function cancelBookingForm() {
        currentSelectedRaid = null;
        $("#selected-raid-id").val("");
        $("#raid-info-display").hide();
        $("#armor-selection").hide();
        $("#armor-queue-status").hide();
        $("#wow-booking-form").hide();
        $("#wow-booking-form")[0].reset();
        $("#available-raids-section").show();
        $("#cancel-booking-btn").hide();
    }
    
    function loadArmorQueueStatus(raidId, armorType) {
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: {
                action: "get_armor_queue",
                raid_id: raidId,
                armor_type: armorType
            },
            success: function(response) {
                if (response.success) {
                    renderArmorQueueStatus(response.data.queue, armorType);
                }
            }
        });
    }
    
    function renderArmorQueueStatus(queue, armorType) {
        if (queue.length === 0) {
            $("#armor-queue-display").html(
                '<div class="queue-slot available">' +
                '<i class="fas fa-crown"></i> Primary slot available for ' + armorType + ' armor!' +
                '</div>'
            );
        } else {
            var html = '<div class="armor-queue-list">';
            
            queue.forEach(function(booking, index) {
                var statusIcon = "";
                var statusClass = "queue-slot " + booking.status;
                var statusText = "";
                
                switch(booking.status) {
                    case "primary":
                        statusIcon = '<i class="fas fa-crown"></i>';
                        statusText = "Primary";
                        break;
                    case "backup":
                        statusIcon = '<i class="fas fa-shield-alt"></i>';
                        statusText = "Backup #" + (booking.priority - 1);
                        break;
                    case "waitlist":
                        statusIcon = '<i class="fas fa-clock"></i>';
                        statusText = "Waitlist #" + (booking.priority - 3);
                        break;
                }
                
                html += '<div class="' + statusClass + '">';
                html += statusIcon + ' ' + statusText + ': ' + booking.character + ' (' + booking.realm + ')';
                html += '</div>';
            });
            
            // Show what position the new booking would get
            var nextPosition = getNextAvailablePosition(queue);
            html += '<div class="queue-slot next-position">';
            html += '<i class="fas fa-plus"></i> Your position: ' + nextPosition;
            html += '</div>';
            
            html += '</div>';
            $("#armor-queue-display").html(html);
        }
        
        $("#armor-queue-status").show();
    }
    
    function getNextAvailablePosition(queue) {
        var primary = queue.filter(q => q.status === "primary");
        var backup = queue.filter(q => q.status === "backup");
        var waitlist = queue.filter(q => q.status === "waitlist");
        
        if (primary.length === 0) {
            return "Primary holder";
        } else if (backup.length < 2) {
            return "Backup #" + (backup.length + 1);
        } else {
            return "Waitlist #" + (waitlist.length + 1);
        }
    }
    
    function submitBooking() {
        var $btn = $("#submit-booking-btn");
        var $loading = $("#booking-loading");
        var $messages = $("#booking-messages");
        
        $messages.empty();
        $btn.prop("disabled", true);
        $loading.show();
        
        var selectedBosses = [];
        $('input[name="selected_bosses[]"]:checked').each(function() {
            selectedBosses.push($(this).val());
        });
        
        var formData = {
            action: "create_wow_booking",
            booking_nonce: $("input[name=booking_nonce]").val(),
            raid_id: $("#selected-raid-id").val(),
            buyer_charname: $("#buyer-charname").val(),
            buyer_realm: $("#buyer-realm").val(),
            battlenet: $("#battlenet").val(),
            selected_bosses: selectedBosses,
            class: $("#customer-class").val(),
            armor_type: $("#armor-type").val(),
            total_price: $("#total-price").val(),
            deposit: $("#deposit").val(),
            booking_type: $("#booking-type").val(),
            additional_info: $("#additional-info").val()
        };
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: formData,
            success: function(response) {
                $btn.prop("disabled", false);
                $loading.hide();
                
                if (response.success) {
                    var message = response.data.message;
                    
                    // Show priority status if available
                    if (response.data.armor_status && response.data.armor_status !== "primary") {
                        var priorityInfo = "";
                        if (response.data.armor_status === "backup") {
                            priorityInfo = " You are backup #" + (response.data.armor_priority - 1) + ".";
                        } else if (response.data.armor_status === "waitlist") {
                            priorityInfo = " You are #" + (response.data.armor_priority - 3) + " on the waitlist.";
                        }
                        message += priorityInfo;
                    }
                    
                    showBookingMessage(message, "success");
                    
                    // Reset form and go back to raids list
                    setTimeout(function() {
                        cancelBookingForm();
                        loadAvailableRaids();
                    }, 3000);
                } else {
                    showBookingMessage(response.data, "error");
                }
            },
            error: function() {
                $btn.prop("disabled", false);
                $loading.hide();
                showBookingMessage("Connection error. Please try again.", "error");
            }
        });
    }
    
    function showBookingMessage(message, type) {
        var className = type === "success" ? "form-success" : "form-error";
        $("#booking-messages").html('<div class="' + className + '">' + message + '</div>');
    }
    
    // Booking Management Functions
    function initBookingManagement() {
        if ($("#wow-bookings-container").length === 0) return;
        
        loadManageableRaids();
    }
    
    function loadManageableRaids() {
        $("#manageable-raids-container").html("<p>Loading your raids...</p>");
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: { action: "get_user_raids" },
            success: function(response) {
                if (response.success && response.data.raids) {
                    renderManageableRaids(response.data.raids);
                } else {
                    $("#manageable-raids-container").html("<p>No raids found.</p>");
                }
            }
        });
    }
    
    function renderManageableRaids(raids) {
        if (raids.length === 0) {
            $("#manageable-raids-container").html("<p>No raids available for management.</p>");
            return;
        }
        
        var html = '<div class="manageable-raids-grid">';
        
        raids.forEach(function(raid) {
            html += '<div class="raid-card manageable" onclick="loadRaidBookings(' + raid.id + ')">';
            html += '<h4>' + raid.name + '</h4>';
            html += '<p>' + raid.date + ' @ ' + raid.time + ' | ' + raid.loot_type + '</p>';
            html += '<p>Status: ' + raid.status + '</p>';
            html += '</div>';
        });
        
        html += '</div>';
        $("#manageable-raids-container").html(html);
    }
    
    window.loadRaidBookings = function(raidId) {
        currentRaidId = raidId;
        $("#bookings-list-container").html("<p>Loading bookings...</p>");
        $("#bookings-management-section").show();
        $(".raid-selector").hide();
        $("#back-to-raids-btn").show();
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: {
                action: "get_raid_bookings",
                raid_id: raidId
            },
            success: function(response) {
                if (response.success) {
                    currentRaidInfo = response.data.raid_info;
                    renderBookingsList(response.data.bookings);
                    updateBookingsSummary(response.data.bookings, raidId);
                    
                    // Show armor queue summary for VIP raids
                    if (currentRaidInfo.loot_type === "VIP") {
                        renderArmorSummary(response.data.armor_summary);
                        $("#armor-queue-summary-section").show();
                        $("#priority-management-tools").show();
                    } else {
                        $("#armor-queue-summary-section").hide();
                        $("#priority-management-tools").hide();
                    }
                } else {
                    $("#bookings-list-container").html('<p class="error">Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                $("#bookings-list-container").html('<p class="error">Failed to load bookings.</p>');
            }
        });
    };
    
    function renderArmorSummary(armorSummary) {
        if (!armorSummary || Object.keys(armorSummary).length === 0) {
            $("#armor-summary-grid").html("<p>No armor bookings yet.</p>");
            return;
        }
        
        var html = '<div class="armor-queue-summary">';
        
        for (var armorType in armorSummary) {
            var data = armorSummary[armorType];
            var statusClass = data.primary > 0 ? "has-primary" : "no-primary";
            
            html += '<div class="armor-summary-card ' + statusClass + '">';
            html += '<h6>' + armorType + ' Armor</h6>';
            html += '<div class="armor-counts">';
            html += '<div class="count-item primary">Primary: ' + data.primary + '</div>';
            html += '<div class="count-item backup">Backup: ' + data.backup + '</div>';
            html += '<div class="count-item waitlist">Waitlist: ' + data.waitlist + '</div>';
            html += '<div class="count-total">Total: ' + data.total + '</div>';
            html += '</div>';
            html += '<button class="btn-view-queue" onclick="viewArmorQueue(\'' + armorType + '\')">View Queue</button>';
            html += '</div>';
        }
        
        html += '</div>';
        $("#armor-summary-grid").html(html);
    }
    
    function renderBookingsList(bookings) {
        if (bookings.length === 0) {
            $("#bookings-list-container").html("<p>No bookings for this raid yet.</p>");
            return;
        }
        
        let html = '<div class="bookings-table-container">';
        html += '<table class="bookings-table priority-enabled">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>Customer</th>';
        html += '<th>Class</th>';
        html += '<th>Armor & Priority</th>';
        html += '<th>Booking Type</th>';
        html += '<th>Price</th>';
        html += '<th>Status</th>';
        html += '<th>Advertiser</th>';
        html += '<th>Actions</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        bookings.forEach(function(booking) {
            let statusClass = "status-" + booking.status;
            let armorStatusClass = booking.armor_status ? "armor-" + booking.armor_status : "";
            
            html += '<tr class="booking-row ' + statusClass + ' ' + armorStatusClass + '">';
            html += '<td>';
            html += '<strong>' + booking.buyer_charname + '</strong><br>';
            html += '<small>' + booking.buyer_realm + '</small>';
            if (booking.battlenet) {
                html += '<br><small>' + booking.battlenet + '</small>';
            }
            html += '</td>';
            html += '<td>' + (booking.class || "N/A") + '</td>';
            html += '<td>';
            if (booking.armor_type) {
                html += '<div class="armor-info">';
                html += '<span class="armor-type">' + booking.armor_type + '</span>';
                if (booking.armor_status) {
                    let priorityText = "";
                    let priorityIcon = "";
                    
                    switch(booking.armor_status) {
                        case "primary":
                            priorityText = "Primary";
                            priorityIcon = '<i class="fas fa-crown"></i>';
                            break;
                        case "backup":
                            priorityText = "Backup #" + (booking.armor_priority - 1);
                            priorityIcon = '<i class="fas fa-shield-alt"></i>';
                            break;
                        case "waitlist":
                            priorityText = "Waitlist #" + (booking.armor_priority - 3);
                            priorityIcon = '<i class="fas fa-clock"></i>';
                            break;
                    }
                    
                    html += '<div class="priority-badge ' + booking.armor_status + '">';
                    html += priorityIcon + ' ' + priorityText;
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += 'No armor selected';
            }
            html += '</td>';
            html += '<td>';
            html += booking.booking_type || "Standard";
            if (booking.selected_bosses && booking.selected_bosses.length > 0) {
                html += '<br><small>Bosses: ' + booking.selected_bosses.length + ' selected</small>';
            }
            html += '</td>';
            html += '<td>';
            html += booking.total_price + 'g';
            if (booking.deposit) {
                html += '<br><small>Deposit: ' + booking.deposit + 'g</small>';
            }
            html += '</td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + booking.status.charAt(0).toUpperCase() + booking.status.slice(1) + '</span></td>';
            html += '<td>' + booking.advertiser_name + '</td>';
            html += '<td>';
            
            // Action buttons with priority management
            if (booking.status === "pending" || booking.status === "confirmed") {
                // Priority management buttons for VIP raids
                if (currentRaidInfo && currentRaidInfo.loot_type === "VIP" && booking.armor_type) {
                    if (booking.armor_status === "backup" || booking.armor_status === "waitlist") {
                        html += '<button class="btn-small btn-promote" onclick="promoteBooking(' + booking.id + ')">Promote</button>';
                    }
                    if (booking.armor_status === "primary" || booking.armor_status === "backup") {
                        html += '<button class="btn-small btn-demote" onclick="demoteBooking(' + booking.id + ')">Demote</button>';
                    }
                }
                
                // Standard status buttons
                if (booking.status === "pending") {
                    html += '<button class="btn-small btn-confirm" onclick="updateBookingStatus(' + booking.id + ', \'confirmed\')">Confirm</button>';
                } else if (booking.status === "confirmed") {
                    html += '<button class="btn-small btn-complete" onclick="updateBookingStatus(' + booking.id + ', \'completed\')">Complete</button>';
                }
                html += '<button class="btn-small btn-cancel" onclick="updateBookingStatus(' + booking.id + ', \'cancelled\')">Cancel</button>';
            }
            
            html += '</td>';
            html += '</tr>';
            
            // Additional info row
            if (booking.additional_info) {
                html += '<tr class="additional-info-row">';
                html += '<td colspan="8">';
                html += '<small><strong>Notes:</strong> ' + booking.additional_info + '</small>';
                html += '</td>';
                html += '</tr>';
            }
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        
        $("#bookings-list-container").html(html);
    }
    
    function updateBookingsSummary(bookings, raidId) {
        var summary = {
            total: bookings.length,
            pending: bookings.filter(b => b.status === "pending").length,
            confirmed: bookings.filter(b => b.status === "confirmed").length,
            completed: bookings.filter(b => b.status === "completed").length,
            cancelled: bookings.filter(b => b.status === "cancelled").length,
            totalRevenue: bookings.reduce((sum, b) => sum + parseFloat(b.total_price || 0), 0)
        };
        
        var html = '<div class="summary-stats">';
        html += '<div class="stat-item"><strong>Total:</strong> ' + summary.total + '</div>';
        html += '<div class="stat-item"><strong>Pending:</strong> ' + summary.pending + '</div>';
        html += '<div class="stat-item"><strong>Confirmed:</strong> ' + summary.confirmed + '</div>';
        html += '<div class="stat-item"><strong>Completed:</strong> ' + summary.completed + '</div>';
        html += '<div class="stat-item"><strong>Revenue:</strong> ' + summary.totalRevenue.toFixed(2) + 'g</div>';
        html += '</div>';
        
        $("#summary-content").html(html);
        $("#bookings-summary").show();
    }
    
    // Priority management functions
    window.promoteBooking = function(bookingId) {
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: {
                action: "promote_booking",
                booking_id: bookingId,
                action_type: "promote"
            },
            success: function(response) {
                if (response.success) {
                    showBookingMessage("Booking promoted successfully!", "success");
                    refreshCurrentBookings();
                } else {
                    showBookingMessage("Error: " + response.data, "error");
                }
            }
        });
    };
    
    window.demoteBooking = function(bookingId) {
        if (confirm("Are you sure you want to demote this booking?")) {
            $.ajax({
                url: wow_raid_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "promote_booking",
                    booking_id: bookingId,
                    action_type: "demote"
                },
                success: function(response) {
                    if (response.success) {
                        showBookingMessage("Booking demoted successfully!", "success");
                        refreshCurrentBookings();
                    } else {
                        showBookingMessage("Error: " + response.data, "error");
                    }
                }
            });
        }
    };
    
    window.updateBookingStatus = function(bookingId, newStatus) {
        if (newStatus === "cancelled" && !confirm("Cancel this booking?")) return;
        
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: {
                action: "update_booking_status",
                booking_id: bookingId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    showBookingMessage("Booking status updated!", "success");
                    refreshCurrentBookings();
                } else {
                    showBookingMessage("Error: " + response.data, "error");
                }
            }
        });
    };
    
    window.refreshCurrentBookings = function() {
        if (currentRaidId) {
            loadRaidBookings(currentRaidId);
        }
    };
    
    window.backToRaidsList = function() {
        $("#bookings-management-section").hide();
        $(".raid-selector").show();
        $("#back-to-raids-btn").hide();
        currentRaidId = null;
        currentRaidInfo = null;
    };
    
    window.viewArmorQueue = function(armorType) {
        if (currentRaidId) {
            $.ajax({
                url: wow_raid_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "get_armor_queue",
                    raid_id: currentRaidId,
                    armor_type: armorType
                },
                success: function(response) {
                    if (response.success) {
                        showArmorQueueDialog(armorType, response.data.queue);
                    }
                }
            });
        }
    };
    
    function showArmorQueueDialog(armorType, queue) {
        var message = armorType + " Armor Queue:\n\n";
        
        if (queue.length === 0) {
            message += "No bookings for this armor type.";
        } else {
            queue.forEach(function(booking, index) {
                var statusIcon = "";
                switch(booking.status) {
                    case "primary": statusIcon = "👑"; break;
                    case "backup": statusIcon = "🛡️"; break;
                    case "waitlist": statusIcon = "⏰"; break;
                }
                
                message += statusIcon + " " + booking.character + " (" + booking.realm + ") - " + booking.status + "\n";
            });
        }
        
        alert(message);
    }
    
    window.autoPromoteQueue = function() {
        if (!currentRaidId) {
            showBookingMessage("No raid selected.", "error");
            return;
        }
        
        if (confirm("Auto-promote all armor queues for this raid?")) {
            $.ajax({
                url: wow_raid_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "auto_promote_queue",
                    raid_id: currentRaidId
                },
                success: function(response) {
                    if (response.success) {
                        showBookingMessage(response.data.message, "success");
                        refreshCurrentBookings();
                    } else {
                        showBookingMessage("Error: " + response.data, "error");
                    }
                },
                error: function() {
                    showBookingMessage("Connection error. Please try again.", "error");
                }
            });
        }
    };
    
    window.showTransferDialog = function() {
        $("#transfer-dialog").show();
        
        // Load available raids for transfer
        $.ajax({
            url: wow_raid_ajax.ajax_url,
            type: "POST",
            data: { action: "get_available_raids" },
            success: function(response) {
                if (response.success) {
                    var html = '<option value="">Select a raid</option>';
                    response.data.raids.forEach(function(raid) {
                        if (raid.id != currentRaidId && raid.loot_type === "VIP") {
                            html += '<option value="' + raid.id + '">' + raid.name + ' - ' + raid.date + '</option>';
                        }
                    });
                    $("#target-raid").html(html);
                }
            }
        });
    };
    
    window.closeTransferDialog = function() {
        $("#transfer-dialog").hide();
    };
    
    window.confirmTransfer = function() {
        var targetRaidId = $("#target-raid").val();
        var armorType = $("#transfer-armor").val();
        
        if (!targetRaidId || !armorType) {
            alert("Please select both raid and armor type.");
            return;
        }
        
        if (confirm("Transfer all waitlist bookings for " + armorType + " armor to the selected raid?")) {
            $.ajax({
                url: wow_raid_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "transfer_waitlist",
                    from_raid_id: currentRaidId,
                    to_raid_id: targetRaidId,
                    armor_type: armorType
                },
                success: function(response) {
                    if (response.success) {
                        showBookingMessage(response.data.message, "success");
                        closeTransferDialog();
                        refreshCurrentBookings();
                    } else {
                        showBookingMessage("Error: " + response.data, "error");
                    }
                }
            });
        }
    };
    
    // Initialize appropriate functionality based on page content
    initBookingForm();
    initBookingManagement();
});