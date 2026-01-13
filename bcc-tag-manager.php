<?php
/*
Plugin Name: BCC Tag Manager
Plugin URI: https://bigcornercreative.com
Description: Install Google Tag Manager into &lt;head&gt; and &lt;body&gt;. Works with Avada, Salient, and standard WordPress themes.
Version: 2.1.0
Author: Big Corner Creative
Author URI: https://bigcornercreative.com
Text Domain: bcc-tag-manager
Domain Path: /languages
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version constant
define( 'BCC_GTM_VERSION', '2.1.0' );
define( 'BCC_GTM_OPTION_NAME', 'bcc_gtm_id' );

/**
 * Initialize the plugin update checker
 */
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/BigCornerCreative/bcc-tag-manager', // Your GitHub repo URL
	__FILE__,
	'bcc-tag-manager'
);

// Set the branch that contains the stable release
$myUpdateChecker->setBranch('main');

/**
 * Activation hook
 */
function bcc_gtm_activate() {
	// Nothing needed on activation for now
}
register_activation_hook( __FILE__, 'bcc_gtm_activate' );

/**
 * Deactivation hook
 */
function bcc_gtm_deactivate() {
	// Clean up transients or temporary data
	delete_transient( 'bcc_gtm_admin_notice' );
}
register_deactivation_hook( __FILE__, 'bcc_gtm_deactivate' );

/**
 * Uninstall hook - clean up all plugin data
 */
function bcc_gtm_uninstall() {
	delete_option( BCC_GTM_OPTION_NAME );
	delete_option( 'bcc_gtm_dismiss_notice' );
}
register_uninstall_hook( __FILE__, 'bcc_gtm_uninstall' );

/**
 * Display admin notice if GTM ID is not configured
 */
function bcc_gtm_admin_notice() {
	$gtm_id = get_option( BCC_GTM_OPTION_NAME );
	$dismissed = get_option( 'bcc_gtm_dismiss_notice' );
	
	// Only show on admin pages, if not configured, and not dismissed
	if ( empty( $gtm_id ) && ! $dismissed && current_user_can( 'manage_options' ) ) {
		?>
		<div class="notice notice-warning is-dismissible" data-notice="bcc-gtm-config">
			<p>
				<strong>BCC Tag Manager:</strong> 
				Please <a href="<?php echo admin_url( 'options-general.php?page=bcc-tag-manager' ); ?>">configure your Google Tag Manager ID</a> to start tracking.
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$(document).on('click', '[data-notice="bcc-gtm-config"] .notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'bcc_gtm_dismiss_notice',
					nonce: '<?php echo wp_create_nonce( 'bcc_gtm_dismiss' ); ?>'
				});
			});
		});
		</script>
		<?php
	}
}
add_action( 'admin_notices', 'bcc_gtm_admin_notice' );

/**
 * AJAX handler to dismiss admin notice
 */
function bcc_gtm_dismiss_notice_handler() {
	check_ajax_referer( 'bcc_gtm_dismiss', 'nonce' );
	
	if ( current_user_can( 'manage_options' ) ) {
		update_option( 'bcc_gtm_dismiss_notice', true );
	}
	
	wp_die();
}
add_action( 'wp_ajax_bcc_gtm_dismiss_notice', 'bcc_gtm_dismiss_notice_handler' );

/**
 * Add settings page to WordPress admin menu
 */
function bcc_gtm_add_admin_menu() {
	add_options_page(
		'BCC Tag Manager Settings',
		'BCC Tag Manager',
		'manage_options',
		'bcc-tag-manager',
		'bcc_gtm_settings_page'
	);
}
add_action( 'admin_menu', 'bcc_gtm_add_admin_menu' );

/**
 * Register plugin settings
 */
function bcc_gtm_settings_init() {
	register_setting( 'bcc_gtm_settings_group', BCC_GTM_OPTION_NAME, array(
		'sanitize_callback' => 'bcc_gtm_sanitize_id'
	) );

	add_settings_section(
		'bcc_gtm_settings_section',
		'Google Tag Manager Configuration',
		'bcc_gtm_settings_section_callback',
		'bcc_gtm_settings_group'
	);

	add_settings_field(
		'bcc_gtm_id',
		'GTM Container ID',
		'bcc_gtm_id_render',
		'bcc_gtm_settings_group',
		'bcc_gtm_settings_section'
	);
}
add_action( 'admin_init', 'bcc_gtm_settings_init' );

/**
 * Sanitize GTM ID input
 */
