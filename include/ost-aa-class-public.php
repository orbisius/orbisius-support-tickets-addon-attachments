<?php
new Orbisius_Support_Tickets_Attachments_Addon_Public();

class Orbisius_Support_Tickets_Attachments_Addon_Public {

    private $ticket_folder_dir;
    private $request_obj;
    private $user_obj;
    private $ticket_obj;

    function __construct() {
        $this->request_obj = Orbisius_Support_Tickets_Request::getInstance();
        $this->user_obj = Orbisius_Support_Tickets_User::getInstance();
        $this->ticket_obj = Orbisius_Support_Tickets_Module_Core_CPT::getInstance();
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
     * Process the attachments files.
     * 
     * @param type $ctx Context
     * @return boolean|string While doing ajax return true is success or string with the exception message is fail
     * @throws Exception
     * @since 1.0.0
     */
    public function process_attachments_files($ctx) {
        $attachments_data = isset($_FILES["orbisius_support_tickets_data_attachments"]) ? $_FILES["orbisius_support_tickets_data_attachments"] : array();
        if (!empty($attachments_data['tmp_name'][0])) { //Check if there are attachment files submitted
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

                $this->ticket_folder_dir = ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR . $deep_folder;

                if (!is_dir($this->ticket_folder_dir)) {
                    if (!wp_mkdir_p($this->ticket_folder_dir)) {
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

                if (!function_exists('media_handle_upload')) {
                    require_once(ABSPATH . "wp-admin" . '/includes/image.php'); // required to process image files type
                    require_once(ABSPATH . "wp-admin" . '/includes/file.php');
                    require_once(ABSPATH . "wp-admin" . '/includes/media.php');
                }

                foreach ($attachments_data['tmp_name'] as $key => $temp_file_path) {
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
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    return true;
                }
            } catch (Exception $ex) {
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    return $ex->getMessage();
                } else {
                    wp_die($ex->getMessage());
                }
            }
        }
    }

    /**
     * Modify the upload dir
     * 
     * @param type $path File temporal path
     * @return string New upload dir path
     */
    public function custom_upload_dir($path) {
        $path['basedir'] = WP_CONTENT_DIR;
        $path['baseurl'] = WP_CONTENT_URL;
        $path['subdir'] = str_replace(WP_CONTENT_DIR, "", $this->ticket_folder_dir);
        $path['path'] = $path['basedir'] . $path['subdir'];
        $path['url'] = $path['baseurl'] . $path['subdir'];
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
     * Show the tickets list and the new attachment ticket form after the ticket meta and before the ticket content.
     * 
     * @param type $ctx Context
     * @return type null|string Null if user don't have access, HTML string otherwise.
     */
    public function show_ticket_attachments($ctx) {
        $ticket_id = $ctx['ticket_id'];
        if (!$this->user_have_access($ticket_id)) {
            return null;
        }
        $attachments = $this->get_all_ticket_attachments($ticket_id);
        wp_enqueue_script(ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN . "-ticket-actions");
        if (!empty($attachments)) {
            ?>
            <div class="ticket_attachments_wrapper">
                <strong><?php _e('Ticket Attachments:', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN); ?></strong>
                <ul>
                    <?php
                    foreach ($attachments as $attachment) {
                        $download_url = admin_url('admin-ajax.php?action=orbisius_support_tickets_action_download_file&id=' . $attachment->ID . '&=ticket-id=', $ticket_id);
                        echo sprintf('<li>'
                                . '<a class="ticket_attachment_download" href="%4$s" target="_blank" download>%1$s</a> '
                                . '<a class="ticket_attachment_delete" href="#" data-id="%3$s" data-ticket-id="%5$s">%2$s</a>'
                                . '</li>',
                                $attachment->post_title,
                                __('Delete File', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN),
                                $attachment->ID,
                                wp_nonce_url($download_url, "orbisius_support_tickets_action_download_file", "download_nonce"),
                                $ticket_id
                        );
                    }
                    ?>
                </ul>                
            </div>
            <?php
        }
        //don't show add new attachments form if ticket is closed
        if ($this->ticket_obj->getStatus($ticket_id) !== Orbisius_Support_Tickets_Module_Core_CPT::STATUS_CLOSED) {
            ?>
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
            <?php
        }
    }

    /**
     * Get all attachments of a ticket post
     * 
     * @param type $ticket_id Id of the post
     * @return type Posts array All attachment objects
     * @since 1.0.0
     */
    public function get_all_ticket_attachments($ticket_id) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_parent' => $ticket_id,
        ));
        return $attachments;
    }

    /**
     *  Handles the Ajax download of ticket attachment files.
     * 
     * @since 1.0.0
     */
    public function download_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_download_file", "download_nonce")) {
            $ticket_id = intval($this->request_obj->get('ticket-id'));
            if (!$this->user_have_access($ticket_id)) {
                $this->send_json_response(0);
            }

            $attachment_id = intval($this->request_obj->get('id'));
            add_filter('upload_dir', array($this, 'custom_upload_dir'));
            $file_path = get_attached_file($attachment_id, true);
            remove_filter('upload_dir', array($this, 'custom_upload_dir'));

            // When safe mode is enabled: Warning: set_time_limit(): Cannot set max execution time limit due to system policy in ...
            @set_time_limit(12 * 3600); // 12 hours
            if (ini_get('zlib.output_compression')) {
                @ini_set('zlib.output_compression', 0);
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', 1);
                }
            }
            if (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)) {
                header("Cache-control: private");
                header('Pragma: private');
                // IE 6.0 fix for SSL
                // SRC http://ca3.php.net/header
                // Brandon K [ brandonkirsch uses gmail ] 25-Apr-2007 03:34
                header('Cache-Control: maxage=3600'); //Adjust maxage appropriately
            } else {
                header('Pragma: public');
            }
            // the actual file that will be downloaded
            $download_file_name = basename($file_path);
            $content_type = get_post_mime_type($attachment_id);
            if (!$content_type) {
                $default_content_type = 'application/octet-stream';
                $get_ext_splits = explode('.', $download_file_name);
                $ext = end($get_ext_splits);
                $ext = strtolower($ext);
                // http://en.wikipedia.org/wiki/Internet_media_type
                $content_types_array = array(
                    'pdf' => 'application/pdf',
                    'exe' => 'application/octet-stream',
                    'zip' => 'application/zip',
                    'gzip' => 'application/gzip',
                    'gz' => 'application/x-gzip',
                    'z' => 'application/x-compress',
                    'cer' => 'application/x-x509-ca-cert',
                    'vcf' => 'application/text/x-vCard',
                    'vcard' => 'application/text/x-vCard',
                    // doc
                    "tsv" => "text/tab-separated-values",
                    "txt" => "text/plain",
                    'dot' => 'application/msword',
                    'rtf' => 'application/msword',
                    'doc' => 'application/msword',
                    'docx' => 'application/msword',
                    'xls' => 'application/vnd.xls',
                    'xlsx' => 'application/vnd.ms-excel',
                    'csv' => 'application/vnd.ms-excel',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.ms-powerpoint',
                    'mdb' => 'application/x-msaccess',
                    'mpp' => 'application/vnd.ms-project',
                    'js' => 'text/javascript',
                    'css' => 'text/css',
                    'htm' => 'text/html',
                    'html' => 'text/html',
                    // images
                    'gif' => 'image/gif',
                    'png' => 'image/png',
                    'jpg' => 'image/jpg',
                    'jpeg' => 'image/jpg',
                    'jfif' => 'image/pipeg',
                    'jpe' => 'image/jpeg',
                    'bmp' => 'image/bmp',
                    'ics' => 'text/calendar',
                    // audio & video
                    'au' => 'audio/basic',
                    'mid' => 'audio/mid',
                    'mp3' => 'audio/mpeg',
                    'avi' => 'video/x-msvideo',
                    'mp4' => 'video/mp4',
                    'mp2' => 'video/mpeg',
                    'mpa' => 'video/mpeg',
                    'mpe' => 'video/mpeg',
                    'mpeg' => 'video/mpeg',
                    'mpg' => 'video/mpeg',
                    'mpv2' => 'video/mpeg',
                    'mov' => 'video/quicktime',
                    'movie' => 'video/x-sgi-movie',
                );
                $content_type = empty($content_types_array[$ext]) ? $default_content_type : $content_types_array[$ext];
            }
            header('Expires: 0');
            header('Content-Description: File Transfer');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Content-Type: ' . $content_type);
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . (string) (filesize($file_path)));
            header('Content-Disposition: attachment; filename="' . $download_file_name . '"');
            ob_clean();
            flush();
            readfile($file_path);
        }
    }

    /**
     * Handles the Ajax remove of ticket attachment files.
     * 
     * @since 1.0.0
     */
    public function delete_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_delete_file")) {
            $ticket_id = intval($this->request_obj->get('ticket-id'));
            if (!$this->user_have_access($ticket_id)) {
                $this->send_json_response(0, __('You do not have access to this ticket.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN));
            }
            $attachment_id = intval($this->request_obj->get('id'));
            if (!$attachment_id) {
                $this->send_json_response(0, _('Invalid attachment ID.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN));
            }
            add_filter('upload_dir', array($this, 'custom_upload_dir'));
            $delete_file = wp_delete_attachment($attachment_id, true);
            remove_filter('upload_dir', array($this, 'custom_upload_dir'));
            if ($delete_file === false || $delete_file === null) {
                $this->send_json_response(0, __('Error when trying to delete ticket attachment.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN));
            }
            do_action('orbisius_support_tickets_filter_submit_ticket_form_after_delete_file', $attachment_id);
            $this->send_json_response(1, __('The attachment file have succesfully deleted.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN));
        }
    }

    /**
     * Handle the Ajax upload of ticket attachments files. 
     * 
     * @since 1.0.0
     */
    public function add_attachment_file() {
        if (check_ajax_referer("orbisius_support_tickets_action_new_file")) {
            $ctx['ticket_id'] = intval($this->request_obj->get('ticket_id'));
            if (!$this->user_have_access($ctx['ticket_id'])) {
                $this->send_json_response(0, __('You do not have access to this ticket.', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_TX_DOMAIN));
            }
            $result = $this->process_attachments_files($ctx);
            if ($result === true) {
                $this->send_json_response(1);
            } else {
                $this->send_json_response(0, $result);
            }
        }
    }

    /**
     * Send a JSON response back to an Ajax request with a specify format
     * 
     * @param type $status (0 for error and 1 for success).
     * @param type $message Message, default empty string.
     * @param type $data Additional data, default empty array.
     * @since 1.0.0
     */
    public function send_json_response($status, $message = '', $data = []) {
        wp_send_json(array(
            'status' => $status,
            'message' => $message,
            'data' => $data
        ));
    }

    /**
     * Check is user have access to the ticket. 
     * 
     * User must be logged in and be the ticket author, support representative or administrator.
     * 
     * @return boolean 
     * @since 1.0.0
     */
    public function user_have_access($ticket_id = 0) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user_id = get_current_user_id();
        if ($this->user_obj->isSupportRep($user_id) || $this->user_obj->isAdmin($user_id)) {
            return true;
        }
        $ticket_author_id = intval(get_post_field('post_author', $ticket_id));
        if ($ticket_author_id === $user_id) {
            return true;
        } else {
            return false;
        }
    }

}
