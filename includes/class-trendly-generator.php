<?php
/**
 * AI generation service.
 *
 * @package TrendlyAIPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates WordPress drafts from a supplied topic.
 */
class Trendly_AI_Generator {
	/**
	 * Generate a post draft using an OpenAI-compatible chat completions endpoint.
	 *
	 * @param string $topic Topic or working title.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public function create_draft( $topic ) {
		$settings = get_option( 'trendly_ai_post_settings', array() );
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$endpoint = isset( $settings['endpoint'] ) ? esc_url_raw( $settings['endpoint'] ) : 'https://api.openai.com/v1/chat/completions';
		$model    = isset( $settings['model'] ) ? sanitize_text_field( $settings['model'] ) : 'gpt-4o-mini';

		if ( '' === $api_key ) {
			return new WP_Error( 'trendly_missing_key', __( 'Add your API key in Trendly AI Post settings first.', 'trendly-ai-post' ) );
		}

		$topic = sanitize_text_field( $topic );
		if ( '' === $topic ) {
			return new WP_Error( 'trendly_empty_topic', __( 'Enter a topic before generating a draft.', 'trendly-ai-post' ) );
		}

		$prompt = sprintf(
			'Write a useful, original, well-structured WordPress article about: %s. Return valid JSON only with keys "title", "content", and "excerpt". The content should be HTML using appropriate h2, h3, p, and ul tags. Do not invent facts, statistics, quotations, or sources. Clearly qualify uncertainty.',
			$topic
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'temperature' => 0.7,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'You are a careful editorial assistant. Produce accurate, readable drafts and never fabricate citations.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $body['error']['message'] ) ? sanitize_text_field( $body['error']['message'] ) : __( 'The AI provider returned an error.', 'trendly-ai-post' );
			return new WP_Error( 'trendly_provider_error', $message );
		}

		$raw = isset( $body['choices'][0]['message']['content'] ) ? trim( $body['choices'][0]['message']['content'] ) : '';
		$raw = preg_replace( '/^\x60\x60\x60(?:json)?\s*|\s*\x60\x60\x60$/', '', $raw );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['title'] ) || empty( $data['content'] ) ) {
			return new WP_Error( 'trendly_invalid_response', __( 'The AI response was not in the expected JSON format. Please try again.', 'trendly-ai-post' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $data['title'] ),
				'post_content' => wp_kses_post( $data['content'] ),
				'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '',
			),
			true
		);

		return $post_id;
	}
}
