<?php

new Orbisius_Support_Tickets_Attachments_Addon_Public();

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_action('orbisius_support_tickets_action_submit_ticket_form_before_submit_button', array($this, 'add_attachment_field_to_ticket_form'));
        add_action('orbisius_support_tickets_action_submit_ticket_after_insert', array($this, 'process_attachments_files'));
        add_action('orbisius_support_tickets_view_ticket_before_ticket_content_wrapper', array($this, 'show_ticket_attachments'));
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
                if (empty($ctx['ticket_id'])) {
                    throw new Exception("Missing ticket id");
                }

                if (!$this->check_attachment_files_error($attachments_data)) {
                    throw new Exception("Error uploading the ticket attachments");
                }

                if (!$this->check_attachment_files_max_size($attachments_data)) {
                    throw new Exception("Error file size limit");
                }

                $attachments = array();
                $hash = md5($ctx['ticket_id']);
                $deep_folder = substr($hash, 0, 1) . "/"
                               . substr($hash, 1, 1) . "/"
                               . substr($hash, 2, 1) . "/"
                               . $ctx['ticket_id'] . "/";

                $ticket_folder_path = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . $deep_folder;

                if (!is_dir($ticket_folder_path)) {
	                if ( ! wp_mkdir_p( $ticket_folder_path ) ) {
		                throw new Exception( "Error creating the ticket folder" );
	                }

	                $htaccess_file = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . '/.htaccess';

	                if (!file_exists($htaccess_file)) {
	                    file_put_contents($htaccess_file, 'deny from all', LOCK_EX);
                    }

	                $index_file = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . '/index.html';

	                if (!file_exists($index_file)) {
	                    touch($index_file); // doesn't need content. an empty file prevents file list if enabled.
                    }
                }

                foreach ($attachments_data['tmp_name'] as $key => $temp_file_path) {
                    $new_file_name = str_replace(" ", "_", $attachments_data['name'][$key]);
                    $new_file_path = str_replace("\\", '/', $ticket_folder_path . $new_file_name);

                    if (move_uploaded_file($temp_file_path, $new_file_path) || copy($temp_file_path, $new_file_path)) {
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

	/**
	 * @param $attachments_data
	 * @return int
	 */
    public function check_attachment_files_max_size($attachments_data) {
        $limit_size = apply_filters('orbisius_support_tickets_filter_submit_ticket_form_file_limit_size', 5000000);
        foreach ($attachments_data['size'] as $size) {
            if ($size > $limit_size) {
                return 0;
            }
        }
        return 1;
    }

	/**
	 * @param $ctx
	 */
    public function show_ticket_attachments($ctx) {
        $attachments = get_post_meta($ctx['ticket_id'], "_ticket_attachments", true);
        if (isset($_REQUEST['delete_file'])) {
            $attachments = $this->delete_attachment_file($ctx, $attachments);
        }
        if (!empty($attachments)) {
            ?>
            <div class="ticket_attachments_wrapper">
                <strong><?php _e('Ticket Attachments:', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></strong>
                <ul>
                    <?php
                    foreach ($attachments as $key => $attachment) {
                        $parameters = array_merge($_REQUEST, array('delete_file' => $key));
                        $delete_url = esc_url(add_query_arg($parameters, get_permalink()));
                        echo sprintf('<li><a href="%2$s" target="_blank">%1$s</a> <a href="%5$s" data-id="%4$s">%3$s</a></li>',
                                $attachment['name'],
                                $attachment['url'],
                                __('Delete File', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN),
                                $key,
                                $delete_url
                        );
                    }
                    ?>
                </ul>
            </div>
            <?php
        }
    }

    public function delete_attachment_file($ctx, $attachments) {
        $file_id = $_REQUEST['delete_file'];
        $delete_file_path = $attachments[$file_id]['path'];

        try {
            if (!unlink($delete_file_path)) {
	            throw new Exception( "Error deleting file attachment" );
            }

            unset($attachments[$file_id]);
            update_post_meta($ctx['ticket_id'], "_ticket_attachments", $attachments);
            do_action('orbisius_support_tickets_filter_submit_ticket_form_after_delete_file', $attachments);
        } catch (Exception $ex) {
            wp_die($ex->getMessage());
        }

        return $attachments;
    }
}

