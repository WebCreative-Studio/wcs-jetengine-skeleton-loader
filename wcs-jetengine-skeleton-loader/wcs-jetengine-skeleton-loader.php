<?php
/**
 * Plugin Name: WCS JetEngine Skeleton Loader
 * Description: Добавляет опциональный скелетон-загрузчик карточек в Listing Grid JetEngine для Elementor.
 * Version: 1.3.4
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Author: WebCreative Studio
 * License: GPL-2.0-or-later
 * Text Domain: wcs-jetengine-skeleton-loader
 */

defined( 'ABSPATH' ) || exit;

final class WCS_JetEngine_Skeleton_Loader {

	const VERSION = '1.3.4';
	const PLUGIN_SLUG = 'wcs-jetengine-skeleton-loader';
	const UPDATE_MANIFEST_URL = 'https://web-creative.studio/wcs-plugins-update/wcs-jetengine-skeleton-loader/metadata.json';
	const UPDATE_CACHE_KEY = 'wcs_jetengine_skeleton_update_manifest';
	const COLOR_OPTION = 'wcs_jetengine_skeleton_color';
	const COLOR_CONTROL = 'wcs_skeleton_color';
	const DEFAULT_COLOR = '#edf5ef';
	const BOOTSTRAP_FILE = 'wcs-jetengine-skeleton-loader-bootstrap.php';
	const BOOTSTRAP_MARKER = 'WCS_JETENGINE_SKELETON_LOADER_BOOTSTRAP';

