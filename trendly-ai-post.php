<?php
/**
 * Plugin Name: Trendly AI Post
 * Description: Discover topics and create SEO-aware WordPress drafts with AI assistance.
 * Version: 0.2.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Trendly AI Post Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trendly-ai-post
 *
 * @package TrendlyAIPost
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'TRENDLY_AI_POST_VERSION', '0.2.0' );
define( 'TRENDLY_AI_POST_FILE', __FILE__ );
define( 'TRENDLY_AI_POST_DIR', plugin_dir_path( __FILE__ ) );
require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-topics.php';
require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-images.php';
require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-generator.php';
require_once TRENDLY_AI_POST_DIR . 'includes/class-trendly-admin.php';
add_action( 'plugins_loaded', static function () {
	$topics = new Trendly_AI_Topics();
	$images = new Trendly_AI_Images();
	$generator = new Trendly_AI_Generator( $images );
	$admin = new Trendly_AI_Admin( $generator, $topics );
	$admin->register();
} );
