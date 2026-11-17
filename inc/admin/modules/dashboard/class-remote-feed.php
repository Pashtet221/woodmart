<?php
/**
 * Cached read-only JSON feed from woodmarttheme.com.
 *
 * Fetches only from WP-Cron, keeps the last good copy with its ETag in a site transient
 * and never surfaces errors.
 *
 * @package woodmart
 */

namespace XTS\Admin\Modules\Dashboard;

if ( ! defined( 'WOODMART_THEME_DIR' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 * Remote feed class.
 */
class Remote_Feed {
	/**
	 * Request timeout in seconds.
	 */
	const TIMEOUT = 3;

	/**
	 * Neutral user agent that carries no site data.
	 */
	const USER_AGENT = 'WoodMart';

	/**
	 * Retry interval after a failed request.
	 */
	const RETRY_TTL = HOUR_IN_SECONDS;

	/**
	 * How long the last good copy is kept when the API stays down.
	 */
	const STORAGE_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * Feed URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Site transient key; the cron hook is this key with a '_fetch' suffix.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Refresh interval after a successful request, in seconds.
	 *
	 * @var int
	 */
	private $ttl;

	/**
	 * Callback that validates the decoded body and returns sanitized data or null.
	 *
	 * @var callable
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param string   $url       Feed URL.
	 * @param string   $key       Site transient key; the cron hook adds a '_fetch' suffix.
	 * @param int      $ttl       Refresh interval after a successful request, in seconds.
	 * @param callable $validator Returns sanitized data for a decoded body, or null when it is invalid.
	 */
	public function __construct( $url, $key, $ttl, $validator ) {
		$this->url       = $url;
		$this->key       = $key;
		$this->ttl       = $ttl;
		$this->validator = $validator;

		add_action( $this->key . '_fetch', array( $this, 'refresh' ) );
	}

	/**
	 * Get the cached sanitized data.
	 *
	 * @return array|null Null when nothing was fetched yet.
	 */
	public function get_data() {
		return $this->get_cache()['data'];
	}

	/**
	 * Schedule a background refresh when the cache is stale. Never fetches inline.
	 *
	 * @return void
	 */
	public function maybe_schedule_refresh() {
		if ( $this->get_cache()['next_check'] > time() || wp_next_scheduled( $this->key . '_fetch' ) ) {
			return;
		}

		wp_schedule_single_event( time(), $this->key . '_fetch' );
	}

	/**
	 * Fetch the feed and update the cache. Runs in WP-Cron.
	 *
	 * @return void
	 */
	public function refresh() {
		$cache = $this->get_cache();
		$args  = array(
			'timeout'    => self::TIMEOUT,
			// The default WordPress user agent contains the site URL and WordPress version.
			'user-agent' => self::USER_AGENT,
		);

		if ( $cache['etag'] && is_array( $cache['data'] ) ) {
			$args['headers'] = array(
				'If-None-Match' => $cache['etag'],
			);
		}

		$response = wp_remote_get( $this->url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $code && is_array( $cache['data'] ) ) {
			$cache['fetched']    = time();
			$cache['next_check'] = time() + $this->ttl;
			$this->set_cache( $cache );
			return;
		}

		$data = 200 === $code ? call_user_func( $this->validator, json_decode( wp_remote_retrieve_body( $response ), true ) ) : null;

		if ( ! is_array( $data ) ) {
			// Keep the last good copy and retry later.
			$cache['next_check'] = time() + self::RETRY_TTL;
			$this->set_cache( $cache );
			return;
		}

		$this->set_cache(
			array(
				'data'       => $data,
				'etag'       => (string) wp_remote_retrieve_header( $response, 'etag' ),
				'fetched'    => time(),
				'next_check' => time() + $this->ttl,
			)
		);
	}

	/**
	 * Return the URL when it is a valid https:// URL, otherwise an empty string.
	 *
	 * @param mixed $url Raw URL.
	 *
	 * @return string
	 */
	public static function https_url( $url ) {
		if ( ! is_string( $url ) || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) {
			return '';
		}

		return esc_url_raw( $url, array( 'https' ) );
	}

	/**
	 * Get the cache record.
	 *
	 * @return array {
	 *     Cache record.
	 *
	 *     @type array|null $data       Sanitized data, or null when nothing was fetched yet.
	 *     @type string     $etag       ETag of the cached data.
	 *     @type int        $fetched    Timestamp of the last successful request.
	 *     @type int        $next_check Timestamp of the next allowed request.
	 * }
	 */
	private function get_cache() {
		$cache = get_site_transient( $this->key );
		$cache = is_array( $cache ) ? $cache : array();

		return array(
			'data'       => isset( $cache['data'] ) && is_array( $cache['data'] ) ? $cache['data'] : null,
			'etag'       => isset( $cache['etag'] ) ? (string) $cache['etag'] : '',
			'fetched'    => isset( $cache['fetched'] ) ? (int) $cache['fetched'] : 0,
			'next_check' => isset( $cache['next_check'] ) ? (int) $cache['next_check'] : 0,
		);
	}

	/**
	 * Save the cache record.
	 *
	 * @param array $cache Cache record, see get_cache().
	 *
	 * @return void
	 */
	private function set_cache( $cache ) {
		set_site_transient( $this->key, $cache, self::STORAGE_TTL );
	}
}
