<?php

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_action('orbisius_support_tickets_action_submit_ticket_form_footer', array($this, 'add_attachment_field_to_ticket_form'));
    }

    /**
     * Add new field to the submit ticket form. 
     * Include filter "orbisius_support_tickets_filter_file_types_allowed" to modify the allowed file types
     * 
     * @since 1.0.0.
     */
    public function add_attachment_field_to_ticket_form() {
        ?>
        <div class="form-group">
            <label class="col-md-3 control-label" for="orbisius_support_tickets_data_attachments">
                <?php _e('Ticket Attachments', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></label>
            <div class="col-md-9">
                <input type="file" name="orbisius_support_tickets_data_attachments" multiple 
                       accept="<?php echo apply_filters('orbisius_support_tickets_filter_file_types_allowed', "image/*,.pdf,.txt"); ?>"/>
            </div>
        </div>
        <?php
    }

}

new Orbisius_Support_Tickets_Attachments_Addon_Public();
