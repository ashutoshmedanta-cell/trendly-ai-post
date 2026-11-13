<?php
/**
 * WordPress admin interface.
 * @package TrendlyAIPost
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Admin screens and secured form handlers. */
class Trendly_AI_Admin {
	private $generator;
	private $topics;
	public function __construct( $generator, $topics ) { $this->generator = $generator; $this->topics = $topics; }

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_trendly_generate_draft', array( $this, 'handle_generate' ) );
		add_action( 'admin_post_trendly_discover_topics', array( $this, 'handle_discover' ) );
	}
	public function add_menu() {
		add_menu_page( __( 'Trendly AI Post', 'trendly-ai-post' ), __( 'Trendly AI Post', 'trendly-ai-post' ), 'edit_posts', 'trendly-ai-post', array( $this, 'render_dashboard' ), 'dashicons-edit-page', 80 );
		add_submenu_page( 'trendly-ai-post', __( 'Discover Topics', 'trendly-ai-post' ), __( 'Discover Topics', 'trendly-ai-post' ), 'edit_posts', 'trendly-ai-topics', array( $this, 'render_topics' ) );
		add_submenu_page( 'trendly-ai-post', __( 'Generate Article', 'trendly-ai-post' ), __( 'Generate Article', 'trendly-ai-post' ), 'edit_posts', 'trendly-ai-generate', array( $this, 'render_generator' ) );
		add_submenu_page( 'trendly-ai-post', __( 'Editorial Calendar', 'trendly-ai-post' ), __( 'Editorial Calendar', 'trendly-ai-post' ), 'edit_posts', 'trendly-ai-calendar', array( $this, 'render_calendar' ) );
		add_submenu_page( 'trendly-ai-post', __( 'Settings', 'trendly-ai-post' ), __( 'Settings', 'trendly-ai-post' ), 'manage_options', 'trendly-ai-settings', array( $this, 'render_settings' ) );
	}
	public function register_settings() {
		register_setting( 'trendly_ai_post_group', 'trendly_ai_post_settings', array( $this, 'sanitize_settings' ) );
	}
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$existing = get_option( 'trendly_ai_post_settings', array() );
		return array(
			'endpoint' => isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : 'https://api.openai.com/v1/chat/completions',
			'model' => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini',
			'api_key' => ! empty( $input['api_key'] ) ? sanitize_text_field( trim( $input['api_key'] ) ) : ( $existing['api_key'] ?? '' ),
			'pexels_api_key' => ! empty( $input['pexels_api_key'] ) ? sanitize_text_field( trim( $input['pexels_api_key'] ) ) : ( $existing['pexels_api_key'] ?? '' ),
			'topic_queries' => isset( $input['topic_queries'] ) ? sanitize_text_field( $input['topic_queries'] ) : 'WordPress, artificial intelligence, digital marketing',
		);
	}
	public function handle_generate() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to create posts.', 'trendly-ai-post' ) ); }
		check_admin_referer( 'trendly_generate_draft' );
		$args = array(
			'topic' => isset( $_POST['trendly_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['trendly_topic'] ) ) : '',
			'keyword' => isset( $_POST['trendly_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['trendly_keyword'] ) ) : '',
			'tone' => isset( $_POST['trendly_tone'] ) ? sanitize_text_field( wp_unslash( $_POST['trendly_tone'] ) ) : 'informative',
			'length' => isset( $_POST['trendly_length'] ) ? absint( $_POST['trendly_length'] ) : 900,
			'schedule_at' => isset( $_POST['trendly_schedule_at'] ) ? sanitize_text_field( wp_unslash( $_POST['trendly_schedule_at'] ) ) : '',
			'add_image' => isset( $_POST['trendly_add_image'] ) && '1' === $_POST['trendly_add_image'],
		);
		$result = $this->generator->create_post( $args );
		if ( is_wp_error( $result ) ) { $this->redirect_with_notice( 'trendly-ai-generate', 'error', $result->get_error_message() ); }
		$this->redirect_with_notice( 'trendly-ai-generate', 'created', absint( $result ) );
	}
	public function handle_discover() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to discover topics.', 'trendly-ai-post' ) ); }
		check_admin_referer( 'trendly_discover_topics' );
		$query = isset( $_POST['trendly_query'] ) ? sanitize_text_field( wp_unslash( $_POST['trendly_query'] ) ) : '';
		$result = $this->topics->discover( $query );
		if ( is_wp_error( $result ) ) { $this->redirect_with_notice( 'trendly-ai-topics', 'error', $result->get_error_message() ); }
		set_transient( 'trendly_last_topics_' . get_current_user_id(), array( 'query' => $query, 'items' => $result ), 30 * MINUTE_IN_SECONDS );
		$this->redirect_with_notice( 'trendly-ai-topics', 'found', count( $result ) );
	}
	private function redirect_with_notice( $page, $type, $value ) {
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'trendly_' . $type => $value ), admin_url( 'admin.php' ) ) );
		exit;
	}
	private function render_notice() {
		if ( isset( $_GET['trendly_created'] ) ) {
			$id = absint( $_GET['trendly_created'] );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Article created.', 'trendly-ai-post' ) . ' <a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( 'Review article', 'trendly-ai-post' ) . '</a></p></div>';
		}
		if ( isset( $_GET['trendly_found'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d topic ideas retrieved.', 'trendly-ai-post' ), absint( $_GET['trendly_found'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['trendly_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['trendly_error'] ) ) ) . '</p></div>';
		}
	}
	public function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		echo '<div class="wrap"><h1>' . esc_html__( 'Trendly AI Post', 'trendly-ai-post' ) . '</h1>';
		$this->render_notice();
		echo '<p>' . esc_html__( 'Research ideas, prepare SEO-aware articles, and organise your publishing workflow. Generated content remains under editorial review.', 'trendly-ai-post' ) . '</p>';
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Version 0.2.0: topic discovery, AI article generation, SEO metadata, optional Pexels featured images, and an editorial calendar.', 'trendly-ai-post' ) . '</p></div>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=trendly-ai-topics' ) ) . '">' . esc_html__( 'Discover topics', 'trendly-ai-post' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=trendly-ai-generate' ) ) . '">' . esc_html__( 'Generate article', 'trendly-ai-post' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=trendly-ai-calendar' ) ) . '">' . esc_html__( 'View calendar', 'trendly-ai-post' ) . '</a></p>';
		echo '<div class="card" style="max-width:760px;padding:16px"><h2>' . esc_html__( 'Editorial workflow', 'trendly-ai-post' ) . '</h2><ol><li>' . esc_html__( 'Find timely ideas from Google News RSS.', 'trendly-ai-post' ) . '</li><li>' . esc_html__( 'Generate a draft with your chosen tone and keyword.', 'trendly-ai-post' ) . '</li><li>' . esc_html__( 'Review facts, sources, images and SEO fields.', 'trendly-ai-post' ) . '</li><li>' . esc_html__( 'Publish manually or schedule it for a future date.', 'trendly-ai-post' ) . '</li></ol></div>';
		echo '<p class="description">' . esc_html__( 'AI output can be inaccurate. Human review is required before publication.', 'trendly-ai-post' ) . '</p></div>';
	}
	public function render_topics() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		echo '<div class="wrap"><h1>' . esc_html__( 'Discover Topic Ideas', 'trendly-ai-post' ) . '</h1>';
		$this->render_notice();
		echo '<p>' . esc_html__( 'Search Google News RSS for recent coverage. Results link to original publishers; headlines are ideas, not verified facts.', 'trendly-ai-post' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="trendly_discover_topics">';
		wp_nonce_field( 'trendly_discover_topics' );
		echo '<p><label for="trendly_query"><strong>' . esc_html__( 'Topic, niche or industry', 'trendly-ai-post' ) . '</strong></label><br><input class="regular-text" id="trendly_query" name="trendly_query" type="text" required placeholder="' . esc_attr__( 'e.g. WordPress AI publishing', 'trendly-ai-post' ) . '"> ';
		submit_button( __( 'Find topic ideas', 'trendly-ai-post' ), 'primary', 'submit', false );
		echo '</p></form>';
		$last = get_transient( 'trendly_last_topics_' . get_current_user_id() );
		if ( is_array( $last ) && ! empty( $last['items'] ) ) {
			echo '<h2>' . esc_html( sprintf( __( 'Recent results for: %s', 'trendly-ai-post' ), $last['query'] ) ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Headline', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Publisher', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Date', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Action', 'trendly-ai-post' ) . '</th></tr></thead><tbody>';
			foreach ( $last['items'] as $item ) {
				echo '<tr><td><a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $item['title'] ) . '</a></td><td>' . esc_html( $item['source'] ) . '</td><td>' . esc_html( $item['date'] ) . '</td><td><a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'trendly-ai-generate', 'topic' => $item['title'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Use topic', 'trendly-ai-post' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
	public function render_generator() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		echo '<div class="wrap"><h1>' . esc_html__( 'Generate Article', 'trendly-ai-post' ) . '</h1>';
		$this->render_notice();
		echo '<p>' . esc_html__( 'Create an article draft, or optionally schedule it for a future date you choose.', 'trendly-ai-post' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:760px"><input type="hidden" name="action" value="trendly_generate_draft">';
		wp_nonce_field( 'trendly_generate_draft' );
		$topic = isset( $_GET['topic'] ) ? sanitize_text_field( wp_unslash( $_GET['topic'] ) ) : '';
		echo '<table class="form-table"><tr><th><label for="trendly_topic">' . esc_html__( 'Article topic', 'trendly-ai-post' ) . '</label></th><td><input class="large-text" id="trendly_topic" name="trendly_topic" type="text" value="' . esc_attr( $topic ) . '" required></td></tr>';
		echo '<tr><th><label for="trendly_keyword">' . esc_html__( 'Focus keyword', 'trendly-ai-post' ) . '</label></th><td><input class="regular-text" id="trendly_keyword" name="trendly_keyword" type="text" placeholder="' . esc_attr__( 'Optional; defaults to topic', 'trendly-ai-post' ) . '"></td></tr>';
		echo '<tr><th><label for="trendly_tone">' . esc_html__( 'Writing tone', 'trendly-ai-post' ) . '</label></th><td><select id="trendly_tone" name="trendly_tone"><option value="informative">Informative</option><option value="professional">Professional</option><option value="conversational">Conversational</option><option value="persuasive">Persuasive</option><option value="beginner-friendly">Beginner-friendly</option></select></td></tr>';
		echo '<tr><th><label for="trendly_length">' . esc_html__( 'Approximate length', 'trendly-ai-post' ) . '</label></th><td><select id="trendly_length" name="trendly_length"><option value="600">600 words</option><option value="900" selected>900 words</option><option value="1200">1,200 words</option><option value="1600">1,600 words</option></select></td></tr>';
		echo '<tr><th><label for="trendly_schedule_at">' . esc_html__( 'Schedule (optional)', 'trendly-ai-post' ) . '</label></th><td><input id="trendly_schedule_at" name="trendly_schedule_at" type="datetime-local"><p class="description">' . esc_html__( 'Leave empty to save as a draft. A future date schedules the post using your WordPress timezone.', 'trendly-ai-post' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Featured image', 'trendly-ai-post' ) . '</th><td><label><input type="checkbox" name="trendly_add_image" value="1"> ' . esc_html__( 'Search Pexels and attach a relevant image (requires a Pexels API key).', 'trendly-ai-post' ) . '</label></td></tr></table>';
		submit_button( __( 'Generate article', 'trendly-ai-post' ) );
		echo '</form><p class="description">' . esc_html__( 'Generated posts are never published immediately. Check accuracy, sources, image rights and SEO before publishing.', 'trendly-ai-post' ) . '</p></div>';
	}
	public function render_calendar() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => array( 'future', 'draft' ), 'numberposts' => 50, 'orderby' => 'date', 'order' => 'ASC' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Editorial Calendar', 'trendly-ai-post' ) . '</h1><p>' . esc_html__( 'Upcoming scheduled posts and drafts. Edit any item to review or change its publication date.', 'trendly-ai-post' ) . '</p>';
		if ( empty( $posts ) ) {
			echo '<p>' . esc_html__( 'No drafts or scheduled posts found.', 'trendly-ai-post' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Article', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Status', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Date', 'trendly-ai-post' ) . '</th><th>' . esc_html__( 'Action', 'trendly-ai-post' ) . '</th></tr></thead><tbody>';
			foreach ( $posts as $post ) {
				$status = get_post_status_object( $post->post_status );
				echo '<tr><td>' . esc_html( get_the_title( $post ) ) . '</td><td>' . esc_html( $status ? $status->label : $post->post_status ) . '</td><td>' . esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ) ) . '</td><td><a class="button button-small" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Edit / review', 'trendly-ai-post' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = get_option( 'trendly_ai_post_settings', array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'Trendly AI Post Settings', 'trendly-ai-post' ) . '</h1><form method="post" action="options.php">';
		settings_fields( 'trendly_ai_post_group' );
		echo '<table class="form-table"><tr><th><label for="trendly_endpoint">' . esc_html__( 'AI API endpoint', 'trendly-ai-post' ) . '</label></th><td><input class="regular-text" id="trendly_endpoint" name="trendly_ai_post_settings[endpoint]" type="url" value="' . esc_attr( $settings['endpoint'] ?? 'https://api.openai.com/v1/chat/completions' ) . '" required><p class="description">' . esc_html__( 'OpenAI-compatible chat completions endpoint. Use a trusted HTTPS provider.', 'trendly-ai-post' ) . '</p></td></tr>';
		echo '<tr><th><label for="trendly_model">' . esc_html__( 'Model name', 'trendly-ai-post' ) . '</label></th><td><input class="regular-text" id="trendly_model" name="trendly_ai_post_settings[model]" type="text" value="' . esc_attr( $settings['model'] ?? 'gpt-4o-mini' ) . '" required></td></tr>';
		echo '<tr><th><label for="trendly_api_key">' . esc_html__( 'AI API key', 'trendly-ai-post' ) . '</label></th><td><input class="regular-text" id="trendly_api_key" name="trendly_ai_post_settings[api_key]" type="password" value="" autocomplete="new-password"><p class="description">' . esc_html__( 'Leave blank to keep the existing key. Stored in WordPress options; restrict admin access and use HTTPS.', 'trendly-ai-post' ) . '</p></td></tr>';
		echo '<tr><th><label for="trendly_pexels_key">' . esc_html__( 'Pexels API key', 'trendly-ai-post' ) . '</label></th><td><input class="regular-text" id="trendly_pexels_key" name="trendly_ai_post_settings[pexels_api_key]" type="password" value="" autocomplete="new-password"><p class="description">' . esc_html__( 'Optional. Get a key from Pexels to enable featured image search.', 'trendly-ai-post' ) . ' <a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">Pexels API</a></p></td></tr>';
		echo '<tr><th><label for="trendly_queries">' . esc_html__( 'Suggested topic searches', 'trendly-ai-post' ) . '</label></th><td><input class="large-text" id="trendly_queries" name="trendly_ai_post_settings[topic_queries]" type="text" value="' . esc_attr( $settings['topic_queries'] ?? 'WordPress, artificial intelligence, digital marketing' ) . '"></td></tr></table>';
		submit_button( __( 'Save settings', 'trendly-ai-post' ) );
		echo '</form><p class="description">' . esc_html__( 'API usage may incur charges from your chosen provider. Trendly AI Post does not include API credits.', 'trendly-ai-post' ) . '</p></div>';
	}
}
