<?php
/**
 * Dashboard announcements sidebar.
 *
 * Fetches news, offers and update notes from the WoodMart API in the background (WP-Cron),
 * caches them in a site transient and renders them in an off-canvas panel in the dashboard header.
 *
 * @package woodmart
 */

namespace XTS\Admin\Modules\Dashboard;

use XTS\Singleton;

if ( ! defined( 'WOODMART_THEME_DIR' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 * Announcements class.
 */
class Announcements extends Singleton {
	/**
	 * API endpoint.
	 */
	const API_URL = 'https://woodmarttheme.com/wp-json/wms/v1/announcements?per_page=10';

	/**
	 * Site transient that holds the last good copy, its ETag and the next check time.
	 */
	const CACHE_KEY = 'woodmart_announcements';

	/**
	 * User meta with the timestamp of the newest announcement the user has seen.
	 */
	const LAST_SEEN_META = 'woodmart_announcements_last_seen';

	/**
	 * AJAX action that marks announcements as seen.
	 */
	const AJAX_ACTION = 'woodmart_announcements_seen';

	/**
	 * Maximum number of stored items.
	 */
	const MAX_ITEMS = 20;

	/**
	 * Refresh interval after a successful request.
	 */
	const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Cached feed.
	 *
	 * @var Remote_Feed
	 */
	private $feed;

	/**
	 * Whether the header button was rendered on the current page.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Register hooks.
	 */
	public function init() {
		$this->feed = new Remote_Feed( self::API_URL, self::CACHE_KEY, self::TTL, array( $this, 'validate' ) );

		add_action( 'admin_init', array( $this, 'maybe_schedule_refresh' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'mark_seen' ) );
		add_action( 'admin_footer', array( $this, 'render_panel' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Whether the current user may see announcements.
	 *
	 * @return bool
	 */
	private function is_visible() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Schedule a background refresh when the cache is stale. Never fetches inline.
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
	 * Validate and sanitize a decoded API response.
	 *
	 * @param mixed $body Decoded JSON body.
	 *
	 * @return array|null Sanitized data with 'latest' and 'items' keys, or null when the body is invalid.
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

		usort(
			$items,
			function ( $a, $b ) {
				return $b['date'] - $a['date'];
			}
		);

		$items = array_slice( $items, 0, self::MAX_ITEMS );

		return array(
			'latest' => $items ? $items[0]['date'] : 0,
			'items'  => $items,
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
		if ( ! is_array( $raw ) || empty( $raw['date'] ) || empty( $raw['title'] ) || ! is_string( $raw['date'] ) || ! is_string( $raw['title'] ) ) {
			return null;
		}

		$date = strtotime( $raw['date'] );

		if ( ! $date ) {
			return null;
		}

		$kind  = isset( $raw['kind'] ) && is_string( $raw['kind'] ) && isset( $this->get_kinds()[ $raw['kind'] ] ) ? $raw['kind'] : 'news';
		$image = null;
		$link  = null;

		if ( isset( $raw['image'] ) && is_array( $raw['image'] ) ) {
			$url = Remote_Feed::https_url( isset( $raw['image']['url'] ) ? $raw['image']['url'] : '' );

			if ( $url ) {
				$image = array(
					'url'    => $url,
					'width'  => isset( $raw['image']['width'] ) ? absint( $raw['image']['width'] ) : 0,
					'height' => isset( $raw['image']['height'] ) ? absint( $raw['image']['height'] ) : 0,
					'alt'    => isset( $raw['image']['alt'] ) && is_string( $raw['image']['alt'] ) ? sanitize_text_field( $raw['image']['alt'] ) : '',
				);
			}
		}

		if ( isset( $raw['link'] ) && is_array( $raw['link'] ) ) {
			$url = Remote_Feed::https_url( isset( $raw['link']['url'] ) ? $raw['link']['url'] : '' );

			if ( $url ) {
				$link = array(
					'url'   => $url,
					'label' => isset( $raw['link']['label'] ) && is_string( $raw['link']['label'] ) ? sanitize_text_field( $raw['link']['label'] ) : '',
				);
			}
		}

		return array(
			'id'      => isset( $raw['id'] ) ? absint( $raw['id'] ) : 0,
			'date'    => $date,
			'kind'    => $kind,
			'title'   => sanitize_text_field( $raw['title'] ),
			'summary' => isset( $raw['summary'] ) && is_string( $raw['summary'] ) ? sanitize_text_field( $raw['summary'] ) : '',
			'content' => isset( $raw['content'] ) && is_string( $raw['content'] ) ? $this->kses( $raw['content'] ) : '',
			'image'   => $image,
			'link'    => $link,
		);
	}

	/**
	 * Sanitize announcement HTML with a small allowlist and force safe link attributes.
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string
	 */
	private function kses( $html ) {
		$html = wp_kses(
			$html,
			array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'i'      => array(),
				'code'   => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'a'      => array(
					'href' => array(),
				),
			),
			array( 'https' )
		);

		// Keep only https:// links, open them in a new tab; unwrap the rest.
		$html = preg_replace_callback(
			'/<a\b[^>]*>/i',
			function ( $matches ) {
				$url = preg_match( '/\bhref="([^"]*)"/i', $matches[0], $href ) ? Remote_Feed::https_url( html_entity_decode( $href[1] ) ) : '';

				return $url ? '<a href="' . esc_url( $url, array( 'https' ) ) . '" target="_blank" rel="noopener noreferrer">' : '<a>';
			},
			$html
		);

		return $html;
	}

	/**
	 * Allowed kinds and their labels.
	 *
	 * @return array
	 */
	private function get_kinds() {
		return array(
			'news'     => esc_html__( 'News', 'woodmart' ),
			'feature'  => esc_html__( 'New feature', 'woodmart' ),
			'update'   => esc_html__( 'Theme update', 'woodmart' ),
			'offer'    => esc_html__( 'Offer', 'woodmart' ),
			'tip'      => esc_html__( 'Tip', 'woodmart' ),
			'notice'   => esc_html__( 'Important notice', 'woodmart' ),
			'security' => esc_html__( 'Security update', 'woodmart' ),
			'video'    => esc_html__( 'New video', 'woodmart' ),
			'tutorial' => esc_html__( 'Tutorial', 'woodmart' ),
			'article'  => esc_html__( 'Blog post', 'woodmart' ),
			'demo'     => esc_html__( 'New prebuilt website', 'woodmart' ),
			'release'  => esc_html__( 'Release notes', 'woodmart' ),
			'plugin'   => esc_html__( 'Plugin update', 'woodmart' ),
			'event'    => esc_html__( 'Event', 'woodmart' ),
			'survey'   => esc_html__( 'Survey', 'woodmart' ),
		);
	}

	/**
	 * Get cached items.
	 *
	 * @return array
	 */
	private function get_items() {
		$data = $this->feed->get_data();

		return $data ? $data['items'] : array();
	}

	/**
	 * Get the date of the newest cached item.
	 *
	 * @return int
	 */
	private function get_latest() {
		$data = $this->feed->get_data();

		return $data ? (int) $data['latest'] : 0;
	}

	/**
	 * Get the current user's last seen timestamp.
	 *
	 * On first run only the newest item stays unread, so a new install gets one highlighted item instead of a flood.
	 *
	 * @return int
	 */
	private function get_last_seen() {
		$user_id   = get_current_user_id();
		$last_seen = get_user_meta( $user_id, self::LAST_SEEN_META, true );
		$latest    = $this->get_latest();

		if ( '' === $last_seen && $latest ) {
			$last_seen = $latest - 1;

			update_user_meta( $user_id, self::LAST_SEEN_META, $last_seen );

			return $last_seen;
		}

		return (int) $last_seen;
	}

	/**
	 * Short "New ..." labels shown next to the bell for the newest unread item.
	 *
	 * @return array
	 */
	private function get_pill_labels() {
		return array(
			'news'     => esc_html__( 'Latest news', 'woodmart' ),
			'feature'  => esc_html__( 'New feature', 'woodmart' ),
			'update'   => esc_html__( 'New theme update', 'woodmart' ),
			'offer'    => esc_html__( 'New offer', 'woodmart' ),
			'tip'      => esc_html__( 'New tip', 'woodmart' ),
			'notice'   => esc_html__( 'Important notice', 'woodmart' ),
			'security' => esc_html__( 'Security update', 'woodmart' ),
			'video'    => esc_html__( 'New video', 'woodmart' ),
			'tutorial' => esc_html__( 'New tutorial', 'woodmart' ),
			'article'  => esc_html__( 'New blog post', 'woodmart' ),
			'demo'     => esc_html__( 'New prebuilt website', 'woodmart' ),
			'release'  => esc_html__( 'Release notes', 'woodmart' ),
			'plugin'   => esc_html__( 'Plugin update', 'woodmart' ),
			'event'    => esc_html__( 'New event', 'woodmart' ),
			'survey'   => esc_html__( 'New survey', 'woodmart' ),
		);
	}

	/**
	 * Get items newer than the current user's last seen timestamp, newest first.
	 *
	 * @return array
	 */
	private function get_unread_items() {
		$last_seen = $this->get_last_seen();

		return array_values(
			array_filter(
				$this->get_items(),
				function ( $item ) use ( $last_seen ) {
					return $item['date'] > $last_seen;
				}
			)
		);
	}

	/**
	 * AJAX: mark all cached announcements as seen by the current user.
	 *
	 * @return void
	 */
	public function mark_seen() {
		check_ajax_referer( self::AJAX_ACTION, 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_user_meta( get_current_user_id(), self::LAST_SEEN_META, $this->get_latest() );

		wp_send_json_success();
	}

	/**
	 * Render the header button.
	 *
	 * @return void
	 */
	public function render_button() {
		if ( ! $this->is_visible() ) {
			return;
		}

		$this->rendered = true;
		$unread_items   = $this->get_unread_items();
		$unread         = count( $unread_items );
		$pill_labels    = $this->get_pill_labels();
		$newest         = $unread ? $unread_items[0] : null;

		wp_enqueue_script( 'wd-announcements', WOODMART_ASSETS . '/js/announcements.js', array(), WOODMART_VERSION, true );
		wp_localize_script(
			'wd-announcements',
			'woodmartAnnouncements',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'security' => wp_create_nonce( self::AJAX_ACTION ),
			)
		);

		?>
		<div class="xts-announcements-wrap">
			<button type="button" class="xts-announcements-btn<?php echo $newest ? ' xts-has-pill' : ''; ?>" aria-expanded="false" aria-controls="xts-announcements" aria-haspopup="dialog" data-unread="<?php echo esc_attr( $unread ); ?>"<?php echo $newest ? ' title="' . esc_attr( $newest['title'] ) . '"' : ''; ?>>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
					<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
				</svg>
				<span class="screen-reader-text"><?php esc_html_e( 'News & updates', 'woodmart' ); ?></span>
				<?php if ( $newest ) : ?>
					<span class="xts-announcements-pill"><?php echo esc_html( $pill_labels[ $newest['kind'] ] ); ?></span>
				<?php endif; ?>
				<?php if ( $unread ) : ?>
					<span class="xts-announcements-count">
						<?php echo esc_html( $unread ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'unread', 'woodmart' ); ?></span>
					</span>
				<?php endif; ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the off-canvas panel in the admin footer when the button was rendered.
	 *
	 * @return void
	 */
	public function render_panel() {
		if ( ! $this->rendered ) {
			return;
		}

		$items = $this->get_items();
		$kinds = $this->get_kinds();

		?>
		<div class="xts-announcements-overlay" hidden></div>
		<div id="xts-announcements" class="xts-announcements xts-theme-style" role="dialog" aria-modal="true" aria-labelledby="xts-announcements-title" tabindex="-1" hidden>
			<div class="xts-announcements-head">
				<h2 id="xts-announcements-title"><?php esc_html_e( 'News & updates', 'woodmart' ); ?></h2>
				<button type="button" class="xts-announcements-close xts-i-close">
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'woodmart' ); ?></span>
				</button>
			</div>
			<div class="xts-announcements-list">
				<?php if ( ! $items ) : ?>
					<p class="xts-announcements-empty"><?php esc_html_e( 'No news yet', 'woodmart' ); ?></p>
				<?php endif; ?>

				<?php foreach ( $items as $item ) : ?>
					<article class="xts-announcement xts-kind-<?php echo esc_attr( $item['kind'] ); ?>">
						<div class="xts-announcement-meta">
							<span class="xts-announcement-kind"><?php echo esc_html( $kinds[ $item['kind'] ] ); ?></span>
							<time datetime="<?php echo esc_attr( gmdate( 'c', $item['date'] ) ); ?>" title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), $item['date'] ) ); ?>">
								<?php
								// translators: %s human-readable time difference, e.g. "2 days".
								echo esc_html( sprintf( __( '%s ago', 'woodmart' ), human_time_diff( $item['date'] ) ) );
								?>
							</time>
						</div>

						<h3 class="xts-announcement-title"><?php echo esc_html( $item['title'] ); ?></h3>

						<?php if ( $item['image'] ) : ?>
							<img class="xts-announcement-image" src="<?php echo esc_url( $item['image']['url'], array( 'https' ) ); ?>" alt="<?php echo esc_attr( $item['image']['alt'] ); ?>" <?php echo $item['image']['width'] ? 'width="' . esc_attr( $item['image']['width'] ) . '"' : ''; ?> <?php echo $item['image']['height'] ? 'height="' . esc_attr( $item['image']['height'] ) . '"' : ''; ?> loading="lazy" decoding="async">
						<?php endif; ?>

						<?php if ( $item['content'] ) : ?>
							<div class="xts-announcement-content"><?php echo $this->kses( $item['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses() allowlist. ?></div>
						<?php elseif ( $item['summary'] ) : ?>
							<p class="xts-announcement-content"><?php echo esc_html( $item['summary'] ); ?></p>
						<?php endif; ?>

						<?php if ( $item['link'] ) : ?>
							<a class="xts-btn xts-color-primary xts-announcement-link" href="<?php echo esc_url( $item['link']['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $item['link']['label'] ? $item['link']['label'] : __( 'Learn more', 'woodmart' ) ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Register the WordPress dashboard widget and move it to the top of the main column.
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {
		global $wp_meta_boxes;

		if ( ! $this->is_visible() ) {
			return;
		}

		wp_add_dashboard_widget(
			'woodmart_announcements_widget',
			esc_html__( 'WoodMart Overview', 'woodmart' ),
			array( $this, 'render_dashboard_widget' )
		);

		$core = isset( $wp_meta_boxes['dashboard']['normal']['core'] ) ? $wp_meta_boxes['dashboard']['normal']['core'] : array();

		if ( isset( $core['woodmart_announcements_widget'] ) ) {
			$wp_meta_boxes['dashboard']['normal']['core'] = array( 'woodmart_announcements_widget' => $core['woodmart_announcements_widget'] ) + $core; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Render the WordPress dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$items     = array_slice( $this->get_items(), 0, 5 );
		$last_seen = $this->get_last_seen();

		?>
		<div class="xts-announcements-widget">
			<div class="xts-announcements-widget-head">
				<img src="<?php echo esc_url( WOODMART_ASSETS_IMAGES . '/wood-logo-dark.svg' ); ?>" alt="<?php esc_attr_e( 'Logo', 'woodmart' ); ?>">
				<span class="xts-announcements-widget-version"><?php echo esc_html( 'v' . woodmart_get_theme_info( 'Version' ) ); ?></span>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=xts_dashboard' ) ); ?>"><?php esc_html_e( 'Theme dashboard', 'woodmart' ); ?></a>
			</div>

			<h3 class="xts-announcements-widget-title"><?php esc_html_e( 'News & updates', 'woodmart' ); ?></h3>

			<?php if ( ! $items ) : ?>
				<p class="xts-announcements-empty"><?php esc_html_e( 'No news yet', 'woodmart' ); ?></p>
			<?php endif; ?>

			<ul class="xts-announcements-widget-list">
				<?php foreach ( $items as $item ) : ?>
					<li>
						<?php if ( $item['date'] > $last_seen ) : ?>
							<span class="xts-announcements-widget-new"><?php esc_html_e( 'New', 'woodmart' ); ?></span>
						<?php endif; ?>

						<?php if ( $item['link'] ) : ?>
							<a class="xts-announcements-widget-link" href="<?php echo esc_url( $item['link']['url'], array( 'https' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['title'] ); ?></a>
						<?php else : ?>
							<strong class="xts-announcements-widget-link"><?php echo esc_html( $item['title'] ); ?></strong>
						<?php endif; ?>

						<?php if ( $item['summary'] ) : ?>
							<p><?php echo esc_html( $item['summary'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="xts-announcements-widget-footer">
				<a href="https://go.xtemos.com/documentation?utm_source=woodmart_dashboard_widget&utm_medium=referral&utm_campaign=need_assistance&utm_content=documentation" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'woodmart' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<a href="https://go.xtemos.com/videos?utm_source=woodmart_dashboard_widget&utm_medium=referral&utm_campaign=need_assistance&utm_content=videos" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Video tutorials', 'woodmart' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<a href="https://go.xtemos.com/support?utm_source=woodmart_dashboard_widget&utm_medium=referral&utm_campaign=need_assistance&utm_content=support" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'woodmart' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
			</div>
		</div>
		<?php
	}
}

Announcements::get_instance();
