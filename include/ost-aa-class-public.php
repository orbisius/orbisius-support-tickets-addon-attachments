<?php

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_action('orbisius_support_tickets_action_submit_ticket_form_footer', array($this, 'add_attachment_field_to_ticket_form'));
        add_action('orbisius_support_tickets_action_before_submit_ticket_before_upsert', array($this, 'process_attachments_files'), 10, 1);
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
                       accept="<?php echo apply_filters('orbisius_support_tickets_filter_file_types_allowed', "image/*,.pdf,.txt"); ?>"/>
            </div>
        </div>
        <?php
    }

    public function process_attachments_files($ctx) {
        $attachments_data = isset($_FILES["orbisius_support_tickets_data_attachments"]) ? $_FILES["orbisius_support_tickets_data_attachments"] : array();
        if (count($attachments_data)) {
            if ($this->check_attachment_files_error($attachments_data)) {
                if ($this->check_attachment_files_max_size($attachments_data)) {
                    $ctx['data']['attachments'] = array();
                    $ticket_attachment_folder_name = md5(current_time("mysql"));
                    $folder_path = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . $ticket_attachment_folder_name . "/";
                    if (wp_mkdir_p($folder_path)) {
                        foreach ($attachments_data['tmp_name'] as $key => $temp_file_path) {
                            $file_extension = array_reverse(explode('.', $attachments_data['name'][$key]))[0];
                            $new_file_name = md5($temp_file_path) . '.' . $file_extension;
                            $new_file_path = $folder_path . $new_file_name;
                            if (move_uploaded_file($temp_file_path, $new_file_path)) {
                                $new_file_url = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_URL . $ticket_attachment_folder_name . "/" . $new_file_name;
                                $ctx['data']['attachments'][] = $new_file_url;
                            } else {
                                
                            }
                        }
                    } else {
                        // Throw error at create ticket folder attachments
                    }
                } else {
                    // Throw error max size files
                }
            } else {
                // Throw error at upload files
            }
        } else {
            //No files uploaded in the form
        }
        die(var_dump($ctx['data']['attachments']));
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
        foreach ($attachments_data['size'] as $size) {
            if ($size > 5000000) {
                return 0;
            }
        }
        return 1;
    }

}

new Orbisius_Support_Tickets_Attachments_Addon_Public();