	public function __construct() {
		// JetEngine registers Elementor controls before ordinary plugins load.
		add_action( 'jet-engine/listing/after-general-settings', array( $this, 'register_content_controls' ), 10, 1 );
		// Style section must start only after Content section_general is closed.
		add_action( 'elementor/element/jet-listing-grid/section_general/after_section_end', array( $this, 'register_style_controls' ) );
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
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
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

	/**
	 * Normalize a color from Elementor/settings for safe CSS use.
	 * Accepts #RGB, #RRGGBB, #RRGGBBAA, rgb()/rgba(), and Elementor global CSS variables.
	 *
	 * @param mixed $color Raw color value.
	 * @return string Sanitized CSS color or empty string when invalid.
	 */
	public function sanitize_color( $color ) {
		$color = trim( wp_unslash( (string) $color ) );
		if ( '' === $color ) {
			return '';
		}

		$hex = sanitize_hex_color( $color );
		if ( $hex ) {
			return strtolower( $hex );
		}

		// Elementor color picker often stores 8-digit hex with alpha: #RRGGBBAA.
		if ( preg_match( '/^#([A-Fa-f0-9]{8})$/', $color, $matches ) ) {
			return '#' . strtolower( $matches[1] );
		}

		if ( preg_match( '/^var\(--e-global-color-[a-zA-Z0-9_-]+\)$/', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^rgba?\(\s*[\d.]+%?(?:\s*,\s*|\s+)[\d.]+%?(?:\s*,\s*|\s+)[\d.]+%?(?:\s*[,\/]\s*[\d.]+%?\s*)?\)$/i', $color ) ) {
			return preg_replace( '/\s+/', ' ', $color );
		}

		return '';
	}

	/**
	 * Parse a sanitized color into RGBA components.
	 *
	 * @param string $color Sanitized CSS color.
	 * @return array{r:int,g:int,b:int,a:float}|null
	 */
	private function parse_rgba( $color ) {
		$color = $this->sanitize_color( $color );
		if ( '' === $color ) {
			return null;
		}

		if ( preg_match( '/^#([a-f0-9]{3})$/i', $color, $matches ) ) {
			$short = strtolower( $matches[1] );
			return array(
				'r' => hexdec( $short[0] . $short[0] ),
				'g' => hexdec( $short[1] . $short[1] ),
				'b' => hexdec( $short[2] . $short[2] ),
				'a' => 1.0,
			);
		}

		if ( preg_match( '/^#([a-f0-9]{6})([a-f0-9]{2})?$/i', $color, $matches ) ) {
			$rgb = strtolower( $matches[1] );
			$alpha = isset( $matches[2] ) ? hexdec( $matches[2] ) / 255 : 1.0;
			return array(
				'r' => hexdec( substr( $rgb, 0, 2 ) ),
				'g' => hexdec( substr( $rgb, 2, 2 ) ),
				'b' => hexdec( substr( $rgb, 4, 2 ) ),
				'a' => $alpha,
			);
		}

		if ( preg_match( '/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+%?))?\s*\)$/i', $color, $matches ) ) {
			$alpha = 1.0;
			if ( isset( $matches[4] ) && '' !== $matches[4] ) {
				$alpha = false !== strpos( $matches[4], '%' ) ? ( (float) $matches[4] ) / 100 : (float) $matches[4];
			}
			return array(
				'r' => (int) round( (float) $matches[1] ),
				'g' => (int) round( (float) $matches[2] ),
				'b' => (int) round( (float) $matches[3] ),
				'a' => max( 0, min( 1, $alpha ) ),
			);
		}

		return null;
	}

	/**
	 * Format RGBA components as a CSS color, preferring rgba() when alpha < 1.
	 *
	 * @param array{r:int,g:int,b:int,a:float} $rgba Parsed color.
	 */
	private function format_rgba( array $rgba ) {
		$r = max( 0, min( 255, (int) $rgba['r'] ) );
		$g = max( 0, min( 255, (int) $rgba['g'] ) );
		$b = max( 0, min( 255, (int) $rgba['b'] ) );
		$a = max( 0, min( 1, (float) $rgba['a'] ) );
		if ( $a >= 0.999 ) {
			return sprintf( '#%02x%02x%02x', $r, $g, $b );
		}
		$a_str = rtrim( rtrim( number_format( $a, 4, '.', '' ), '0' ), '.' );
		if ( '' === $a_str || '.' === $a_str ) {
			$a_str = '0';
		}
		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, $a_str );
	}

	/**
	 * Resolve skeleton color for a widget: Elementor Style control, then legacy site option, then default.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string Sanitized color, or empty string when an Elementor global binding should own the value.
	 */
	private function get_widget_color( $settings ) {
		if ( ! empty( $settings[ self::COLOR_CONTROL ] ) ) {
			$color = $this->sanitize_color( $settings[ self::COLOR_CONTROL ] );
			if ( '' !== $color ) {
				return $color;
			}
		}

		// Global color bindings are applied by Elementor selectors; do not override them with the legacy option.
		if ( ! empty( $settings['__globals__'][ self::COLOR_CONTROL ] ) ) {
			return '';
		}

		$legacy = $this->sanitize_color( get_option( self::COLOR_OPTION, self::DEFAULT_COLOR ) );
		return '' !== $legacy ? $legacy : self::DEFAULT_COLOR;
	}

	/**
	 * Build CSS custom properties for base/highlight/border while preserving alpha.
	 *
	 * @param string $color Sanitized widget color.
	 * @return array{base:string,highlight:string,border:string}
	 */
	private function skeleton_color_vars( $color ) {
		$parsed = $this->parse_rgba( $color );
		if ( null === $parsed ) {
			// Global CSS variables and other non-parseable values: keep as-is and let CSS color-mix derive siblings.
			return array(
				'base' => $color,
				'highlight' => '',
				'border' => '',
			);
		}

		$base = $this->format_rgba( $parsed );
		$highlight = $this->format_rgba( $this->blend_rgba_toward_white( $parsed, 0.55 ) );
		$border = $this->format_rgba( $this->blend_rgba_toward_white( $parsed, 0.25 ) );

		return array(
			'base' => $base,
			'highlight' => $highlight,
			'border' => $border,
		);
	}

	/**
	 * Lighten RGB toward white and keep the original alpha channel.
	 *
	 * @param array{r:int,g:int,b:int,a:float} $rgba Parsed color.
	 * @param float                             $amount 0..1 blend toward white.
	 * @return array{r:int,g:int,b:int,a:float}
	 */
	private function blend_rgba_toward_white( array $rgba, $amount ) {
		$amount = min( 1, max( 0, (float) $amount ) );
		return array(
			'r' => (int) round( $rgba['r'] + ( 255 - $rgba['r'] ) * $amount ),
			'g' => (int) round( $rgba['g'] + ( 255 - $rgba['g'] ) * $amount ),
			'b' => (int) round( $rgba['b'] + ( 255 - $rgba['b'] ) * $amount ),
			'a' => $rgba['a'],
		);
	}

	public function register_content_controls( $element ) {
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
	}

	public function register_style_controls( $element ) {
		$element->start_controls_section(
			'wcs_skeleton_style_section',
			array(
				'label' => esc_html__( 'Скелетон-загрузчик', 'wcs-jetengine-skeleton-loader' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'lazy_load' => 'yes',
					'wcs_skeleton_loader' => 'yes',
				),
			)
		);

		$element->add_control(
			self::COLOR_CONTROL,
			array(
				'label' => esc_html__( 'Основной цвет', 'wcs-jetengine-skeleton-loader' ),
				'description' => esc_html__( 'Цвет скелетона для этого листинга. Блик и тонкая рамка подбираются автоматически. Если не задан, используется прежний цвет сайта или значение по умолчанию.', 'wcs-jetengine-skeleton-loader' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}}' => '--wcs-skeleton-base: {{VALUE}};',
				),
			)
		);

