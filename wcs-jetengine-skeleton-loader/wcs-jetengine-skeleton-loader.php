<?php
/**
 * Plugin Name: WCS JetEngine Skeleton Loader
 * Description: Добавляет опциональный скелетон-загрузчик карточек в Listing Grid JetEngine для Elementor.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Author: WebCreative Studio
 * License: GPL-2.0-or-later
 * Text Domain: wcs-jetengine-skeleton-loader
 */

defined( 'ABSPATH' ) || exit;

final class WCS_JetEngine_Skeleton_Loader {

	const VERSION = '1.0.0';
	const BOOTSTRAP_FILE = 'wcs-jetengine-skeleton-loader-bootstrap.php';
	const BOOTSTRAP_MARKER = 'WCS_JETENGINE_SKELETON_LOADER_BOOTSTRAP';

	public function __construct() {
		// JetEngine registers Elementor controls before ordinary plugins load.
		add_action( 'jet-engine/listing/after-general-settings', array( $this, 'register_controls' ), 10, 1 );
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
	}

	public static function activate() {
		self::install_bootstrap();
	}

	public static function deactivate() {
		self::remove_bootstrap();
	}

	public static function install_bootstrap() {
		$target = trailingslashit( WPMU_PLUGIN_DIR ) . self::BOOTSTRAP_FILE;
		$source = plugin_dir_path( __FILE__ ) . 'bootstrap/' . self::BOOTSTRAP_FILE;

		if ( ! file_exists( $source ) || ( file_exists( $target ) && ! self::is_our_bootstrap( $target ) ) ) {
			set_transient( 'wcs_jetengine_skeleton_bootstrap_notice', 1, MINUTE_IN_SECONDS );
			return false;
		}

		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) || false === file_put_contents( $target, file_get_contents( $source ), LOCK_EX ) ) {
			set_transient( 'wcs_jetengine_skeleton_bootstrap_notice', 1, MINUTE_IN_SECONDS );
			return false;
		}

		return true;
	}

	public static function remove_bootstrap() {
		$target = trailingslashit( WPMU_PLUGIN_DIR ) . self::BOOTSTRAP_FILE;
		if ( file_exists( $target ) && self::is_our_bootstrap( $target ) ) {
			return unlink( $target );
		}

		return true;
	}

	private static function is_our_bootstrap( $path ) {
		$content = file_get_contents( $path );
		return false !== $content && false !== strpos( $content, self::BOOTSTRAP_MARKER );
	}

	public function bootstrap() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( 'Jet_Engine' ) ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		add_action( 'elementor/frontend/widget/before_render', array( $this, 'add_widget_attributes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'admin_notices', array( $this, 'bootstrap_notice' ) );
	}

	public function dependency_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WCS JetEngine Skeleton Loader requires active Elementor and JetEngine plugins.', 'wcs-jetengine-skeleton-loader' ) . '</p></div>';
		}
	}

	public function bootstrap_notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'wcs_jetengine_skeleton_bootstrap_notice' ) ) {
			delete_transient( 'wcs_jetengine_skeleton_bootstrap_notice' );
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WCS JetEngine Skeleton Loader could not create its MU bootstrap. Check write access to wp-content/mu-plugins and reactivate the plugin.', 'wcs-jetengine-skeleton-loader' ) . '</p></div>';
		}
	}

	public function register_controls( $element ) {
		$element->add_control( 'wcs_skeleton_loader', array(
			'label' => esc_html__( 'Скелетон-загрузчик', 'wcs-jetengine-skeleton-loader' ),
			'description' => esc_html__( 'Показывать карточки-заглушки, пока Listing Grid загружается.', 'wcs-jetengine-skeleton-loader' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'label_on' => esc_html__( 'Вкл.', 'wcs-jetengine-skeleton-loader' ),
			'label_off' => esc_html__( 'Выкл.', 'wcs-jetengine-skeleton-loader' ),
			'return_value' => 'yes',
			'default' => '',
			'condition' => array( 'lazy_load' => 'yes' ),
		) );

		$element->add_control( 'wcs_skeleton_filters', array(
			'label' => esc_html__( 'Показывать при фильтрации JetSmartFilters', 'wcs-jetengine-skeleton-loader' ),
			'description' => esc_html__( 'Также показывать скелетон во время связанного запроса JetSmartFilters.', 'wcs-jetengine-skeleton-loader' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'label_on' => esc_html__( 'Вкл.', 'wcs-jetengine-skeleton-loader' ),
			'label_off' => esc_html__( 'Выкл.', 'wcs-jetengine-skeleton-loader' ),
			'return_value' => 'yes',
			'default' => '',
			'condition' => array( 'wcs_skeleton_loader' => 'yes' ),
		) );
	}

	public function add_widget_attributes( $widget ) {
		if ( 'jet-listing-grid' !== $widget->get_name() ) {
			return;
		}
		$settings = $widget->get_settings_for_display();
		if ( empty( $settings['wcs_skeleton_loader'] ) || 'yes' !== $settings['wcs_skeleton_loader'] ) {
			return;
		}

		$desktop = $this->get_grid_columns( $settings, 'columns', 3 );
		$tablet = $this->get_grid_columns( $settings, 'columns_tablet', $desktop );
		$mobile = $this->get_grid_columns( $settings, 'columns_mobile', $tablet );
		$devices = isset( $settings['scroll_slider_on'] ) && is_array( $settings['scroll_slider_on'] ) ? $settings['scroll_slider_on'] : array();
		$scroll = ! empty( $settings['scroll_slider_enabled'] ) && 'yes' === $settings['scroll_slider_enabled'];

		$widget->add_render_attribute( '_wrapper', 'class', 'wcs-jetengine-skeleton-enabled' );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-columns-desktop', (string) $desktop );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-columns-tablet', (string) $tablet );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-columns-mobile', (string) $mobile );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-tablet', $scroll && in_array( 'tablet', $devices, true ) ? 'yes' : 'no' );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-mobile', $scroll && in_array( 'mobile', $devices, true ) ? 'yes' : 'no' );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-width-tablet', $this->get_dimension( $settings, 'static_column_width_tablet', '40%' ) );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-width-mobile', $this->get_dimension( $settings, 'static_column_width_mobile', '75%' ) );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-gap-tablet', $this->get_dimension( $settings, 'horizontal_gap_tablet', '16px' ) );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-scroll-gap-mobile', $this->get_dimension( $settings, 'horizontal_gap_mobile', '5px' ) );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-carousel', ! empty( $settings['carousel_enabled'] ) && 'yes' === $settings['carousel_enabled'] ? 'yes' : 'no' );
		$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-filters', ! empty( $settings['wcs_skeleton_filters'] ) ? 'yes' : 'no' );
	}

	private function get_grid_columns( $settings, $key, $fallback ) {
		$value = isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
		return is_numeric( $value ) ? min( 12, max( 1, absint( $value ) ) ) : $fallback;
	}

	private function get_dimension( $settings, $key, $fallback ) {
		if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) || ! isset( $settings[ $key ]['size'], $settings[ $key ]['unit'] ) ) {
			return $fallback;
		}
		$size = (float) $settings[ $key ]['size'];
		$unit = $settings[ $key ]['unit'];
		return $size > 0 && in_array( $unit, array( 'px', '%', 'vw' ), true ) ? $size . $unit : $fallback;
	}

	public function enqueue_assets() {
		$path = plugin_dir_path( __FILE__ ) . 'assets/';
		$url = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_style( 'wcs-jetengine-skeleton-loader', $url . 'skeleton-loader.css', array(), self::VERSION );
		wp_enqueue_script( 'wcs-jetengine-skeleton-loader', $url . 'skeleton-loader.js', array(), self::VERSION, true );
	}
}

register_activation_hook( __FILE__, array( 'WCS_JetEngine_Skeleton_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCS_JetEngine_Skeleton_Loader', 'deactivate' ) );
new WCS_JetEngine_Skeleton_Loader();
