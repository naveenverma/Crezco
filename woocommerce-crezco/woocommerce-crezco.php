<?php
/**
 * The plugin bootstrap file
 *
 *
 * @link              https://www.fiverr.com/snipper1212
 * @since             1.0.0
 * @package           crezco
 *
 * @wordpress-plugin
 * Plugin Name:       Crezco Woocommerce
 * Plugin URI:        https://www.fiverr.com/snipper1212
 * Description:       Crezco Payment Gateway.
 * Version:           1.0.0
 * Author:            Naveen Verma
 * Author URI:        https://www.fiverr.com/snipper1212
 * Text Domain:       woo-crezco
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Well, get lost.' );
}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'wc_crezco_init', 0 );
add_action( 'admin_menu', 'nv_admin_menu' );
add_action( 'admin_init', 'nv_settings_init' );

function nv_admin_menu() {
	add_menu_page(
		__( 'Crezco', 'nv-manager' ),
		__( 'Crezco', 'nv-manager' ),
		'manage_options',
		'nv-manager-page',
		'nv_admin_page_contents',
		'dashicons-schedule',
		3
	);
}
 
function nv_admin_page_contents() {
	?>
	<form method="POST" action="options.php">
	<h1> <?php esc_html_e( 'Merchant onboarding', 'nv-manager' ); ?> </h1>
	<?php
	settings_fields( 'nv-manager-page' );
	do_settings_sections( 'nv-manager-page' );
	submit_button();
	?>
	</form>
	<?php
	$crezco_partnercode = get_option( 'crezco_partnercode' );
	if($crezco_partnercode)
	{
		$crezco_user_id_sandbox     = get_option( 'crezco_user_id_sandbox' );
        $crezco_user_id_live        = get_option( 'crezco_user_id_live' );
		$url 						= admin_url().'admin.php?page=wc-settings&tab=checkout&section=crezco';
		if($_GET['user-id'])
		{
			
			if($_GET['env'] == "sandbox")
			{
				update_option( 'crezco_user_id_sandbox', $_GET['user-id'] );
				echo "Congrats, you are boarded on sanbox. Please <a href='$url'>click here</a> to continue continue";
				echo " User ID : ".$crezco_user_id_sandbox;
			}

			if($_GET['env'] == "live")
			{
				update_option( 'crezco_user_id_live', $_GET['user-id'] );
				echo "Congrats, you are boarded on live. Please <a href='$url'>click here</a> to continue continue";
				echo " User ID : ".$crezco_user_id_live;
			}

		}
		else
		{
			if(empty($crqezco_user_id_sandbox))
			{
				echo "<h2>Sandbox</h2>";
				$url = 'https://app.sandbox.crezco.com/onboarding?partner_id='.$crezco_partnercode.'-sandbox&redirect_uri='.admin_url().'admin.php?'.urlencode('page=nv-manager-page&env=sandbox');
				echo '<br><strong><red>** PLEASE CLICK THE LINK BELOW </red> </strong><br><br>';
				echo '<a href="'.$url.'">Onboard with Crezco sandbox account</a></strong>';
			}
			else
			{
				echo "Congrats, you are boarded on sandbox. Please <a href='$url'>click here</a> to continue continue.";
				echo " User ID : ".$crezco_user_id_sandbox;
			}

			echo "<br><br>";
			
			if(empty($crezco_user_id_live))
			{
				echo "<h2>Live</h2>";
				$url = 'https://app.crezco.com/onboarding?partner_id='.$crezco_partnercode.'&redirect_uri='.admin_url().'admin.php?'.urlencode('page=nv-manager-page&env=live');
				echo '<br><strong><red>** PLEASE CLICK THE LINK BELOW </red> </strong><br><br>';
				echo '<a href="'.$url.'">Onboard with Crezco live account</a></strong>';
			}
			else
			{
				echo "Congrats, you are boarded on live. Please <a href='$url'>click here</a> to continue continue";
				echo " User ID : ".$crezco_user_id_live;
			}
		}
		
	}
} 

function nv_settings_init() {

	add_settings_section(
		'nv_manager_page_setting_section',
		__( 'Please add details below', 'nv-manager' ),
		'nv_setting_section_callback_function',
		'nv-manager-page'
	);

	
	add_settings_field(
	'crezco_partnercode',
	__( 'Partner Code', 'nv-manager' ),
	'crezco_partnercode',
	'nv-manager-page',
	'nv_manager_page_setting_section'
	);

	register_setting( 'nv-manager-page', 'crezco_partnercode' );

}

function crezco_partnercode() {
	?>
	<input type="text" class="regular-text" id="crezco_partnercode" name="crezco_partnercode" value="<?php echo get_option( 'crezco_partnercode' ); ?>">
	<small>Please note that [partnercode] is the code assigned by Crezco to the partner platform during its original onboarding.</small>
<?php
}

function nv_setting_section_callback_function() {

	echo '';

}
        

function wc_crezco_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-crezco-gateway.php' );
    
	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'wc_add_crezco_gateway' );
	function wc_add_crezco_gateway( $methods ) {
		$methods[] = 'WC_Payment_Gateway_Crezco';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_crezco_action_links' );
function wc_crezco_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'woo-crezco' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}
