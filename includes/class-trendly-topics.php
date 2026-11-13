<?php
/**
 * RSS-based topic discovery.
 * @package TrendlyAIPost
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Fetches topic ideas from public Google News RSS search feeds. */
class Trendly_AI_Topics {
	/**
	 * Search news feeds for a phrase.
	 * @param string $query Search phrase.
	 * @return array|WP_Error
	 */
	public function discover( $query ) {
		$query = sanitize_text_field( $query );
		if ( '' === $query ) {
			return new WP_Error( 'trendly_empty_query', __( 'Enter a topic or industry to discover ideas.', 'trendly-ai-post' ) );
		}
		$cache_key = 'trendly_topics_' . md5( strtolower( $query ) );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) { return $cached; }
		if ( ! function_exists( 'fetch_feed' ) ) { require_once ABSPATH . WPINC . '/feed.php'; }
		$feed_url = add_query_arg( array( 'q' => $query, 'hl' => 'en-IN', 'gl' => 'IN', 'ceid' => 'IN:en' ), 'https://news.google.com/rss/search' );
		$feed = fetch_feed( $feed_url );
		if ( is_wp_error( $feed ) ) {
			return new WP_Error( 'trendly_feed_error', __( 'Could not retrieve the news feed. Please try again later.', 'trendly-ai-post' ) );
		}
		$items = $feed->get_items( 0, 12 );
		$topics = array();
		foreach ( $items as $item ) {
			$title = sanitize_text_field( wp_strip_all_tags( $item->get_title() ) );
			$link = esc_url_raw( $item->get_permalink() );
			if ( '' === $title || '' === $link ) { continue; }
			$source = $item->get_source();
			$topics[] = array(
				'title' => $title,
				'url' => $link,
				'date' => sanitize_text_field( (string) $item->get_date( 'Y-m-d H:i' ) ),
				'source' => $source ? sanitize_text_field( wp_strip_all_tags( $source->get_title() ) ) : '',
			);
		}
		set_transient( $cache_key, $topics, 30 * MINUTE_IN_SECONDS );
		return $topics;
	}
}
