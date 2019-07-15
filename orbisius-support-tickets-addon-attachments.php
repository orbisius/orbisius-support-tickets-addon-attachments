<?php

/*
  Plugin Name: Orbisius Support Tickets Attachments Addon
  Plugin URI: https://orbisius.com/products/wordpress-plugins/orbisius-support-tickets
  Description: Minimalistic support ticket system that enables you to start providing awesome support in 2 minutes.
  Version: 1.0.0
  Author: Svetoslav Marinov (Slavi) | Orbisius.com
  Author URI: http://orbisius.com
  Text Domain: orbisius_support_tickets_attachments_addon
  Domain Path: /lang
 */

define("ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_BASE_PLUGIN", __FILE__);
define("ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_BASE_DIR", dirname(__FILE__));
define("ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_BASE_URL", plugins_url('', ORBISIUS_SUPPORT_TICKETS_ATTACHMENTS_ADDON_BASE_PLUGIN));

/**
 * Plugin init. Check is Orbisius_Support_Tickets is active.
 * 
 * @since 1.0.0
 */
function ost_aa_init() {
    if (class_exists('Orbisius_Support_Tickets_Module_Core_CPT')) {
        
    }
}

add_action('plugins_loaded', 'ost_aa_init');
