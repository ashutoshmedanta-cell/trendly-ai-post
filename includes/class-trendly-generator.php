<?php
/**
 * AI content generation service.
 * @package TrendlyAIPost
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Creates reviewable WordPress posts with optional scheduling and imagery. */
class Trendly_AI_Generator {
	private $images;
	public function __construct( $images ) { $this->images = $images; }

	/**
	 * Generate an article and save it as a draft or user-scheduled post.
	 * @param array $args Generation arguments.
	 * @return int|WP_Error
	 */
	public function create_post( $args ) {
		$settings = get_option( 'trendly_ai_post_settings', array() );
		$api_key = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$endpoint = isset( $settings['endpoint'] ) ? esc_url_raw( $settings['endpoint'] ) : 'https://api.openai.com/v1/chat/completions';
		$model = isset( $settings['model'] ) ? sanitize_text_field( $settings['model'] ) : 'gpt-4o-mini';
		if ( '' === $api_key ) { return new WP_Error( 'trendly_missing_key', __( 'Add your AI provider API key in Settings first.', 'trendly-ai-post' ) ); }
		$topic = isset( $args['topic'] ) ? sanitize_text_field( $args['topic'] ) : '';
		if ( '' === $topic ) { return new WP_Error( 'trendly_empty_topic', __( 'Enter an article topic before generating.', 'trendly-ai-post' ) ); }
		$tone = isset( $args['tone'] ) ? sanitize_text_field( $args['tone'] ) : 'informative';
		$length = isset( $args['length'] ) ? absint( $args['length'] ) : 900;
		$length = min( 1800, max( 400, $length ) );
		$keyword = ! empty( $args['keyword'] ) ? sanitize_text_field( $args['keyword'] ) : $topic;
		$prompt = sprintf(
			'Create an original WordPress article about: %1$s. Primary SEO keyword: %2$s. Tone: %3$s. Target length: approximately %4$d words. Return valid JSON only with keys title, content, excerpt, seo_title, meta_description. content must be safe, semantic HTML using headings, paragraphs and lists. Make the article useful and specific. Never invent statistics, quotes, studies, URLs, or sources. If claims need verification, flag them in the prose. Do not include markdown code fences.',
			$topic, $keyword, $tone, $length
		);
		$response = wp_safe_remote_post( $endpoint, array(
			'timeout' => 90,
			'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array(
				'model' => $model,
				'temperature' => 0.65,
				'messages' => array(
					array( 'role' => 'system', 'content' => 'You are an experienced editorial assistant and SEO editor. Return strictly valid JSON. Do not fabricate evidence or sources.' ),
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );
		if ( is_wp_error( $response ) ) { return $response; }
		$status = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $body['error']['message'] ) ? sanitize_text_field( $body['error']['message'] ) : __( 'The AI provider returned an error.', 'trendly-ai-post' );
			return new WP_Error( 'trendly_provider_error', $message );
		}
		$raw = isset( $body['choices'][0]['message']['content'] ) ? trim( $body['choices'][0]['message']['content'] ) : '';
		$raw = preg_replace( '/^\x60\x60\x60(?:json)?\s*|\s*\x60\x60\x60$/', '', $raw );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['title'] ) || empty( $data['content'] ) ) {
			return new WP_Error( 'trendly_invalid_response', __( 'The AI response was not valid JSON with a title and article body. Try again.', 'trendly-ai-post' ) );
		}
		$post_status = 'draft';
		$post_date = current_time( 'mysql' );
		if ( ! empty( $args['schedule_at'] ) ) {
			$scheduled = DateTime::createFromFormat( 'Y-m-d\\TH:i', sanitize_text_field( $args['schedule_at'] ), wp_timezone() );
			if ( ! $scheduled || $scheduled->getTimestamp() <= current_datetime()->getTimestamp() ) {
				return new WP_Error( 'trendly_invalid_schedule', __( 'Choose a future date and time to schedule the article.', 'trendly-ai-post' ) );
			}
			$post_status = 'future';
			$post_date = $scheduled->format( 'Y-m-d H:i:s' );
		}
		$post_id = wp_insert_post( array(
			'post_type' => 'post',
			'post_status' => $post_status,
			'post_title' => sanitize_text_field( $data['title'] ),
			'post_content' => wp_kses_post( $data['content'] ),
			'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '',
			'post_date' => $post_date,
		), true );
		if ( is_wp_error( $post_id ) ) { return $post_id; }
		update_post_meta( $post_id, '_trendly_seo_title', isset( $data['seo_title'] ) ? sanitize_text_field( $data['seo_title'] ) : sanitize_text_field( $data['title'] ) );
		update_post_meta( $post_id, '_trendly_meta_description', isset( $data['meta_description'] ) ? sanitize_textarea_field( $data['meta_description'] ) : ( isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '' ) );
		update_post_meta( $post_id, '_trendly_focus_keyword', $keyword );
		if ( ! empty( $args['add_image'] ) ) {
			$image_result = $this->images->set_featured_image( $post_id, $keyword );
			if ( is_wp_error( $image_result ) ) { update_post_meta( $post_id, '_trendly_image_error', $image_result->get_error_message() ); }
		}
		return $post_id;
	}
}