		$element->end_controls_section();
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
		$base = $this->get_widget_color( $settings );

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

		if ( '' !== $base ) {
			$vars = $this->skeleton_color_vars( $base );
			$widget->add_render_attribute( '_wrapper', 'data-wcs-skeleton-base', $vars['base'] );
			$style = '--wcs-skeleton-base:' . esc_attr( $vars['base'] ) . ';';
			if ( $vars['highlight'] ) {
				$style .= '--wcs-skeleton-highlight:' . esc_attr( $vars['highlight'] ) . ';';
			}
			if ( $vars['border'] ) {
				$style .= '--wcs-skeleton-border:' . esc_attr( $vars['border'] ) . ';';
			}
			$widget->add_render_attribute( '_wrapper', 'style', $style );
		}
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

	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ plugin_basename( __FILE__ ) ] ) ) {
			return $transient;
		}

		$manifest = $this->get_update_manifest();
		if ( empty( $manifest ) || ! version_compare( self::VERSION, $manifest['version'], '<' ) ) {
			return $transient;
		}

		$transient->response[ plugin_basename( __FILE__ ) ] = (object) array(
			'slug' => self::PLUGIN_SLUG,
			'plugin' => plugin_basename( __FILE__ ),
			'new_version' => $manifest['version'],
			'url' => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'package' => $manifest['download_url'],
			'requires' => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
		);

		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = $this->get_update_manifest();
		if ( empty( $manifest ) ) {
			return $result;
		}

		return (object) array(
			'name' => isset( $manifest['name'] ) ? $manifest['name'] : 'WCS JetEngine Skeleton Loader',
			'slug' => self::PLUGIN_SLUG,
			'version' => $manifest['version'],
			'homepage' => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'requires' => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'download_link' => $manifest['download_url'],
			'sections' => array(
				'description' => isset( $manifest['description'] ) ? wp_kses_post( $manifest['description'] ) : '',
				'changelog' => isset( $manifest['changelog'] ) ? wp_kses_post( $manifest['changelog'] ) : '',
			),
		);
	}

	private function get_update_manifest() {
		$cache_key = self::UPDATE_CACHE_KEY . '_' . self::VERSION;
		$manifest = get_site_transient( $cache_key );
		if ( is_array( $manifest ) ) {
			return $manifest;
		}

		$response = wp_safe_remote_get( self::UPDATE_MANIFEST_URL, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) || ! $this->is_trusted_package_url( $manifest['download_url'] ) ) {
			return array();
		}

		$manifest['version'] = preg_replace( '/[^0-9A-Za-z.\-_]/', '', (string) $manifest['version'] );
		$manifest['download_url'] = esc_url_raw( $manifest['download_url'] );
		if ( '' === $manifest['version'] || '' === $manifest['download_url'] ) {
			return array();
		}

		set_site_transient( $cache_key, $manifest, HOUR_IN_SECONDS );
		return $manifest;
	}

	private function is_trusted_package_url( $url ) {
		$expected = wp_parse_url( self::UPDATE_MANIFEST_URL );
		$package = wp_parse_url( esc_url_raw( $url ) );
		return ! empty( $package['scheme'] ) && 'https' === $package['scheme'] && ! empty( $expected['host'] ) && isset( $package['host'] ) && $expected['host'] === $package['host'] && 0 === strpos( $url, 'https://web-creative.studio/wcs-plugins-update/' . self::PLUGIN_SLUG . '/' );
	}
}

register_activation_hook( __FILE__, array( 'WCS_JetEngine_Skeleton_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCS_JetEngine_Skeleton_Loader', 'deactivate' ) );
new WCS_JetEngine_Skeleton_Loader();
