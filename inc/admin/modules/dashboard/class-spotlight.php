<?php
/**
 * Dashboard spotlight.
 *
 * Fetches time-limited highlights from the WoodMart API in the background (WP-Cron)
 * and shows at most one dismissible item per admin placement.
 *
 * @package woodmart
 */

namespace XTS\Admin\Modules\Dashboard;

use XTS\Singleton;

if ( ! defined( 'WOODMART_THEME_DIR' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 * Spotlight class.
 */
class Spotlight extends Singleton {
	/**
	 * API endpoint.
	 */
	const API_URL = 'https://woodmarttheme.com/wp-json/wms/v1/spotlight';

	/**
	 * Site transient that holds the last good copy.
	 */
	const CACHE_KEY = 'woodmart_spotlight';

	/**
	 * Refresh interval after a successful request.
	 */
	const TTL = HOUR_IN_SECONDS;

	/**
	 * User meta with dismissed item IDs.
	 */
	const DISMISSED_META = 'woodmart_spotlight_dismissed';

	/**
	 * How many dismissed IDs are kept per user.
	 */
	const MAX_DISMISSED = 50;

	/**
	 * AJAX action that dismisses an item.
	 */
	const AJAX_ACTION = 'woodmart_spotlight_dismiss';

	/**
	 * Maximum number of stored items.
	 */
	const MAX_ITEMS = 20;

	/**
	 * Cached feed.
	 *
	 * @var Remote_Feed
	 */
	private $feed;

	/**
	 * Placements already rendered on the current page.
	 *
	 * @var array
	 */
	private $rendered = array();

	/**
	 * Register hooks.
	 */
	public function init() {
		$this->feed = new Remote_Feed( self::API_URL, self::CACHE_KEY, self::TTL, array( $this, 'validate' ) );

		add_action( 'admin_init', array( $this, 'maybe_schedule_refresh' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'dismiss' ) );
		add_action( 'xts_dashboard_after_header', array( $this, 'render_dashboard_placement' ) );
		add_action( 'admin_notices', array( $this, 'render_notice_placement' ) );
		add_action( 'in_admin_header', array( $this, 'render_topbar_placement' ) );
		add_action( 'xts_spotlight_placement', array( $this, 'render_placement' ) );
	}

	/**
	 * Whether the current user may see the spotlight.
	 *
	 * @return bool
	 */
	private function is_visible() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Schedule a background refresh when the cache is stale.
	 *
	 * @return void
	 */
	public function maybe_schedule_refresh() {
		if ( wp_doing_ajax() || wp_doing_cron() || ! $this->is_visible() ) {
			return;
		}

		$this->feed->maybe_schedule_refresh();
	}

	/**
	 * Allowed placement keys.
	 *
	 * @return array
	 */
	private function get_placements() {
		return array_merge( $this->get_image_placements(), $this->get_text_placements() );
	}

	/**
	 * Placements that show the item image.
	 *
	 * @return array
	 */
	private function get_image_placements() {
		return array( 'dashboard', 'theme-settings', 'prebuilt-websites', 'prebuilt-websites-sidebar', 'header-builder', 'layouts', 'wp-dashboard', 'plugins' );
	}

	/**
	 * Placements that show the item text bar.
	 *
	 * @return array
	 */
	private function get_text_placements() {
		return array( 'topbar' );
	}

	/**
	 * Validate and sanitize a decoded API response.
	 *
	 * @param mixed $body Decoded JSON body.
	 *
	 * @return array|null Sanitized data with an 'items' key, or null when the body is invalid.
	 */
	public function validate( $body ) {
		if ( ! is_array( $body ) || ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
			return null;
		}

		$items = array();

		foreach ( $body['items'] as $raw ) {
			$item = $this->validate_item( $raw );

			if ( $item ) {
				$items[] = $item;
			}
		}

		return array(
			'items' => array_slice( $items, 0, self::MAX_ITEMS ),
		);
	}

	/**
	 * Validate and sanitize a single item.
	 *
	 * @param mixed $raw Raw item from the API.
	 *
	 * @return array|null Sanitized item, or null when it is unusable.
	 */
	private function validate_item( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['title'] ) || ! is_string( $raw['title'] ) || ! isset( $raw['placements'] ) || ! is_array( $raw['placements'] ) ) {
			return null;
		}

		$image_url = isset( $raw['image']['url'] ) ? Remote_Feed::https_url( $raw['image']['url'] ) : '';
		$bar       = isset( $raw['bar'] ) && is_array( $raw['bar'] ) ? $this->validate_bar( $raw['bar'] ) : null;
		$url       = Remote_Feed::https_url( isset( $raw['url'] ) ? $raw['url'] : '' );
		$ends      = 0;
		$supported = array_merge( $image_url ? $this->get_image_placements() : array(), $bar ? $this->get_text_placements() : array() );

		// Keep only known placements this item has content for.
		$placements = array_values( array_intersect( $supported, array_filter( $raw['placements'], 'is_string' ) ) );

		if ( ! empty( $raw['ends'] ) ) {
			$ends = is_string( $raw['ends'] ) ? (int) strtotime( $raw['ends'] ) : 0;

			if ( ! $ends ) {
				return null;
			}
		}

		if ( ! $placements || ! $url ) {
			return null;
		}

		return array(
			'id'         => absint( $raw['id'] ),
			'placements' => $placements,
			'title'      => sanitize_text_field( $raw['title'] ),
			'image'      => $image_url ? array(
				'url'    => $image_url,
				'width'  => isset( $raw['image']['width'] ) ? absint( $raw['image']['width'] ) : 0,
				'height' => isset( $raw['image']['height'] ) ? absint( $raw['image']['height'] ) : 0,
			) : null,
			'bar'        => $bar,
			'url'        => $url,
			'ends'       => $ends,
		);
	}

	/**
	 * Validate the text bar.
	 *
	 * @param array $raw Raw bar data.
	 *
	 * @return array|null Sanitized bar, or null when it has no text.
	 */
	private function validate_bar( $raw ) {
		$bar = array();

		foreach ( array( 'label', 'text', 'link_label' ) as $key ) {
			$bar[ $key ] = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
		}

		return $bar['text'] ? $bar : null;
	}

	/**
	 * Get dismissed item IDs of the current user.
	 *
	 * @return array
	 */
	private function get_dismissed() {
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );

		return is_array( $dismissed ) ? array_map( 'absint', $dismissed ) : array();
	}

	/**
	 * Get the item to show in a placement.
	 *
	 * @param string $placement Placement key.
	 *
	 * @return array|null
	 */
	private function get_placement_item( $placement ) {
		$data = $this->feed->get_data();

		if ( ! $data || ! $this->is_visible() ) {
			return null;
		}

		$dismissed = $this->get_dismissed();

		foreach ( $data['items'] as $item ) {
			if ( in_array( $placement, $item['placements'], true ) && ( ! $item['ends'] || $item['ends'] > time() ) && ! in_array( $item['id'], $dismissed, true ) ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * AJAX: dismiss an item for the current user.
	 *
	 * @return void
	 */
	public function dismiss() {
		check_ajax_referer( self::AJAX_ACTION, 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( ! $id ) {
			wp_send_json_error( null, 400 );
		}

		$dismissed   = array_values( array_diff( $this->get_dismissed(), array( $id ) ) );
		$dismissed[] = $id;

		update_user_meta( get_current_user_id(), self::DISMISSED_META, array_slice( $dismissed, -self::MAX_DISMISSED ) );

		wp_send_json_success();
	}

	/**
	 * Render the placement for the current WoodMart dashboard page, right below the dashboard header.
	 *
	 * @return void
	 */
	public function render_dashboard_placement() {
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = get_current_screen();
		$pages  = array(
			'xts_dashboard'         => 'dashboard',
			'xts_theme_settings'    => 'theme-settings',
			'xts_prebuilt_websites' => 'prebuilt-websites',
			'xts_header_builder'    => 'header-builder',
		);

		if ( isset( $pages[ $page ] ) ) {
			$this->render_placement( $pages[ $page ] );
		} elseif ( $screen && 'edit-woodmart_layout' === $screen->id ) {
			$this->render_placement( 'layouts' );
		}
	}

	/**
	 * Render the WordPress Dashboard and Plugins placements as admin notices.
	 *
	 * @return void
	 */
	public function render_notice_placement() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		if ( 'dashboard' === $screen->id ) {
			$this->render_placement( 'wp-dashboard', true );
		} elseif ( 'plugins' === $screen->id ) {
			$this->render_placement( 'plugins', true );
		}
	}

	/**
	 * Render the text bar above the theme dashboard header.
	 *
	 * @return void
	 */
	public function render_topbar_placement() {
		if ( \XTS\Admin\Modules\Dashboard::get_instance()->is_theme_page() ) {
			$this->render_placement( 'topbar' );
		}
	}

	/**
	 * Render the item for a placement.
	 *
	 * @param string $placement   Placement key.
	 * @param bool   $notice Whether to render it as a WordPress admin notice.
	 *
	 * @return void
	 */
	public function render_placement( $placement, $notice = false ) {
		if ( isset( $this->rendered[ $placement ] ) ) {
			return;
		}

		$item = $this->get_placement_item( $placement );

		if ( ! $item ) {
			return;
		}

		$this->rendered[ $placement ] = true;

		wp_enqueue_script( 'wd-spotlight', WOODMART_ASSETS . '/js/spotlight.js', array(), WOODMART_VERSION, true );
		wp_localize_script(
			'wd-spotlight',
			'woodmartSpotlight',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'security' => wp_create_nonce( self::AJAX_ACTION ),
			)
		);

		$classes = 'xts-spotlight xts-spotlight-' . $placement . ( $notice ? ' notice xts-spotlight-notice' : '' );

		if ( in_array( $placement, $this->get_text_placements(), true ) ) {
			$this->render_bar( $item, $classes );
			return;
		}

		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-id="<?php echo esc_attr( $item['id'] ); ?>">
			<a class="xts-spotlight-link" href="<?php echo esc_url( $item['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<img src="<?php echo esc_url( $item['image']['url'], array( 'https' ) ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>"<?php echo $item['image']['width'] ? ' width="' . esc_attr( $item['image']['width'] ) . '"' : ''; ?><?php echo $item['image']['height'] ? ' height="' . esc_attr( $item['image']['height'] ) . '"' : ''; ?> decoding="async">
			</a>
			<button type="button" class="xts-spotlight-close" aria-label="<?php esc_attr_e( 'Dismiss', 'woodmart' ); ?>">
				<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M1.5 1.5l9 9M10.5 1.5l-9 9"/></svg>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the text bar.
	 *
	 * @param array  $item    Item.
	 * @param string $classes Wrapper classes.
	 *
	 * @return void
	 */
	private function render_bar( $item, $classes ) {
		$bar = $item['bar'];

		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-id="<?php echo esc_attr( $item['id'] ); ?>">
			<p class="xts-spotlight-bar-text">
				<?php if ( $bar['label'] ) : ?>
					<strong><?php echo esc_html( $bar['label'] ); ?></strong>
				<?php endif; ?>
				<?php echo esc_html( $bar['text'] ); ?>
				<a href="<?php echo esc_url( $item['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $bar['link_label'] ? $bar['link_label'] : __( 'Learn more', 'woodmart' ) ); ?></a>
			</p>
			<button type="button" class="xts-spotlight-close" aria-label="<?php esc_attr_e( 'Dismiss', 'woodmart' ); ?>">
				<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M1.5 1.5l9 9M10.5 1.5l-9 9"/></svg>
			</button>
		</div>
		<?php
	}
}

Spotlight::get_instance();
