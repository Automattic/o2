<?php
/**
 * @package o2
 * @subpackage o2_Notifications
 */

if ( ! class_exists( 'o2_Notifications' ) ) {
class o2_Notifications extends o2_API_Base {

	function __construct() {


		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );

		// Actions
		add_action( 'o2_templates', array( $this, 'get_templates' ) );
		add_action( 'o2_wp_footer', array( $this, 'wp_footer' ) );

		// Filters
		add_filter( 'o2_options', array( $this, 'get_options' ) );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-notifications-models-notification', plugins_url( 'modules/notifications/js/models/notification.js', O2__FILE__ ), array( 'o2-models-base', 'o2-timestamp' ) );
		wp_enqueue_script( 'o2-notifications-collections-notifications', plugins_url( 'modules/notifications/js/collections/notifications.js', O2__FILE__ ), array( 'o2-notifications-models-notification' ) );
		wp_enqueue_script( 'o2-notifications-views-notification', plugins_url( 'modules/notifications/js/views/notification.js', O2__FILE__ ), array( 'o2-notifications-models-notification', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-notifications-views-dock-items', plugins_url( 'modules/notifications/js/views/dock-items.js', O2__FILE__ ), array( 'o2-notifications-collections-notifications', 'o2-notifications-views-notification', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-notifications-views-dock-count', plugins_url( 'modules/notifications/js/views/dock-count.js', O2__FILE__ ), array( 'o2-notifications-collections-notifications', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-notifications-views-dock', plugins_url( 'modules/notifications/js/views/dock.js', O2__FILE__ ), array( 'o2-notifications-views-dock-items', 'o2-notifications-views-dock-count', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-notifications-views-flash-items', plugins_url( 'modules/notifications/js/views/flash-items.js', O2__FILE__ ), array( 'o2-notifications-collections-notifications', 'o2-notifications-views-notification' ) );
		wp_enqueue_script( 'o2-notifications-views-flash', plugins_url( 'modules/notifications/js/views/flash.js', O2__FILE__ ), array( 'o2-notifications-views-notification', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-notifications', plugins_url( 'modules/notifications/js/app/notifications.js', O2__FILE__ ), array( 'o2-notifications-views-dock', 'o2-notifications-views-flash' ) );
	}

	function enqueue_style() {
		wp_register_style( 'o2-notifications', plugins_url( 'modules/notifications/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-notifications', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-notifications' );
	}

	/**
	 * Add the dock and flash templates to the o2 templates
	 */
	function get_templates() {
		?>

		<script type="html/template" id="tmpl-o2-notification">

		<# if ( '' !== data.iconUrl ) { #>
			<img src="{{ data.iconUrl }}&amp;s={{ data.iconSize }}" width="{{ data.iconSize }}" height="{{ data.iconSize }}" class="avatar {{data.iconClass}}" />
		<# } #>

		<# if ( data.dismissable ) { #>
			<span class="o2-notification-close"><a href="#" class="o2-notification-close">&#xf405;</a></span>
		<# } #>

		<p>
		<# if ( data.url ) { #>
			<a href="#" class="o2-notification-link {{data.textClass}}">{{{ data.text }}}</a>
		<# } else { #>
			{{{ data.text }}}
		<# } #>
		<# if ( 'notice' === data.type ) { #>
			<br />
			<span class="entry-date o2-timestamp" data-compact-allowed="true" data-unixtime="{{ data.unixtime }}">
		<# } #>
		</p>

		<div class="clear"></div>

		</script>

		<?php
	}

	/**
	 * Add the startup function to wp_footer() after o2 has started
	 */
	function wp_footer() {
		$data = apply_filters( 'o2_notifications_data', array() );
		?>

		<div id="o2-dock"></div>
		<div id="o2-flash"></div>

		<script class='o2-notifications-data' type='application/json' style='display:none;'>
		<?php echo json_encode( $data ); ?>

		</script>

		<?php
	}

	/**
	 * Add notification strings to the o2 options array
	 */
	function get_options( $options ) {
		$localizations = array(
			'clearNotifications' => __( 'Clear All', 'o2' ),
		);
		$localizations = array_merge( $options['strings'], $localizations );
		$options['strings'] = $localizations;
		return $options;
	}
} }

function o2_notifications() {
	new o2_Notifications();
}
add_action( 'o2_loaded', 'o2_notifications' );
