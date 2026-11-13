<?php
/**
 * Optional Pexels image search and WordPress media import.
 * @package TrendlyAIPost
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Finds and imports a relevant image from Pexels. */
class Trendly_AI_Images {
	/**
	 * Search Pexels and attach the first suitable image to a post.
	 * @param int $post_id Post ID.
	 * @param string $query Image search phrase.
	 * @return int|WP_Error
	 */
	public function set_featured_image( $post_id, $query ) {
		$settings = get_option( 'trendly_ai_post_settings', array() );
		$api_key = isset( $settings['pexels_api_key'] ) ? trim( $settings['pexels_api_key'] ) : '';
		if ( '' === $api_key ) {
			return new WP_Error( 'trendly_missing_pexels_key', __( 'Add a Pexels API key in Settings to use featured image search.', 'trendly-ai-post' ) );
		}
		$response = wp_safe_remote_get(
			add_query_arg( array( 'query' => sanitize_text_field( $query ), 'per_page' => 1, 'orientation' => 'landscape' ), 'https://api.pexels.com/v1/search' ),
			array( 'timeout' => 20, 'headers' => array( 'Authorization' => $api_key ) )
		);
		if ( is_wp_error( $response ) ) { return $response; }
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || empty( $data['photos'][0]['src']['large'] ) ) {
			return new WP_Error( 'trendly_pexels_empty', __( 'No suitable Pexels image was found.', 'trendly-ai-post' ) );
		}
		$photo = $data['photos'][0];
		$image_url = esc_url_raw( $photo['src']['large'] );
		$photographer = isset( $photo['photographer'] ) ? sanitize_text_field( $photo['photographer'] ) : 'Pexels contributor';
		$source_url = isset( $photo['url'] ) ? esc_url_raw( $photo['url'] ) : 'https://www.pexels.com/';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$temp_file = download_url( $image_url, 30 );
		if ( is_wp_error( $temp_file ) ) { return $temp_file; }
		$file_array = array( 'name' => 'trendly-pexels-' . absint( $post_id ) . '.jpg', 'tmp_name' => $temp_file );
		$attachment_id = media_handle_sideload( $file_array, $post_id, sanitize_text_field( $query ) );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}
		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, '_trendly_image_credit', array( 'photographer' => $photographer, 'url' => $source_url ) );
		return $attachment_id;
	}
}
