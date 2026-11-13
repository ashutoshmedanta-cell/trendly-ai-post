<?php
/**
 * Plugin Name: Trendly AI Post
 * Description: Generate WordPress draft posts from your ideas using an OpenAI-compatible API.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Trendly AI Post Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trendly-ai-post
 *
 * @package TrendlyAIPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRENDLY_AI_POST_VERSION', '0.1.0' );
define( 'TRENDLY_AI_POST_FILE', __FILE__ );
define( 'TRENDLY_AI_POST_DIR', plugin_dir_path( __FILE__ ) );

require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-admin.php';
require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-generator.php';

add_action(
	'plugins_loaded',
	static function () {
		$generator = new Trendly_AI_Generator();
		$admin     = new Trendly_AI_Admin( $generator );
		$admin->register();
	}
);
