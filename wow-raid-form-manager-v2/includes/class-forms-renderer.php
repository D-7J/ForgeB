<?php
/**
 * Forms renderer class
 */

if (!defined('ABSPATH')) exit;

class WoW_Raid_Forms_Renderer {
    
    /**
     * Plugin manager instance
     */
    private $manager;
    
    /**
     * Constructor
     */
    public function __construct($manager) {
        $this->manager = $manager;
    }
    
    /**
     * Render raid creation form
     */
    public function render_raid_form() {
        $today = date('Y-m-d');
        ?>
        <div class="wow-raid-form-container">
            <form id="wow-raid-form" class="raid-form-wrapper">
                <h3>Create New Raid</h3>
                
                <div class="form-row">
                    <label for="raid-date">Raid Date*</label>
                    <input type="date" id="raid-date" name="raid_date" min="<?php echo $today; ?>" required>
                </div>
                
                <div class="form-row">
                    <label for="raid-time">Raid Time*</label>
                    <input type="time" id="raid-time" name="raid_time" required>
                    <small class="form-help">Select raid start time (24-hour format)</small>
                </div>
                
                <div class="form-row">
                    <label for="raid-name">Raid Name*</label>
                    <select id="raid-name" name="raid_name" required>
                        <option value="">Select Raid</option>
                        <option value="Undermine" selected>Undermine</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="loot-type">Loot Type*</label>
                    <select id="loot-type" name="loot_type" required>
                        <option value="">Select Loot Type</option>
                        <option value="Saved">Saved</option>
                        <option value="Unsaved">Unsaved</option>
                        <option value="VIP">VIP</option>
                    </select>
                    <small class="form-help">VIP raids support armor type priority queues</small>
                </div>
                
                <div class="form-row">
                    <label for="difficulty">Difficulty*</label>
                    <select id="difficulty" name="difficulty" required>
                        <option value="">Select Difficulty</option>
                        <option value="Normal">Normal</option>
                        <option value="Heroic">Heroic</option>
                        <option value="Mythic">Mythic</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="boss-count">Number of Bosses*</label>
                    <select id="boss-count" name="boss_count" required>
                        <option value="">Select</option>
                        <?php for($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="available-spots">Available Spots*</label>
                    <input type="number" id="available-spots" name="available_spots" min="1" max="30" placeholder="20" required>
                </div>
                
                <div class="form-row">
                    <label for="raid-leader">Raid Leader*</label>
                    <input type="text" id="raid-leader" name="raid_leader" required>
                </div>
                
                <div class="form-row">
                    <label for="gold-collector">Gold Collector*</label>
                    <input type="text" id="gold-collector" name="gold_collector" required>
                </div>
                
                <div class="form-row">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" placeholder="Any additional information..." rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <button type="submit" id="submit-raid-btn">Create Raid</button>
                    <div id="form-loading" style="display: none;">Creating raid...</div>
                </div>
                
                <div id="form-messages"></div>
                
                <?php wp_nonce_field('create_wow_raid', 'wow_raid_nonce'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render booking form
     */
    public function render_booking_form($raid_id = '', $show_raid_list = true) {
        ?>
        <div class="wow-booking-form-container">
            <div class="booking-form-wrapper">
                <h3>Create Booking</h3>
                
                <?php if ($show_raid_list): ?>
                <div class="available-raids-section" id="available-raids-section">
                    <h4>Available Raids</h4>
                    <div id="raids-list-container">
                        <p>Loading available raids...</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <form id="wow-booking-form" class="booking-form" style="<?php echo !$show_raid_list ? '' : 'display:none;'; ?>">
                    <div class="form-section">
                        <h4>Customer Information</h4>
                        
                        <div class="form-row">
                            <label for="buyer-charname">Character Name*</label>
                            <input type="text" id="buyer-charname" name="buyer_charname" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="buyer-realm">Realm*</label>
                            <input type="text" id="buyer-realm" name="buyer_realm" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="battlenet">Battle.net (Optional)</label>
                            <input type="text" id="battlenet" name="battlenet" placeholder="Player#1234">
                        </div>
                        
                        <div class="form-row">
                            <label for="customer-class">Class</label>
                            <select id="customer-class" name="class">
                                <option value="">Select Class</option>
                                <?php foreach ($this->manager->wow_classes as $class): ?>
                                    <option value="<?php echo esc_attr($class); ?>"><?php echo esc_html($class); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Raid Details</h4>
                        
                        <div class="form-row" id="raid-info-display" style="display:none;">
                            <div class="selected-raid-info">
                                <h5>Selected Raid:</h5>
                                <div id="selected-raid-details"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <label for="booking-type">Booking Type</label>
                            <select id="booking-type" name="booking_type">
                                <option value="">Select Type</option>
                                <?php foreach ($this->manager->booking_types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row" id="bosses-selection" style="display:none;">
                            <label>Select Bosses</label>
                            <div class="checkbox-group">
                                <?php foreach ($this->manager->undermine_bosses as $index => $boss): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="selected_bosses[]" value="<?php echo esc_attr($boss); ?>">
                                        <?php echo esc_html($boss); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-row" id="armor-selection" style="display:none;">
                            <label for="armor-type">Armor Type (VIP Only)</label>
                            <select id="armor-type" name="armor_type">
                                <option value="">Select Armor Type</option>
                                <?php foreach ($this->manager->armor_types as $armor): ?>
                                    <option value="<?php echo esc_attr($armor); ?>"><?php echo esc_html($armor); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-help">For VIP raids, armor types use a priority queue system.</small>
                            
                            <!-- Armor Queue Status Display -->
                            <div id="armor-queue-status" style="display:none; margin-top: 10px;">
                                <div class="armor-queue-info">
                                    <h6>Current Queue Status:</h6>
                                    <div id="armor-queue-display"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Payment Information</h4>
                        
                        <div class="form-row">
                            <label for="total-price">Total Price (Gold)*</label>
                            <input type="number" id="total-price" name="total_price" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="deposit">Deposit (Gold)</label>
                            <input type="number" id="deposit" name="deposit" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-row">
                            <label for="additional-info">Additional Information</label>
                            <textarea id="additional-info" name="additional_info" rows="4" placeholder="Any special requests or notes..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <button type="button" id="cancel-booking-btn" style="display:none;">Cancel</button>
                        <button type="submit" id="submit-booking-btn">Create Booking</button>
                        <div id="booking-loading" style="display: none;">Creating booking...</div>
                    </div>
                    
                    <div id="booking-messages"></div>
                    
                    <input type="hidden" id="selected-raid-id" name="raid_id" value="<?php echo esc_attr($raid_id); ?>">
                    <?php wp_nonce_field('create_booking', 'booking_nonce'); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render raid bookings management interface
     */
    public function render_raid_bookings($raid_id = '', $show_management = false) {
        ?>
        <div class="wow-bookings-container">
            <div class="bookings-wrapper">
                <h3>Raid Bookings Management</h3>
                
                <?php if (empty($raid_id)): ?>
                <div class="raid-selector">
                    <h4>Select a Raid to Manage</h4>
                    <div id="manageable-raids-container">
                        <p>Loading your raids...</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div id="bookings-management-section" style="<?php echo empty($raid_id) ? 'display:none;' : ''; ?>">
                    <div class="raid-info-header" id="selected-raid-header">
                        <?php if (!empty($raid_id)): ?>
                            <script>loadRaidBookings(<?php echo intval($raid_id); ?>);</script>
                        <?php endif; ?>
                    </div>
                    
                    <!-- VIP Armor Queue Summary -->
                    <div id="armor-queue-summary-section" style="display:none;">
                        <h4>Armor Queue Status</h4>
                        <div id="armor-summary-grid"></div>
                    </div>
                    
                    <!-- Priority Management Tools -->
                    <div id="priority-management-tools" style="display:none;">
                        <h4>Priority Management</h4>
                        <div class="priority-tools-grid">
                            <button id="auto-promote-btn" onclick="autoPromoteQueue()">
                                <i class="fas fa-arrow-up"></i> Auto-Promote Queue
                            </button>
                            <button id="transfer-waitlist-btn" onclick="showTransferDialog()">
                                <i class="fas fa-exchange-alt"></i> Transfer Waitlist
                            </button>
                            <button id="reorder-queue-btn" onclick="showReorderDialog()">
                                <i class="fas fa-sort"></i> Reorder Queue
                            </button>
                        </div>
                    </div>
                    
                    <div class="bookings-controls">
                        <button id="refresh-bookings-btn" onclick="refreshCurrentBookings()">
                            <i class="fas fa-sync"></i> Refresh Bookings
                        </button>
                        <button id="back-to-raids-btn" onclick="backToRaidsList()" style="display:none;">
                            <i class="fas fa-arrow-left"></i> Back to Raids
                        </button>
                    </div>
                    
                    <div id="bookings-list-container">
                        <p>Loading bookings...</p>
                    </div>
                    
                    <div class="bookings-summary" id="bookings-summary" style="display:none;">
                        <h4>Booking Summary</h4>
                        <div id="summary-content"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transfer Dialog -->
        <div id="transfer-dialog" class="priority-dialog" style="display:none;">
            <div class="dialog-content">
                <h4>Transfer Waitlist to Another Raid</h4>
                <div class="form-group">
                    <label for="target-raid">Select Target Raid:</label>
                    <select id="target-raid">
                        <option value="">Loading raids...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transfer-armor">Armor Type:</label>
                    <select id="transfer-armor">
                        <option value="">Select armor type</option>
                        <?php foreach ($this->manager->armor_types as $armor): ?>
                            <option value="<?php echo esc_attr($armor); ?>"><?php echo esc_html($armor); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dialog-actions">
                    <button onclick="confirmTransfer()">Transfer</button>
                    <button onclick="closeTransferDialog()">Cancel</button>
                </div>
            </div>
        </div>
        <?php
    }
}