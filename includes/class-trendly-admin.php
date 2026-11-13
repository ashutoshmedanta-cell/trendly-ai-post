<?php
/**
 * WordPress admin interface.
 *
 * @package TrendlyAIPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings page and draft-generation action.
 */
class Trendly_AI_Admin {
	/**
	 * @var Trendly_AI_Generator
	 */
	private $generator;

	/**
	 * Constructor.
	 *
	 * @param Trendly_AI_Generator $generator Generator service.
	 */
	public function __construct( $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_trendly_generate_draft', array( $this, 'handle_generate' ) );
	}

	/**
	 * Add the settings page.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Trendly AI Post', 'trendly-ai-post' ),
			__( 'Trendly AI Post', 'trendly-ai-post' ),
			'manage_options',
			'trendly-ai-post',
			array( $this, 'render_page' ),
			'dashicons-edit-page',
			80
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'trendly_ai_post_group',
			'trendly_ai_post_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize submitted settings while retaining an existing key when blank.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$existing = get_option( 'trendly_ai_post_settings', array() );
		$output  = array();

		$output['endpoint'] = isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : 'https://api.openai.com/v1/chat/completions';
		$output['model']    = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini';

		$new_key = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
		$output['api_key'] = '' !== $new_key
			? $new_key
			: ( isset( $existing['api_key'] ) ? $existing['api_key'] : '' );

		return $output;
	}

	/**
	 * Handle a request to create a draft.
	 */
	public function handle_generate() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to create posts.', 'trendly-ai-post' ) );
		}

		check_admin_referer( 'trendly_generate_draft' );

		$topic = isset( $_POST['trendly_topic'] )
			? sanitize_text_field( wp_unslash( $_POST['trendly_topic'] ) )
			: '';

		$result = $this->generator->create_draft( $topic );

		if ( is_wp_error( $result ) ) {
			$url = add_query_arg(
				array(
					'page'          => 'trendly-ai-post',
					'trendly_error' => $result->get_error_message(),
				),
				admin_url( 'admin.php' )
			);
		} else {
			$url = add_query_arg(
				array(
					'page'            => 'trendly-ai-post',
					'trendly_created' => absint( $result ),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'trendly_ai_post_settings', array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Trendly AI Post', 'trendly-ai-post' ); ?></h1>
			<p><?php esc_html_e( 'Create an editable WordPress draft from a topic. You review and publish every draft yourself.', 'trendly-ai-post' ); ?></p>

			<?php if ( isset( $_GET['trendly_created'] ) ) : ?>
				<?php $post_id = absint( $_GET['trendly_created'] ); ?>
				<div class="notice notice-success"><p>
					<?php esc_html_e( 'Draft created.', 'trendly-ai-post' ); ?>
					<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Review draft', 'trendly-ai-post' ); ?></a>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['trendly_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['trendly_error'] ) ) ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Generate a draft', 'trendly-ai-post' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="trendly_generate_draft">
				<?php wp_nonce_field( 'trendly_generate_draft' ); ?>
				<p><label for="trendly_topic"><strong><?php esc_html_e( 'Article topic', 'trendly-ai-post' ); ?></strong></label></p>
				<p><input class="large-text" id="trendly_topic" name="trendly_topic" type="text" required placeholder="<?php esc_attr_e( 'e.g. A beginner guide to accessible WordPress websites', 'trendly-ai-post' ); ?>"></p>
				<?php submit_button( __( 'Generate WordPress draft', 'trendly-ai-post' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'AI provider settings', 'trendly-ai-post' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'trendly_ai_post_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="trendly_endpoint"><?php esc_html_e( 'Chat completions endpoint', 'trendly-ai-post' ); ?></label></th>
						<td>
							<input class="regular-text" id="trendly_endpoint" name="trendly_ai_post_settings[endpoint]" type="url" value="<?php echo esc_attr( isset( $settings['endpoint'] ) ? $settings['endpoint'] : 'https://api.openai.com/v1/chat/completions' ); ?>" required>
							<p class="description"><?php esc_html_e( 'Use an OpenAI-compatible chat completions endpoint.', 'trendly-ai-post' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="trendly_model"><?php esc_html_e( 'Model', 'trendly-ai-post' ); ?></label></th>
						<td><input class="regular-text" id="trendly_model" name="trendly_ai_post_settings[model]" type="text" value="<?php echo esc_attr( isset( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini' ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="trendly_api_key"><?php esc_html_e( 'API key', 'trendly-ai-post' ); ?></label></th>
						<td>
							<input class="regular-text" id="trendly_api_key" name="trendly_ai_post_settings[api_key]" type="password" value="" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key. The key is stored in this WordPress site database; use HTTPS and restrict administrator access.', 'trendly-ai-post' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'trendly-ai-post' ) ); ?>
			</form>
		</div>
		<?php
	}
}
