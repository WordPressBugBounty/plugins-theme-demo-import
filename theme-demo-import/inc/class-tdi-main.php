<?php
/**
 * Main Theme Demo Import plugin class/file.
 *
 * @package tdi
 */

// Include files.
require TDI_PATH . 'inc/class-tdi-helpers.php';
require TDI_PATH . 'inc/class-tdi-importer.php';
require TDI_PATH . 'inc/class-tdi-widget-importer.php';
require TDI_PATH . 'inc/class-tdi-customizer-importer.php';
require TDI_PATH . 'inc/class-tdi-logger.php';

/**
 * Theme Demo Import class, so we don't have to worry about namespaces.
 */
class Theme_Demo_Import {

	/**
	 * @var $instance the reference to *Singleton* instance of this class
	 */
	private static $instance;

	/**
	 * Private variables used throughout the plugin.
	 */
	private $importer, $plugin_page, $import_files, $logger, $log_file_path, $selected_index, $selected_import_files, $microtime, $frontend_error_messages, $ajax_call_number;


	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Theme_Demo_Import the *Singleton* instance.
	 */
	public static function getInstance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}


	/**
	 * Class construct function, to initiate the plugin.
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	protected function __construct() {

		// Actions.
		add_action( 'admin_menu', array( $this, 'create_plugin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_TDI_import_demo_data', array( $this, 'import_demo_data_ajax_callback' ) );
		add_action( 'after_setup_theme', array( $this, 'setup_plugin_with_filter_data' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}


	/**
	 * Creates the plugin page and a submenu item in WP Appearance menu.
	 */
	public function create_plugin_page() {
		$plugin_page_setup = apply_filters( 'theme-demo-import/plugin_page_setup', array(
				'parent_slug' => 'themes.php',
				'page_title'  => esc_html__( 'Theme Demo Importer' , 'theme-demo-import' ),
				'menu_title'  => esc_html__( 'Import Demo Content' , 'theme-demo-import' ),
				'capability'  => 'import',
				'menu_slug'   => 'theme-demo-import',
			)
		);

		$this->plugin_page = add_submenu_page( $plugin_page_setup['parent_slug'], $plugin_page_setup['page_title'], $plugin_page_setup['menu_title'], $plugin_page_setup['capability'], $plugin_page_setup['menu_slug'], array( $this, 'display_plugin_page' ) );
	}


	/**
	 * Plugin page display.
	 */
	public function display_plugin_page() {
	?>

	<div class="tdi wrap about-wrap">

		<h1><?php esc_html_e( 'Theme Demo Importer', 'theme-demo-import' ); ?></h1>

		<div class="TDI__intro-notice notice notice-warning">
			<p><?php esc_html_e( 'Before you begin, make sure all the plugins required by your theme are installed and activated.', 'theme-demo-import' ); ?></p>
		</div>

		<?php

		// Display warrning if PHP safe mode is enabled, since we wont be able to change the max_execution_time.
		if ( ini_get( 'safe_mode' ) ) {
			printf(
				esc_html__( '%sWarning: your server is using %sPHP safe mode%s. This means that you might experience server timeout errors.%s', 'theme-demo-import' ),
				'<div class="notice  notice-warning  is-dismissible"><p>',
				'<strong>',
				'</strong>',
				'</p></div>'
			);
		}

		// Start output buffer for displaying the plugin intro text.
		ob_start();
		?>

		<div class="TDI__intro-text">

			<p class="about-description">
				<?php esc_html_e( 'Import the demo content for your new theme which includes posts, pages, images, widgets, menus and settings. This will provide you with a basic layout to build your website and speed up the development process. NOTE: Your existing content will NOT be deleted or modified.', 'theme-demo-import' ); ?>
			</p>

			<p><?php esc_html_e( 'Click the button once and wait. The import process may take several minutes.', 'theme-demo-import' ); ?></p>

		</div>

		<?php
		$plugin_intro_text = ob_get_clean();

		// Display the plugin intro text (can be replaced with custom text through the filter below).
		echo wp_kses_post( apply_filters( 'theme-demo-import/plugin_intro_text', $plugin_intro_text ) );
		?>


		<?php if ( empty( $this->import_files ) ) : ?>

			<div class="notice  notice-info  is-dismissible">
				<p><?php esc_html_e( 'There are no predefined import files available in this theme. Please upload the import files manually!', 'theme-demo-import' ); ?></p>
			</div>

			<div class="TDI__file-upload-container">

				<h2><?php esc_html_e( 'Manually upload the demo files', 'theme-demo-import' ); ?></h2>

				<div class="TDI__file-upload">
					<h3><label for="content-file-upload"><?php esc_html_e( 'Choose a XML file for content import:', 'theme-demo-import' ); ?></label></h3>
					<input id="TDI__content-file-upload" type="file" accept="application/xml,.xml" pattern="^.*\.(xml)$" name="content-file-upload">
				</div>

				<div class="TDI__file-upload">
					<h3><label for="widget-file-upload"><?php esc_html_e( 'Choose a WIE or JSON file for widget import:', 'theme-demo-import' ); ?></label> <span><?php esc_html_e( '(*optional)', 'theme-demo-import' ); ?></span></h3>
					<input id="TDI__widget-file-upload" type="file" accept=".wie,.json,application/json" pattern="^.*\.(wie|json)$" name="widget-file-upload">
				</div>

				<div class="TDI__file-upload">
					<h3><label for="customizer-file-upload"><?php esc_html_e( 'Choose a DAT file for customizer import:', 'theme-demo-import' ); ?></label> <span><?php esc_html_e( '(*optional)', 'theme-demo-import' ); ?></span></h3>
					<input id="TDI__customizer-file-upload" type="file" accept="application/dat,.dat" pattern="^.*\.(dat)$" name="customizer-file-upload">
				</div>

			</div>

		<?php elseif ( 1 < count( $this->import_files ) ) : ?>

			<div class="TDI__multi-select-import">

				<h2><?php esc_html_e( 'Choose which demo you want to import:', 'theme-demo-import' ); ?></h2>

				<select id="TDI__demo-import-files" class="TDI__demo-import-files">
					<?php foreach ( $this->import_files as $index => $import_file ) : ?>
						<option value="<?php echo esc_attr( $index ); ?>">
							<?php echo esc_html( $import_file['import_file_name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php
				// Check if at least one preview image is defined, so we can prepare the structure for display.
				$preview_image_is_defined = false;
				foreach ( $this->import_files as $import_file ) {
					if ( isset( $import_file['import_preview_image_url'] ) ) {
						$preview_image_is_defined = true;
						break;
					}
				}

				if ( $preview_image_is_defined ) :
				?>

				<div class="TDI__demo-import-preview-container">

					<p><?php esc_html_e( 'Import preview:', 'theme-demo-import' ); ?></p>

					<p class="TDI__demo-import-preview-image-message  js-tdi-preview-image-message"><?php
						if ( ! isset( $this->import_files[0]['import_preview_image_url'] ) ) {
							esc_html_e( 'No preview image defined for this import.', 'theme-demo-import' );
						}
						// Leave the img tag below and the p tag above available for later changes via JS.
					?></p>

					<img id="TDI__demo-import-preview-image" class="js-tdi-preview-image" src="<?php echo ! empty( $this->import_files[0]['import_preview_image_url'] ) ? esc_url( $this->import_files[0]['import_preview_image_url'] ) : ''; ?>">

				</div>

				<?php endif; ?>

			</div>

		<?php endif; ?>

		<div class="TDI__demo-import-notice  js-tdi-demo-import-notice"><?php
			if ( is_array( $this->import_files ) && ! empty( $this->import_files[0]['import_notice'] ) ) {
				echo wp_kses_post( $this->import_files[0]['import_notice'] );
			}
		?></div>

		<p class="TDI__button-container">
			<button class="TDI__button button button-hero button-primary js-tdi-import-data" style="width: 100%;"><?php esc_html_e( 'Import Demo Content', 'theme-demo-import' ); ?></button>
		</p>

		<p class="TDI__ajax-loader js-tdi-ajax-loader">
			<span class="spinner"></span> <?php esc_html_e( 'Importing, please wait!', 'theme-demo-import' ); ?>
		</p>

		<div class="TDI__response js-tdi-ajax-response"></div>

	</div>

	<?php
	}


	/**
	 * Enqueue admin scripts (JS and CSS)
	 *
	 * @param string $hook holds info on which admin page you are currently loading.
	 */
	public function admin_enqueue_scripts( $hook ) {

		// Enqueue the scripts only on the plugin page.
		if ( $this->plugin_page === $hook ) {
			wp_enqueue_script( 'tdi-main-js', TDI_URL . 'assets/js/main.js' , array( 'jquery', 'jquery-form' ), TDI_VERSION );

			wp_localize_script( 'tdi-main-js', 'tdi',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'   => wp_create_nonce( 'tdi-ajax-verification' ),
					'import_files' => $this->import_files,
					'texts'        => array(
						'missing_preview_image' => esc_html__( 'No preview image defined for this import.', 'theme-demo-import' ),
					),
				)
			);

			wp_enqueue_style( 'tdi-main-css', TDI_URL . 'assets/css/main.css', array() , TDI_VERSION );
		}
	}


	/**
	 * Main AJAX callback function for:
	 * 1. prepare import files (uploaded or predefined via filters)
	 * 2. import content
	 * 3. before widgets import setup (optional)
	 * 4. import widgets (optional)
	 * 5. import customizer options (optional)
	 * 6. after import setup (optional)
	 */
	public function import_demo_data_ajax_callback() {
	    // Try to update PHP memory limit (so that it does not run out of it).
	    ini_set( 'memory_limit', apply_filters( 'theme-demo-import/import_memory_limit', '350M' ) );

	    // Verify if the AJAX call is valid (checks nonce and current_user_can).
	    TDI_Helpers::verify_ajax_call();

	    // Is this a new AJAX call to continue the previous import?
	    $use_existing_importer_data = $this->get_importer_data();

	    if ( ! $use_existing_importer_data ) {
	        // Set the AJAX call number.
	        $this->ajax_call_number = empty( $this->ajax_call_number ) ? 0 : $this->ajax_call_number;

	        // Error messages displayed on front page.
	        $this->frontend_error_messages = '';

	        // Create a date and time string to use for demo and log file names.
	        $demo_import_start_time = date( apply_filters( 'theme-demo-import/date_format_for_file_names', 'Y-m-d__H-i-s' ) );

	        // Define log file path.
	        $this->log_file_path = TDI_Helpers::get_log_path( $demo_import_start_time );

	        // Get selected file index or set it to 0.
	        $this->selected_index = empty( $_POST['selected'] ) ? 0 : absint( $_POST['selected'] );

	        if ( ! empty( $_FILES ) ) { // Using manual file uploads?
	            // Get paths for the uploaded files.
	            $this->selected_import_files = TDI_Helpers::process_uploaded_files( $_FILES, $this->log_file_path );

	            // Set the name of the import files, because we used the uploaded files.
	            $this->import_files[ $this->selected_index ]['import_file_name'] = esc_html__( 'Manually uploaded files', 'theme-demo-import' );
	        }
	        elseif ( ! empty( $this->import_files[ $this->selected_index ] ) ) {
	            // Download the import files (content and widgets files) and save it to variable for later use.
	            $this->selected_import_files = TDI_Helpers::download_import_files(
	                $this->import_files[ $this->selected_index ],
	                $demo_import_start_time
	            );

	            // Check Errors.
	            if ( is_wp_error( $this->selected_import_files ) ) {
	                // Write error to log file and send an AJAX response with the error.
	                TDI_Helpers::log_error_and_send_ajax_response(
	                    $this->selected_import_files->get_error_message(),
	                    $this->log_file_path,
	                    esc_html__( 'Downloaded files', 'theme-demo-import' )
	                );
	            }

	            // Add this message to log file.
	            $log_added = TDI_Helpers::append_to_file(
	                sprintf(
	                    __( 'The import files for: %s were successfully downloaded!', 'theme-demo-import' ),
	                    $this->import_files[ $this->selected_index ]['import_file_name']
	                ) . TDI_Helpers::import_file_info( $this->selected_import_files ),
	                $this->log_file_path,
	                esc_html__( 'Downloaded files' , 'theme-demo-import' )
	            );
	        }
	        else {
	            // Send JSON Error response to the AJAX call.
	            wp_send_json( esc_html__( 'No import files specified!', 'theme-demo-import' ) );
	        }
	    }

	    // Run the actual import.
	    $this->run_import();
	}

	private function run_import() {
	    // Import content.
	    $this->frontend_error_messages .= $this->import_content( $this->selected_import_files['content'] );

	    // Import widgets.
	    if ( ! empty( $this->selected_import_files['widgets'] ) && empty( $this->frontend_error_messages ) ) {
	        $this->import_widgets( $this->selected_import_files['widgets'] );
	    }

	    // Import customize options.
	    if ( ! empty( $this->selected_import_files['customizer'] ) && empty( $this->frontend_error_messages ) ) {
	        $this->import_customizer( $this->selected_import_files['customizer'] );
	    }

	    // Display final messages (success or error messages).
	    if ( empty( $this->frontend_error_messages ) ) {
	        $response['message'] = sprintf(
	            __( '%1$s%3$sThat\'s it, all done!%4$s%2$sThe demo import has finished. Please check your page and make sure that everything has imported correctly. If it did, you can deactivate the %3$sTheme Demo Importer%4$s plugin.%5$s', 'theme-demo-import' ),
	            '<div class="notice  notice-success"><p>',
	            '<br>',
	            '<strong>',
	            '</strong>',
	            '</p></div>'
	        );
	    }
	    else {
	        $response['message'] = $this->frontend_error_messages . '<br>';
	        $response['message'] .= sprintf(
	            __( '%1$sThe demo import has finished, but there were some import errors.%2$sMore details about the errors can be found in this %3$s%5$slog file%6$s%4$s%7$s', 'theme-demo-import' ),
	            '<div class="notice  notice-error"><p>',
	            '<br>',
	            '<strong>',
	            '</strong>',
	            '<a href="' . TDI_Helpers::get_log_url( $this->log_file_path ) .'" target="_blank">',
	            '</a>',
	            '</p></div>'
	        );
	    }

	    wp_send_json( $response );
	}


	/**
	 * Import content from an WP XML file.
	 *
	 * @param string $import_file_path path to the import file.
	 */
	private function import_content( $import_file_path ) {

		$this->microtime = microtime( true );

		// This should be replaced with multiple AJAX calls (import in smaller chunks)
		// so that it would not come to the Internal Error, because of the PHP script timeout.
		// Also this function has no effect when PHP is running in safe mode
		// http://php.net/manual/en/function.set-time-limit.php.
		// Increase PHP max execution time.
		set_time_limit( apply_filters( 'theme-demo-import/set_time_limit_for_demo_data_import', 300 ) );

		// Disable import of authors.
		add_filter( 'wxr_importer.pre_process.user', '__return_false' );

		// Check, if we need to send another AJAX request and set the importing author to the current user.
		add_filter( 'wxr_importer.pre_process.post', array( $this, 'new_ajax_request_maybe' ) );

		// Disables generation of multiple image sizes (thumbnails) in the content import step.
		if ( ! apply_filters( 'theme-demo-import/regenerate_thumbnails_in_content_import', true ) ) {
			add_filter( 'intermediate_image_sizes_advanced',
				function() {
					return null;
				}
			);
		}

		// Import content.
		if ( ! empty( $import_file_path ) ) {
			ob_start();
				$this->importer->import( $import_file_path );
			$message = ob_get_clean();

			// Add this message to log file.
			$log_added = TDI_Helpers::append_to_file(
				$message . PHP_EOL . esc_html__( 'Max execution time after content import = ' , 'theme-demo-import' ) . ini_get( 'max_execution_time' ),
				$this->log_file_path,
				esc_html__( 'Importing content' , 'theme-demo-import' )
			);
		}

		// Delete content importer data for current import from DB.
		delete_transient( 'TDI_importer_data' );

		// Return any error messages for the front page output (errors, critical, alert and emergency level messages only).
		return $this->logger->error_output;
	}


	/**
	 * Import widgets from WIE or JSON file.
	 *
	 * @param string $widget_import_file_path path to the widget import file.
	 */
	private function import_widgets( $widget_import_file_path ) {

		// Widget import results.
		$results = array();

		// Create an instance of the Widget Importer.
		$widget_importer = new TDI_Widget_Importer();

		// Import widgets.
		if ( ! empty( $widget_import_file_path ) ) {

			// Import widgets and return result.
			$results = $widget_importer->import_widgets( $widget_import_file_path );
		}

		// Check for errors.
		if ( is_wp_error( $results ) ) {

			// Write error to log file and send an AJAX response with the error.
			TDI_Helpers::log_error_and_send_ajax_response(
				$results->get_error_message(),
				$this->log_file_path,
				esc_html__( 'Importing widgets', 'theme-demo-import' )
			);
		}

		ob_start();
			$widget_importer->format_results_for_log( $results );
		$message = ob_get_clean();

		// Add this message to log file.
		$log_added = TDI_Helpers::append_to_file(
			$message,
			$this->log_file_path,
			esc_html__( 'Importing widgets' , 'theme-demo-import' )
		);
	}


	/**
	 * Import customizer from a DAT file, generated by the Customizer Export/Import plugin.
	 *
	 * @param string $customizer_import_file_path path to the customizer import file.
	 */
	private function import_customizer( $customizer_import_file_path ) {

		// Try to import the customizer settings.
		$results = TDI_Customizer_Importer::import_customizer_options( $customizer_import_file_path );

		// Check for errors.
		if ( is_wp_error( $results ) ) {

			// Write error to log file and send an AJAX response with the error.
			TDI_Helpers::log_error_and_send_ajax_response(
				$results->get_error_message(),
				$this->log_file_path,
				esc_html__( 'Importing customizer settings', 'theme-demo-import' )
			);
		}

		// Add this message to log file.
		$log_added = TDI_Helpers::append_to_file(
			esc_html__( 'Customizer settings import finished!', 'theme-demo-import' ),
			$this->log_file_path,
			esc_html__( 'Importing customizer settings' , 'theme-demo-import' )
		);
	}


	/**
	 * Setup other things in the passed wp action.
	 *
	 * @param string $action the action name to be executed.
	 * @param array  $selected_import with information about the selected import.
	 */
	private function do_import_action( $action, $selected_import ) {

		ob_start();
			do_action( $action, $selected_import );
		$message = ob_get_clean();

		// Add this message to log file.
		$log_added = TDI_Helpers::append_to_file(
			$message,
			$this->log_file_path,
			$action
		);
	}


	/**
	 * Check if we need to create a new AJAX request, so that server does not timeout.
	 *
	 * @param array $data current post data.
	 * @return array
	 */
	public function new_ajax_request_maybe( $data ) {
		$time = microtime( true ) - $this->microtime;

		// We should make a new ajax call, if the time is right.
		if ( $time > apply_filters( 'theme-demo-import/time_for_one_ajax_call', 25 ) ) {
			$this->ajax_call_number++;
			$this->set_importer_data();

			$response = array(
				'status'  => 'newAJAX',
				'message' => 'Time for new AJAX request!: ' . $time,
			);

			// Add any output to the log file and clear the buffers.
			$message = ob_get_clean();

			// Add message to log file.
			$log_added = TDI_Helpers::append_to_file(
				__( 'Completed AJAX call number: ' , 'theme-demo-import' ) . $this->ajax_call_number . PHP_EOL . $message,
				$this->log_file_path,
				''
			);

			wp_send_json( $response );
		}

		// Set importing author to the current user.
		// Fixes the [WARNING] Could not find the author for ... log warning messages.
		$current_user_obj    = wp_get_current_user();
		$data['post_author'] = $current_user_obj->user_login;

		return $data;
	}

	/**
	 * Set current state of the content importer, so we can continue the import with new AJAX request.
	 */
	private function set_importer_data() {
		$data = array(
			'frontend_error_messages' => $this->frontend_error_messages,
			'ajax_call_number'        => $this->ajax_call_number,
			'log_file_path'           => $this->log_file_path,
			'selected_index'          => $this->selected_index,
			'selected_import_files'   => $this->selected_import_files,
		);

		$data = array_merge( $data, $this->importer->get_importer_data() );

		set_transient( 'TDI_importer_data', $data, 0.5 * HOUR_IN_SECONDS );
	}

	/**
	 * Get content importer data, so we can continue the import with this new AJAX request.
	 */
	private function get_importer_data() {
		if ( $data = get_transient( 'TDI_importer_data' ) ) {
			$this->frontend_error_messages                = empty( $data['frontend_error_messages'] ) ? '' : $data['frontend_error_messages'];
			$this->ajax_call_number                       = empty( $data['ajax_call_number'] ) ? 1 : $data['ajax_call_number'];
			$this->log_file_path                          = empty( $data['log_file_path'] ) ? '' : $data['log_file_path'];
			$this->selected_index                         = empty( $data['selected_index'] ) ? 0 : $data['selected_index'];
			$this->selected_import_files                  = empty( $data['selected_import_files'] ) ? array() : $data['selected_import_files'];
			$this->importer->set_importer_data( $data );

			return true;
		}
		return false;
	}

	/**
	 * Load the plugin textdomain, so that translations can be made.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'theme-demo-import', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}


	/**
	 * Get data from filters, after the theme has loaded and instantiate the importer.
	 */
	public function setup_plugin_with_filter_data() {

		// Get info of import data files and filter it.
		$this->import_files = TDI_Helpers::validate_import_file_info( apply_filters( 'theme-demo-import/import_files', array() ) );

		// Importer options array.
		$importer_options = apply_filters( 'theme-demo-import/importer_options', array(
			'fetch_attachments' => true,
		) );

		// Logger options for the logger used in the importer.
		$logger_options = apply_filters( 'theme-demo-import/logger_options', array(
			'logger_min_level' => 'warning',
		) );

		// Configure logger instance and set it to the importer.
		$this->logger            = new TDI_Logger();
		$this->logger->min_level = $logger_options['logger_min_level'];

		// Create importer instance with proper parameters.
		$this->importer = new TDI_Importer( $importer_options, $this->logger );
	}
}
