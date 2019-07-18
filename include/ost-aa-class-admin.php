<?php

class Orbisius_Support_Tickets_Attachments_Addon_Admin {

    function __construct() {
        add_action('admin_init', array($this, 'init'));
    }

    public function init() {
        $this->create_attachments_files_directory();
    }

    public function create_attachments_files_directory() {
        if (!is_dir(ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR)) {
            try {
                if (!wp_mkdir_p(ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_FILES_DIR)) {
                    throw new Exception("Error creating the support tickets main folder");
                }
            } catch (Exception $ex) {
                wp_die();
            }
        }
    }

}

new Orbisius_Support_Tickets_Attachments_Addon_Admin();
