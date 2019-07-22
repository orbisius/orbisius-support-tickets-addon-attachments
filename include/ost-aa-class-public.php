<?php
new Orbisius_Support_Tickets_Attachments_Addon_Public();

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    private $ticket_folder_path;

    function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_orbisius_support_tickets_action_download_file', array($this, 'download_attachment_file'));
        add_action('wp_ajax_orbisius_support_tickets_action_delete_file', array($this, 'delete_attachment_file'));
        add_action('wp_ajax_orbisius_support_tickets_action_new_file', array($this, 'add_attachment_file'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function init() {
        add_action('orbisius_support_tickets_action_submit_ticket_form_before_submit_button', array($this, 'add_attachment_field_to_ticket_form'));
        add_action('orbisius_support_tickets_action_submit_ticket_after_insert', array($this, 'process_attachments_files'));
        add_action('orbisius_support_tickets_view_ticket_before_ticket_content_wrapper', array($this, 'show_ticket_attachments'));
    }

    public function enqueue_scripts() {
        wp_register_script(
                ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN . "-ticket-actions",
                ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_BASE_URL . "/assets/js/ticket-attachments-actions.js",
                array('jquery'),
                ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_VERSION,
                true
        );
        wp_localize_script(
                ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN . "-ticket-actions",
                "OST_AA",
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'download_nonce' => wp_create_nonce("orbisius_support_tickets_action_download_file"),
                    'delete_nonce' => wp_create_nonce("orbisius_support_tickets_action_delete_file"),
                )
        );
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
                <?php _e('Add Ticket Attachments', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></label>
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

    /**
     * Process the attachments files
     * 
     * @param type $ctx
     * @throws Exception
     */
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
                $ticket_id = $ctx['ticket_id'];
                $hash = md5($ticket_id);
                $deep_folder = substr($hash, 0, 1) . "/"
                        . substr($hash, 1, 1) . "/"
                        . substr($hash, 2, 1) . "/"
                        . $ticket_id;

                $this->ticket_folder_path = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_SUBDIR . $deep_folder;

                if (!is_dir($this->ticket_folder_path)) {
                    if (!wp_mkdir_p($this->ticket_folder_path)) {
                        throw new Exception("Error creating the ticket folder");
                    }

                    $htaccess_file = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . '.htaccess';

                    if (!file_exists($htaccess_file)) {
                        file_put_contents($htaccess_file, 'deny from all', LOCK_EX);
                    }

                    $index_file = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . 'index.html';

                    if (!file_exists($index_file)) {
                        touch($index_file); // doesn't need content. an empty file prevents file list if enabled.
                    }
                }

                foreach ($attachments_data['tmp_name'] as $key => $temp_file_path) {

                    if (!function_exists('media_handle_upload')) {
                        require_once(ABSPATH . "wp-admin" . '/includes/image.php'); // required to process image files type
                        require_once(ABSPATH . "wp-admin" . '/includes/file.php');
                        require_once(ABSPATH . "wp-admin" . '/includes/media.php');
                    }

                    $file_array['tmp_name'] = $temp_file_path;
                    $file_array['name'] = $attachments_data['name'][$key];
                    $file_array['type'] = $attachments_data['type'][$key];
                    $file_array['error'] = $attachments_data['error'][$key];
                    $file_array['size'] = $attachments_data['size'][$key];

                    // do the validation and storage stuff
                    add_filter('upload_dir', array($this, 'custom_upload_dir'));
                    $attachment_id = media_handle_sideload($file_array, $ticket_id);
                    remove_filter('upload_dir', array($this, 'custom_upload_dir'));

                    // If error storing permanently, unlink
                    if (is_wp_error($attachment_id)) {
                        unlink($file_array['tmp_name']);
                        throw new Exception("Error storing the ticket attachment");
                    }

                    do_action('orbisius_support_tickets_filter_submit_ticket_form_after_upload_file', $attachment_id);
                }
            } catch (Exception $ex) {
                wp_die($ex->getMessage());
            }
        }
    }

    /**
     * 
     * @param type $path
     * @return string
     */
    public function custom_upload_dir($path) {
        $path['basedir'] = WP_CONTENT_DIR;
        $path['baseurl'] = WP_CONTENT_URL;
        $path['subdir'] = $this->ticket_folder_path;
        $path['path'] = $path['basedir'] . $this->ticket_folder_path;
        $path['url'] = $path['baseurl'] . $this->ticket_folder_path;
        return $path;
    }

    /**
     * Check all files selected on the input file were uploaded successfully.
     * 
     * @param type $attachments_data
     * @return int
     */
    public function check_attachment_files_error($attachments_data) {
        foreach ($attachments_data['error'] as $error) {
            if ($error) {
                return 0;
            }
        }
        return 1;
    }

    /**
     * Check all files selected on the input file don't are larger than the limit size
     * 
     * @param type $attachments_data
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
        $ticket_id = $ctx['ticket_id'];
        $attachments = $this->get_all_ticket_attachments($ticket_id);
        if (!empty($attachments)) {
            wp_enqueue_script(ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN . "-ticket-actions");
            ?>
            <div class="ticket_attachments_wrapper">
                <strong><?php _e('Ticket Attachments:', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></strong>
                <ul>
                    <?php
                    foreach ($attachments as $attachment) {
                        echo sprintf('<li>'
                                . '<a class="ticket_attachment_download" href="#" data-id="%4$s">%1$s</a> '
                                . '<a class="ticket_attachment_delete" href="#" data-id="%4$s">%3$s</a>'
                                . '</li>',
                                $attachment->post_title,
                                $attachment->guid,
                                __('Delete File', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN),
                                $attachment->ID
                        );
                    }
                    ?>
                </ul>
                <form method="POST" id="orbisius_support_tickets_attachments_form" data-id="<?php echo $ticket_id; ?>">
                    <?php
                    wp_nonce_field('orbisius_support_tickets_action_new_file');
                    $this->add_attachment_field_to_ticket_form();
                    ?>
                    <div class="form-group">
                        <div class="col-md-12 text-right">
                            <button type="submit"
                                    id="orbisius_support_tickets_attachments_form_submit"
                                    name="orbisius_support_tickets_attachments_form_submit"
                                    class="orbisius_support_tickets_attachments_form_submit btn btn-primary">
                                <?php _e('Submit', 'orbisius_support_tickets'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        }
    }

    /**
     * 
     * @param type $ticket_id
     * @return type Posts array
     */
    public function get_all_ticket_attachments($ticket_id) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_parent' => $ticket_id,
            'exclude' => get_post_thumbnail_id($ticket_id)
        ));
        return $attachments;
    }

    public function download_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_download_file")) {
            /* $attachment_id = $_POST['id'];
              $file_path = str_replace("uploads/", "", get_attached_file($attachment_id));
              $mimetype = get_post_mime_type($attachment_id);
              header("Content-Type: " . $mimetype);
              echo readfile($file_path);
              wp_die(); */
        }
    }

    public function delete_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_delete_file")) {
            $attachment_id = $_POST['id'];
            $file_path = str_replace("uploads/", "", get_attached_file($attachment_id));
            if (wp_delete_attachment($attachment_id, true)) {
                wp_delete_file($file_path);
                do_action('orbisius_support_tickets_filter_submit_ticket_form_after_delete_file', $attachment_id);
                echo 'OK';
            } else {
                echo __('Error when trying to delete ticket attachment.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN);
            }
            wp_die();
        }
    }

    public function add_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_new_file")) {
            $ctx['ticket_id'] = $_POST['ticket_id'];
            $this->process_attachments_files($ctx);
            wp_die();
        }
    }

}
