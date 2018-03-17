<?php

// File Security Check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class The7_Install
 */
class The7_Install {

	/**
	 * @var The7_Background_Updater
	 */
    private static $background_updater;

	/**
	 * @var array
	 */
	private static $update_callbacks = array(
		'5.5.0' => array(
			'the7_update_550_fancy_titles_parallax',
			'the7_update_550_fancy_titles_font_size',
			'the7_update_550_fancy_subtitles_font_size',
			'the7_update_550_db_version',
		),
		'6.0.0' => array(
			'the7_mass_regenerate_short_codes_inline_css',
			'the7_update_600_db_version',
		),
		'6.1.0' => array(
			'the7_update_610_db_version',
		),
		'6.1.1' => array(
			'the7_update_611_page_transparent_top_bar_migration',
			'the7_update_611_db_version',
		),
		'6.2.0' => array(
			'the7_mass_regenerate_short_codes_inline_css',
			'the7_update_620_db_version',
		),
	);

    public static function init() {
	    add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
	    add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
	    add_action( 'init', array( __CLASS__, 'check_version' ) );
	    add_action( 'init', array( __CLASS__, 'show_db_update_notices' ) );
	    add_action( 'init', array( __CLASS__, 'upgrade_stylesheets_action' ) );
    }

    public static function check_version() {
	    $current_db_version = self::get_db_version();

	    // No db version? New install.
	    if ( is_null( $current_db_version ) ) {
		    self::update_db_version();

		    // Dismiss updated notice.
		    the7_admin_notices()->dismiss_notice( 'the7_updated' );
	    }
    }

	/**
	 * Init background updates
	 */
	public static function init_background_updater() {
		include_once( dirname( __FILE__ ) . '/class-the7-background-updater.php' );
		self::$background_updater = new The7_Background_Updater();
	}

	/**
	 * Install actions when a update button is clicked within the admin area.
	 *
	 * This function is hooked into admin_init to affect admin only.
	 */
	public static function install_actions() {
		if ( ! empty( $_GET['do_update_the7'] ) ) {
			self::update();
			wp_safe_redirect( add_query_arg( 'do_updating_the7', 'true', admin_url( 'admin.php?page=the7-dashboard' ) ) );
			exit;
		}
		if ( ! empty( $_GET['force_update_the7'] ) ) {
			do_action( 'wp_the7_updater_cron' );
			wp_safe_redirect( admin_url( 'admin.php?page=the7-dashboard' ) );
			exit;
		}
	}

	public static function update_notice() {
		include( dirname( __FILE__ ) . '/views/html-notice-update.php' );
	}

	public static function updating_notice() {
		include( dirname( __FILE__ ) . '/views/html-notice-updating.php' );
	}

	public static function updated_notice() {
		include( dirname( __FILE__ ) . '/views/html-notice-updated.php' );
	}

	private static function get_update_callbacks() {
		return self::$update_callbacks;
	}

	/**
	 * Push all needed DB updates to the queue for processing.
	 */
	public static function update() {
		$db_version = self::get_db_version();
		$update_queued = false;

		// Update the7 options.
		self::$background_updater->push_to_queue( array( __CLASS__, 'update_theme_options' ) );

		$db_update_callbacks = self::get_update_callbacks();

		// Update db.
		foreach ( $db_update_callbacks as $version => $update_callbacks ) {
			if ( version_compare( $db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
	}

	public static function show_db_update_notices() {
		$db_version = self::get_db_version();

		if ( version_compare( $db_version, PRESSCORE_DB_VERSION, '<' ) ) {
			$updater = new The7_Background_Updater();
			if ( $updater->is_updating() || ! empty( $_GET['do_updating_the7'] ) ) {
				the7_admin_notices()->reset( 'the7_updated' );
				the7_admin_notices()->add( 'the7_updating', array( __CLASS__, 'updating_notice' ), 'the7-dashboard-notice' );
			} else {
				the7_admin_notices()->add( 'the7_update', array( __CLASS__, 'update_notice' ), 'the7-dashboard-notice' );
			}
		} else {
			the7_admin_notices()->add( 'the7_updated', array( __CLASS__, 'updated_notice' ), 'the7-dashboard-notice updated is-dismissible' );
		}
	}

	public static function update_db_version( $version = null ) {
		delete_option( 'the7_db_version' );
		add_option( 'the7_db_version', is_null( $version ) ? PRESSCORE_DB_VERSION : $version );
	}

	public static function get_db_version() {
	    return get_option( 'the7_db_version', null );
    }

	public static function upgrade_stylesheets_action() {
		if ( version_compare( get_option( 'the7_style_version' ), PRESSCORE_STYLESHEETS_VERSION, '<' ) ) {
			_optionsframework_delete_defaults_cache();

			self::regenerate_stylesheets();

			update_option( 'the7_style_version', PRESSCORE_STYLESHEETS_VERSION );
		}
	}

	public static function regenerate_stylesheets() {
		presscore_refresh_dynamic_css();
	}

	public static function update_theme_options() {
		$cur_db_version = self::get_db_version();
		$options = optionsframework_get_options();
		if ( ! $options ) {
			return;
		}

		$patches_dir = trailingslashit( trailingslashit( dirname( __FILE__ ) ) . 'patches' );
		require_once( $patches_dir . 'interface-the7-db-patch.php' );

		$patches = array(
			'3.5.0' => 'The7_DB_Patch_030500',
			'4.0.0' => 'The7_DB_Patch_040000',
			'4.0.3' => 'The7_DB_Patch_040003',
			'5.0.3' => 'The7_DB_Patch_050003',
			'5.1.6' => 'The7_DB_Patch_050106',
			'5.2.0' => 'The7_DB_Patch_050200',
			'5.3.0' => 'The7_DB_Patch_050300',
			'5.4.0' => 'The7_DB_Patch_050400',
			'6.0.0' => 'The7_DB_Patch_060000',
			'6.1.0' => 'The7_DB_Patch_060100',
			'6.1.1' => 'The7_DB_Patch_060101',
		);

		$update_options = false;
		foreach ( $patches as $ver => $class_name ) {
			if ( version_compare( $ver, $cur_db_version ) <= 0 ) {
				continue;
			}

			include $patches_dir . 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

			$patch = new $class_name();
			$options = $patch->apply( $options );
			$update_options = true;
		}

		if ( $update_options ) {
			update_option( optionsframework_get_options_id(), $options );
			_optionsframework_delete_defaults_cache();
		}
	}
}