<?php
/*
Plugin Name: Reaktiv CSS Builder
Plugin URI: http://reaktivstudios.com/plugins/
Description: Make simple CSS customizations
Version: 1.1.0
Author: Andrew Norcross
Author URI: http://andrewnorcross.com
Text Domain: reaktiv-css-builder
Domain Path: /languages

	Copyright 2013 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

 // Start up the engine
class RKV_Custom_CSS_Builder {

	/**
	 * Current version of the plugin.
	 * 
	 * @since	1.1.0
	 * @access	public
	 * @var		string	$version
	 */
	public $version = '1.1.0';
	
	/**
	 * This is our constructor. There are many like it, but this one is mine.
	 *
	 * @return RKV_Custom_CSS_Builder
	 */

	public function __construct() {

		// front end
		add_action		(	'wp_enqueue_scripts',								array(	$this,	'scripts_styles'		),	99		);

		// back end
		add_action		(	'plugins_loaded', 									array(	$this,	'textdomain'			) 			);

		add_action		(	'admin_init',										array(	$this,	'export_styles'			)			);
		add_action		(	'admin_init',										array(	$this,	'import_styles'			)			);
		add_action		(	'admin_notices',									array(	$this,	'export_notices'		)			);
		add_action		(	'admin_notices',									array(	$this,	'import_notices'		)			);

		add_action		(	'admin_init',										array(	$this,	'protection_files'		)			);
		add_action		(	'admin_enqueue_scripts',							array(	$this,	'admin_scripts'			)			);
		add_action		(	'admin_init', 										array(	$this,	'settings'				)			);
		add_action		(	'admin_menu' ,										array(	$this,	'css_edit_menu'			)			);
		add_action		(	'admin_notices',									array(	$this,	'write_css'				)			);

		add_filter		(	'plugin_action_links',								array(	$this,	'quick_link'			),	10,	2	);
		add_filter		(	'option_page_capability_reaktiv-custom-css',		array(	$this,	'user_permission'		)			);

	}

	/**
	 * load textdomain
	 *
	 * @return void
	 */

	public function textdomain() {

		load_plugin_textdomain( 'reaktiv-css-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * save the css code
	 *
	 * @return void
	 */
	
	public function save_css_code( $css_code ) {

		// The `rkvcss_get_css_code` action is run inside `sanitize_css`
		$css_code = $this->sanitize_css( $css_code );
		update_option( 'reaktiv-custom-css', $css_code );

	}

	/**
	 * get the css code
	 *
	 * @return string
	 */
	
	public function get_css_code() {

		$css_code = get_option( 'reaktiv-custom-css' );
		return apply_filters( 'rkvcss_get_css_code', $css_code );

	}


	/**
	 * sanitize the css
	 */

	public function sanitize_css( $raw_css_code ) {

		// @todo: Add additional sanitizing of the CSS in here.
		$sanitized_css_code = apply_filters( 'rkvcss_sanitize_css', $raw_css_code );

		do_action( 'rkvcss_save_css_code', $sanitized_css_code, $raw_css_code );
		return $sanitized_css_code;

	}

	/**
	 * set filename and create folder if need be for reuse
	 *
	 * @return
	 */

	static function filebase() {

		$uploads	= wp_upload_dir();
		$basedir	= $uploads['basedir'].'/custom-css/';
		$baseurl	= $uploads['baseurl'].'/custom-css/';


		// check if folder exists. if not, make it
		if ( ! is_dir( $basedir ) )
			mkdir( $basedir );

		// open the css file, or generate if one does not exist
		$blog_id	= get_current_blog_id();
		$filename	= 'reaktiv-css-'.$blog_id.'.css';

		return array(
			'dir'	=> $basedir.$filename,
			'url'	=> $baseurl.$filename,
		);

	}

	/**
	 * Load CSS
	 *
	 * @return RKV_Custom_CSS_Builder
	 */

	public function scripts_styles() {

		$file	= $this->filebase();

		if ( ! file_exists( $file['dir'] ) )
			return;

		wp_enqueue_style( 'reaktiv-custom', $file['url'], array(), null, 'all' );

	}

	/**
	 * init call CSS generation
	 *
	 * @return
	 */

	public function write_css() {

		// first check to make sure we're on our settings
		if ( !isset( $_GET['page'] ) )
			return;

		// now make sure we're actually doing our save function
		if ( !isset( $_GET['settings-updated'] ) )
			return;

		if ( $_GET['page'] !== 'reaktiv-custom-css' || $_GET['settings-updated'] !== 'true' )
			return;

		// generate the CSS
		$generate	= $this->generate_css();

		if ( $generate === true ) :
			// checks passed, display the message
			echo '<div class="updated">';
				echo '<p>'.__( 'The custom CSS has been generated.', 'reaktiv-css-builder' ).'</p>';
			echo '</div>';
		else:
			// checks failed, display the message
			echo '<div class="error">';
				echo '<p>'.__( 'The custom CSS could not be generated.', 'reaktiv-css-builder' ).'</p>';
			echo '</div>';
		endif;

		do_action( 'rkvcss_after_write_css', $generate );

		return;

	}

	/**
	 * actual CSS generation
	 *
	 * @return
	 */

	public function generate_css() {

		$file	= $this->filebase();

		$check	= fopen( $file['dir'], 'wb');

		if ( $check === false )
			return false;

		// get the new CSS
		$data	= $this->get_css_code();

		$write	= trim( $data );
		fwrite( $check, $write );
		fclose( $check );

		return true;
	}

	/**
	 * Admin scripts and styles
	 *
	 * @return
	 */

	public function admin_scripts( $hook ) {

		if ( $hook == 'appearance_page_reaktiv-custom-css' ) :
			
			if ( true == apply_filters( 'reaktiv_css_debug_mode', WP_DEBUG ) ) {

				wp_enqueue_style( 'codemirror', plugins_url( 'lib/css/codemirror.css', __FILE__ ), array(), '3.20', 'all' );
				wp_enqueue_style( 'reaktiv-css-admin', plugins_url( 'lib/css/reaktiv.admin.css', __FILE__ ), array(), $this->version, 'all' );

				wp_enqueue_script( 'codemirror-base', plugins_url( 'lib/js/codemirror.js', __FILE__ ), array( 'jquery' ), '3.20', true );
				wp_enqueue_script( 'codemirror-css', plugins_url( 'lib/js/codemirror.css.js', __FILE__ ), array( 'jquery', ), '3.20', true );
				wp_enqueue_script( 'reaktiv-js-admin', plugins_url( 'lib/js/reaktiv.admin.js', __FILE__ ), array( 'jquery' ), $this->version, true );
			
			} else {
				
				wp_enqueue_style( 'codemirror', plugins_url( 'lib/css/codemirror.min.css', __FILE__ ), array('reaktiv-css-admin'), '3.20', 'all' );
				wp_enqueue_style( 'reaktiv-css-admin', plugins_url( 'lib/css/reaktiv.admin.min.css', __FILE__ ), array(), $this->version, 'all' );
				
				wp_enqueue_script( 'codemirror-base', plugins_url( 'lib/js/codemirror.min.js', __FILE__ ), array( 'jquery' ), '3.20', true );
				wp_enqueue_script( 'reaktiv-js-admin', plugins_url( 'lib/js/reaktiv.admin.min.js', __FILE__ ), array( 'jquery' ), $this->version, true );
				
			}

		endif;

	}

	/**
	 * show settings link on plugins page
	 *
	 * @return
	 */

	public function quick_link( $links, $file ) {

		static $this_plugin;

		if (!$this_plugin) {
			$this_plugin = plugin_basename(__FILE__);
		}

		// check to make sure we are on the correct plugin
		if ($file == $this_plugin) {

			$settings_link	= '<a href="' . menu_page_url( 'reaktiv-custom-css', 0 ) . '">'.__( 'CSS Builder', 'reaktiv-css-builder' ).'</a>';
			array_push( $links, $settings_link );

		}

		return $links;

	}

	/**
	 * Register settings
	 *
	 * @return
	 */

	public function settings() {

		register_setting( 'reaktiv-custom-css', 'reaktiv-custom-css', array( $this, 'sanitize_css' ) );

	}

	/**
	 * filter user permission to allow saving without error message
	 *
	 * @return capabilities
	 */

	public function user_permission( $capability ) {

		return apply_filters( 'reaktiv_css_caps', $capability );

	}

	/**
	 * call CSS editor page
	 *
	 * @return RKV_Custom_CSS_Builder
	 */

	public function css_edit_menu() {
		add_theme_page( __( 'CSS Builder', 'reaktiv-css-builder' ), __( 'CSS Builder', 'reaktiv-css-builder' ), apply_filters( 'reaktiv_css_caps', 'manage_options' ), 'reaktiv-custom-css', array( $this, 'css_edit_page' ) );
	}


   /**
	 * Display CSS editor
	 *
	 * @return
	 */

	public function css_edit_page() {

		$cssdata	= $this->get_css_code();

		?>

		<div class="wrap">
		<div class="icon32" id="icon-tools"><br></div>
		<h2><?php _e( 'Custom CSS Builder', 'reaktiv-css-builder' ) ?></h2>

			<div class="reaktiv-form-wrap">

			<form class="reaktiv-custom-css" method="post" action="options.php">
				<?php settings_fields( 'reaktiv-custom-css' ); ?>

				<p><?php _e( 'Enter your CSS below and it will display on the front end of the site. Keep in mind that you may have to be more specific that the existing CSS for it to take precedent.', 'reaktiv-css-builder' ); ?></p>

				<textarea name="reaktiv-custom-css" id="reaktiv-custom-css" class="widefat code"><?php echo esc_attr( $cssdata ); ?></textarea>

				<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save CSS', 'reaktiv-css-builder' ); ?>" /></p>
			</form>

			<?php self::css_data_manager(); ?>
			</div>

		</div>

	<?php }

   /**
	 * Grab buttons and fields for import / export
	 *
	 * @return
	 */

	static function css_data_manager() {

		// create nonce for export
		$expnonce	= wp_create_nonce( 'rkv_css_export_nonce' );

		echo '<div class="reaktiv-data-setup">';
			echo '<h3 class="title">'.__( 'Export CSS Data', 'reaktiv-css-builder' ).'</h3>';

			echo '<p>';
				echo '<a href="'.menu_page_url( 'reaktiv-custom-css', 0 ).'&amp;reaktiv-css-export=go&amp;_wpnonce='.$expnonce.'" class="button-primary button-small">'.__( 'Export File', 'reaktiv-css-builder' ).'</a>';
				echo '&nbsp;<span class="description">'.__( 'Export your entered CSS in JSON format to import on another site.', 'reaktiv-css-builder' ).'</span>';
			echo '</p>';

		echo '</div>';

		echo '<div class="reaktiv-data-setup">';
			echo '<h3 class="title">'.__( 'Import CSS Data', 'reaktiv-css-builder' ).'</h3>';

			echo '<form enctype="multipart/form-data" method="post" action="'.menu_page_url( 'reaktiv-custom-css', 0 ).'&amp;reaktiv-css-import=go">';
				wp_nonce_field( 'rkv_css_import_nonce' );

				echo '<input class="reaktiv-import-upload" type="file" name="reaktiv-css-import-upload" size="25" />';

				echo '<p>';
					echo get_submit_button( __( 'Import File', 'reaktiv-css-builder' ), 'primary', 'reaktiv-css-import-submit', false, false );
					echo '&nbsp;<span class="description">'.__( 'Import a previously saved JSON file.', 'reaktiv-css-builder' ).'</span>';
				echo '</p>';

			echo '</form>';

		echo '</div>';

	}

	/**
	 * export our settings
	 *
	 * @return Genesis_Palette_Pro
	 */

	public function export_styles() {

		// check page and query string
		if ( ! isset( $_REQUEST['reaktiv-css-export'] ) || isset( $_REQUEST['reaktiv-css-export'] ) && $_REQUEST['reaktiv-css-export'] != 'go' )
			return;

		// check nonce
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'rkv_css_export_nonce' ) )
			return;

		// get current settings
		$current	= $this->get_css_code();

		// if settings empty, bail
		if ( empty( $current ) ) {
			$failure	= menu_page_url( 'reaktiv-custom-css', 0 ).'&export=failure&reason=nodata';
			wp_safe_redirect( $failure );

			return;
		}

		$output = json_encode( $current );

		//* Prepare and send the export file to the browser
		header( 'Content-Description: File Transfer' );
		header( 'Cache-Control: public, must-revalidate' );
		header( 'Pragma: hack' );
		header( 'Content-type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="reaktiv-custom-css-' . date( 'Ymd-His' ) . '.json"' );
		header( 'Content-Length: ' . mb_strlen( $output ) );
		echo $output;
		exit();

	}

	/**
	 * import our settings
	 *
	 * @return Genesis_Palette_Pro
	 */

	public function import_styles() {

		// bail if no page reference
		if ( ! isset( $_REQUEST['reaktiv-css-import'] ) || isset( $_REQUEST['reaktiv-css-import'] ) && $_REQUEST['reaktiv-css-import'] != 'go' )
			return;

		// check nonce and bail if missing
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'rkv_css_import_nonce' ) )
			return;


		// bail if no file present
		if ( ! isset( $_FILES['reaktiv-css-import-upload'] ) ) {
			$failure	= menu_page_url( 'reaktiv-custom-css', 0 ).'&uploaded=failure&reason=nofile';
			wp_safe_redirect( $failure );

			return;
		}

		// bail if no file present
		if ( isset( $_FILES['reaktiv-css-import-upload']['error'] ) && $_FILES['reaktiv-css-import-upload']['error'] === 4 ) {
			$failure	= menu_page_url( 'reaktiv-custom-css', 0 ).'&uploaded=failure&reason=nofile';
			wp_safe_redirect( $failure );

			return;
		}

		// check file type
		if ( $_FILES['reaktiv-css-import-upload']['type'] !== 'application/json' ) {
			$failure	= menu_page_url( 'reaktiv-custom-css', 0 ).'&uploaded=failure&reason=notjson';
			wp_safe_redirect( $failure );

			return;
		}

		// passed our initial checks, now decode the file and check the contents
		$upload		= file_get_contents( $_FILES['reaktiv-css-import-upload']['tmp_name'] );
		$options	= json_decode( $upload, true );

		// check for valid JSON
		if ( $options === null ) {
			$failure	= menu_page_url( 'reaktiv-custom-css', 0 ).'&uploaded=failure&reason=badjson';
			wp_safe_redirect( $failure );

			return;
		}

		// everything is gold! lets make some magic
		$this->save_css_code( $options[0] );

		// generate the new CSS
		$build	= $this->generate_css();

		//* Redirect, add success flag to the URI
		$update	= menu_page_url( 'reaktiv-custom-css', 0 ).'&uploaded=success';
		wp_safe_redirect( $update );

		exit;

	}

	/**
	 * display messages if export failure
	 *
	 * @return
	 */

	public function export_notices() {

		// first check to make sure we're on our settings
		if ( ! isset( $_REQUEST['page'] ) || isset( $_REQUEST['page'] ) && $_REQUEST['page'] !== 'reaktiv-custom-css' )
			return;

		// check for failure
		if ( isset( $_REQUEST['export'] ) && isset( $_REQUEST['reason'] ) && $_REQUEST['export'] == 'failure' ) {

			// no file provided
			if ( $_REQUEST['reason'] == 'nodata' ) {
				echo '<div id="message" class="error">';
				echo '<p>'.__( 'No settings data has been saved. Please save your settings and try again.', 'gppro' ).'</p>';
				echo '</div>';

				return;
			}

			// unknown reason
			if ( $_REQUEST['reason'] !== 'nofile' && $_REQUEST['reason'] !== 'notjson' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'There was an error with your export. Please try again later.', 'gppro' ).'</p>';
				echo '</div>';

				return;
			}

			return;

		}

		return;

	}

	/**
	 * display messages if import success or failure
	 *
	 * @return
	 */

	public function import_notices() {

		// first check to make sure we're on our settings
		if ( ! isset( $_REQUEST['page'] ) || isset( $_REQUEST['page'] ) && $_REQUEST['page'] !== 'reaktiv-custom-css' )
			return;

		// make sure we have some sort of upload message
		if ( ! isset( $_REQUEST['uploaded'] ) )
			return;

		// check for failure
		if ( isset( $_REQUEST['uploaded'] ) && isset( $_REQUEST['reason'] ) && $_REQUEST['uploaded'] == 'failure' ) {

			// no file provided
			if ( $_REQUEST['reason'] == 'nofile' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'No file was provided. Please try again.', 'gppro' ).'</p>';
				echo '</div>';

				return;

			}

			// file isn't JSON
			if ( $_REQUEST['reason'] == 'notjson' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'The import file was not in JSON format. Please try again.', 'gppro' ).'</p>';
				echo '</div>';

				return;

			}

			// JSON isn't valid
			if ( $_REQUEST['reason'] == 'badjson' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'The import file was not valid JSON. Please try again.', 'gppro' ).'</p>';
				echo '</div>';

				return;

			}

			// no CSS generated
			if ( $_REQUEST['reason'] == 'nocss' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'The import settings could not be applied. Please try again.', 'gppro' ).'</p>';
				echo '</div>';

				return;

			}

			// unknown reason
			if ( $_REQUEST['reason'] !== 'nofile' && $_REQUEST['reason'] !== 'notjson' ) {

				echo '<div id="message" class="error">';
				echo '<p>'.__( 'There was an error with your import. Please try again later.', 'gppro' ).'</p>';
				echo '</div>';

				return;

			}

			return;

		}

		// checks passed, display the message
		if ( isset( $_REQUEST['uploaded'] ) && $_REQUEST['uploaded'] == 'success' ) {

			echo '<div id="message" class="updated">';
				echo '<p>'.__( 'Your settings have been updated', 'gppro' ).'</p>';
			echo '</div>';

			return;

		}

		return;

	}

	/**
	 * Creates blank index.php and .htaccess files
	 *
	 * This function runs approximately once per month in order to ensure all folders
	 * have their necessary protection files
	 *
	 * @since 0.0.6.2
	 * @return void
	 */

	static function protection_files() {

		if ( false === get_transient( 'rkvcss_check_protection_files' ) ) :

			// grab our folder setup
			$upload	= self::filebase();
			if ( !isset( $upload['root'] ) )
				return;

			$folder	= $upload['root'];

			// kill the trailing slash
			if( substr( $folder, -1 ) == '/' )
				$folder = substr( $folder, 0, -1 );

			// Top level blank index.php
			if ( ! file_exists( $folder . '/index.php' ) )
				@file_put_contents( $folder . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' );

			// Top level .htaccess file
			if ( ! file_exists( $folder . '/.htaccess' ) )
				@file_put_contents( $folder . '/.htaccess', 'Options -Indexes' );

			// Check for the files once per day
			set_transient( 'rkvcss_check_protection_files', true, 3600 * 24 );

		endif;

	}

/// end class
}


// Instantiate our class
$RKV_Custom_CSS_Builder = new RKV_Custom_CSS_Builder();


