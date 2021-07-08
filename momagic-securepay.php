<?php
/**
 *  Plugin Name: MagicWay Payment Gateway
 *  Plugin URI: https://wordpress.org/plugins/magicway-payment-gateway
 *  Description: Customers will be able to buy products online using their Visa Cards, Master cards, MFS, etc. via MagicWay Payment Gateway
 *  Version: 1.0.0
 *  Stable tag: 1.0.0
 *  Requires at least: 4.0.1
 *  tested up to: 5.7.1
 *  Author: MagicWay
 *  Author URI: https://profiles.wordpress.org/magicway
 *  Author Email: info@momagicbd.com
 *  License: GNU General Public License v3.0
 *  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 **/
/**
 * @package MagicWay_Payment_Gateway
 */


if (!defined('ABSPATH')) die('You can\'t directly access this file'); // Exit if accessed directly

define('MAGICWAY_PATH', plugin_dir_path(__FILE__));
define('MAGICWAY_PLUGIN_VERSION', '1.0.0');

global $plugin_slug;
$plugin_slug = 'magicway';

add_action("plugins_loaded", "woocommerce_magicway_init", 0);


/**
 * Hook plugin activation
 */
register_activation_hook(__FILE__, 'MagicwayPaymentActivator');
function MagicwayPaymentActivator()
{
    $installed_version = get_option("magicway_easy_version");
    if ($installed_version === MAGICWAY_PLUGIN_VERSION) {
        return true;
    }
    update_option('magicway_easy_version', MAGICWAY_PLUGIN_VERSION);
}

/**
 * Hook plugin deactivation
 */
register_deactivation_hook(__FILE__, 'MagicwayPaymentDeactivator');
function MomagicSecurepayDeactivator()
{
}

function woocommerce_magicway_init()
{
    require_once(MAGICWAY_PATH . 'lib/magicway-class.php');

    function add_payment_gateway_class($methods)
    {
        $methods[] = 'Magicway_Payment';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_payment_gateway_class');

    function magicway_settings_link($links)
    {
        $pluginLinks = array(
            'settings' => '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=magicway')) . '">Settings</a>',
            'support' => '<a href="mailto:info@momagicbd.com">Support</a>'
        );

        $links = array_merge($links, $pluginLinks);

        return $links;
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'magicway_settings_link');

    /**
     *  Add Custom Icon
     */
    function magicway_gateway_icon($icon, $id)
    {
        if ($id === 'magicway') {
            return '<img src="' . plugins_url('images/mmbd-verified.png', __FILE__) . '" > ';
        } else {
            return $icon;
        }
    }
    add_filter('woocommerce_gateway_icon', 'magicway_gateway_icon', 10, 2);
}