function bcc_gtm_sanitize_id( $input ) {
	$input = sanitize_text_field( $input );
	
	// Validate GTM ID format
	if ( ! empty( $input ) && ! preg_match( '/^GTM-[A-Z0-9]+$/', $input ) ) {
		add_settings_error(
			BCC_GTM_OPTION_NAME,
			'invalid_gtm_id',
			'Please enter a valid GTM Container ID (format: GTM-XXXXXXX)',
			'error'
		);
		return get_option( BCC_GTM_OPTION_NAME );
	}
	
	// Clear the dismiss notice flag when ID is saved
	if ( ! empty( $input ) ) {
		delete_option( 'bcc_gtm_dismiss_notice' );
	}
	
	return $input;
}

/**
 * Render GTM ID input field
 */
function bcc_gtm_id_render() {
	$gtm_id = get_option( BCC_GTM_OPTION_NAME );
	?>
	<input type="text" name="<?php echo BCC_GTM_OPTION_NAME; ?>" value="<?php echo esc_attr( $gtm_id ); ?>" placeholder="GTM-XXXXXXX" class="regular-text">
	<p class="description">Enter your Google Tag Manager Container ID (e.g., GTM-XXXXXXX)</p>
	<?php
}

/**
 * Settings section description
 */
function bcc_gtm_settings_section_callback() {
	echo '<p>Configure your Google Tag Manager integration. The GTM code will be automatically inserted into your site.</p>';
}

/**
 * Render settings page
 */
function bcc_gtm_settings_page() {
	?>
	<div class="wrap">
		<h1>BCC Tag Manager Settings</h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'bcc_gtm_settings_group' );
			do_settings_sections( 'bcc_gtm_settings_group' );
			submit_button();
			?>
		</form>
		
		<div style="margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
			<h3>Theme Compatibility</h3>
			<p><strong>Detected Theme:</strong> <?php echo wp_get_theme()->get('Name'); ?></p>
			<p>This plugin automatically works with:</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>Avada Theme (uses <code>avada_before_body_content</code> hook)</li>
				<li>Salient Theme (uses <code>nectar_hook_after_body_open</code> hook)</li>
				<li>All other themes (uses standard <code>wp_body_open</code> hook)</li>
			</ul>
		</div>
		
		<div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3;">
			<h3>Plugin Information</h3>
			<p><strong>Version:</strong> <?php echo BCC_GTM_VERSION; ?></p>
			<p><strong>Support:</strong> <a href="https://bigcornercreative.com" target="_blank">Big Corner Creative</a></p>
		</div>
	</div>
	<?php
}

/**
 * Add Google Tag Manager javascript code to <head>
 */
function bcc_gtm_add_head() {
	$gtm_id = get_option( BCC_GTM_OPTION_NAME );
	
	// Don't output if GTM ID is not set
	if ( empty( $gtm_id ) ) {
		return;
	}
	
	// Optional: Don't load for logged-in admins (uncomment if desired)
	// if ( current_user_can( 'manage_options' ) ) {
	// 	return;
	// }
	?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
<!-- End Google Tag Manager -->
	<?php
}
add_action( 'wp_head', 'bcc_gtm_add_head', 0 );

/**
 * Add Google Tag Manager noscript code after opening <body> tag
 */
function bcc_gtm_add_body() {
	$gtm_id = get_option( BCC_GTM_OPTION_NAME );
	
	// Don't output if GTM ID is not set
	if ( empty( $gtm_id ) ) {
		return;
	}
	
	// Optional: Don't load for logged-in admins (uncomment if desired)
	// if ( current_user_can( 'manage_options' ) ) {
	// 	return;
	// }
	?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
	<?php
}

/**
 * Hook into the appropriate body tag location based on active theme
 */
function bcc_gtm_register_body_hook() {
	// Check for Avada theme hook
	if ( has_action( 'avada_before_body_content' ) ) {
		add_action( 'avada_before_body_content', 'bcc_gtm_add_body' );
	}
	// Check for Salient theme hook
	elseif ( has_action( 'nectar_hook_after_body_open' ) ) {
		add_action( 'nectar_hook_after_body_open', 'bcc_gtm_add_body' );
	}
	// Fall back to standard WordPress hook (WordPress 5.2+)
	else {
		add_action( 'wp_body_open', 'bcc_gtm_add_body' );
	}
}
add_action( 'after_setup_theme', 'bcc_gtm_register_body_hook' );

/**
 * Add settings link on plugin page
 */
function bcc_gtm_add_settings_link( $links ) {
	$settings_link = '<a href="options-general.php?page=bcc-tag-manager">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bcc_gtm_add_settings_link' );
