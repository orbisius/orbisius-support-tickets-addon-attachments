<?php

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_action('orbisius_support_tickets_action_submit_ticket_form_before_submit_button', array($this, 'add_attachment_field_to_ticket_form'));
        add_action('orbisius_support_tickets_action_submit_ticket_after_insert', array($this, 'process_attachments_files'), 10, 1);
        add_action('orbisius_support_tickets_view_ticket_before_ticket_content_wrapper', array($this, 'show_ticket_attachments'), 10, 1);
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
                <input type="file"
                       id="orbisius_support_tickets_data_attachments" 
                       class="form-control orbisius_support_tickets_data_attachments orbisius_support_tickets_full_width"
                       name="orbisius_support_tickets_data_attachments[]" 
                       multiple 
                       accept="<?php echo apply_filters('orbisius_support_tickets_filter_submit_ticket_form_file_types_allowed', "image/*,.pdf,.txt"); ?>"/>
            </div>
        </div>
        <?php
    }

    public function process_attachments_files($ctx) {
        $attachments_data = isset($_FILES["orbisius_support_tickets_data_attachments"]) ? $_FILES["orbisius_support_tickets_data_attachments"] : array();
        if ($attachments_data['tmp_name'][0] !== "") {
            try {
                if (!isset($ctx['ticket_id'])) {
                    throw new Exception("Error inserting the ticket post");
                }
                if (!$this->check_attachment_files_error($attachments_data)) {
                    throw new Exception("Error uploading the files");
                }
                if (!$this->check_attachment_files_max_size($attachments_data)) {
                    throw new Exception("Error file size limit");
                }

                $attachments = array();
                $hash = md5($ctx['ticket_id']);
                $deep_folder = substr($hash, 0, 1) . "/" . substr($hash, 1, 1) . "/" . substr($hash, 2, 1) . "/" . $ctx['ticket_id'] . "/";
                $ticket_folder_path = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . $deep_folder;

                if (!wp_mkdir_p($ticket_folder_path)) {
                    throw new Exception("Error creating the ticket folder");
                }

                foreach ($attachments_data['tmp_name'] as $key => $temp_file_path) {
                    $new_file_name = str_replace(" ", "_", $attachments_data['name'][$key]);
                    $new_file_path = $ticket_folder_path . $new_file_name;
                    if (move_uploaded_file($temp_file_path, $new_file_path)) {
                        $new_file_url = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_URL . $deep_folder . $new_file_name;
                        $attachments[$key]['url'] = $new_file_url;
                        $attachments[$key]['path'] = $new_file_path;
                        $attachments[$key]['name'] = $attachments_data['name'][$key];
                    } else {
                        throw new Exception("Error moving the ticket attachments to the ticket folder");
                    }
                }

                if (update_post_meta($ctx['ticket_id'], '_ticket_attachments', $attachments)) {
                    do_action('orbisius_support_tickets_filter_submit_ticket_form_after_upload_files', $attachments);
                } else {
                    throw new Exception("Error moving the ticket attachments to the ticket folder");
                }
            } catch (Exception $ex) {
                wp_die($ex->getMessage());
            }
        }
    }

    public function check_attachment_files_error($attachments_data) {
        foreach ($attachments_data['error'] as $error) {
            if ($error) {
                return 0;
            }
        }
        return 1;
    }

    public function check_attachment_files_max_size($attachments_data) {
        $limit_size = apply_filters('orbisius_support_tickets_filter_submit_ticket_form_file_limit_size', 5000000);
        foreach ($attachments_data['size'] as $size) {
            if ($size > $limit_size) {
                return 0;
            }
        }
        return 1;
    }

    public function show_ticket_attachments($ctx) {
        $attachments = get_post_meta($ctx['ticket_id'], "_ticket_attachments", true);
        if (!empty($attachments)) {
            ?>
            <div class="ticket_attachments_wrapper">
                <strong><?php _e('Ticket Attachments:', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></strong>
                <ul>
                    <?php
                    foreach ($attachments as $key => $attachment) {
                        echo sprintf('<li><a href="%2$s" target="_blank">%1$s</a> <a href="#">%3$s</a></li>',
                                $attachment['name'],
                                $attachment['url'],
                                __('Delete File', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN)
                        );
                    }
                    ?>
                </ul>
            </div>
            <?php
        }
    }

}

new Orbisius_Support_Tickets_Attachments_Addon_Public();
