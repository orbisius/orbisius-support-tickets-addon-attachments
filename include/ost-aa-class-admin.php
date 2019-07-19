<?php

new Orbisius_Support_Tickets_Attachments_Addon_Admin();

class Orbisius_Support_Tickets_Attachments_Addon_Admin {
    function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
    }

    public function admin_init() {
    }
}
