<?php
/**
 * Blocks Initializer
 *
 * Enqueue CSS/JS of all the blocks.
 *
 * @since   4.0.0
 * @package bynder-wordpress
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Gutenberg block assets for both frontend + backend.
 *
 * Assets enqueued:
 * 1. blocks.style.build.css - Frontend + Backend.
 * 2. blocks.build.js - Backend.
 * 3. blocks.editor.build.css - Backend.
 *
 * @uses {wp-blocks} for block type registration & related functions.
 * @uses {wp-element} for WP Element abstraction — structure of blocks.
 * @uses {wp-i18n} to internationalize the block's text.
 * @uses {wp-editor} for WP editor styles.
 * @since 1.0.0
 */
function bynder_block_cgb_block_assets() { // phpcs:ignore
	// Register block styles for both frontend + backend.
	wp_register_style(
		'bynder_block-cgb-style-css', // Handle.
		plugins_url( '/build/style-index.css', dirname( __FILE__ ) ), // Block style CSS.
		array( 'wp-editor' ), // Dependency to include the CSS after it.
		null // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.style.build.css' ) // Version: File modification time.
	);

	// Register block editor script for backend.
	wp_register_script(
		'bynder_block-cgb-block-js', // Handle.
		plugins_url( '/build/index.js', dirname( __FILE__ ) ), // Block.build.js: We register the block here. Built with Webpack.
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ), // Dependencies, defined above.
		null, // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: filemtime — Gets file modification time.
		true // Enqueue the script in the footer.
	);

	// Register block editor styles for backend.
	wp_register_style(
		'bynder_block-cgb-block-editor-css', // Handle.
		plugins_url( '/build/index.css', dirname( __FILE__ ) ), // Block editor CSS.
		array( 'wp-edit-blocks' ), // Dependency to include the CSS after it.
		null // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: File modification time.
	);

	$settings = get_option('bynder_settings');
	$derivative = array_get($settings, 'image_derivative') == "DAT" ? "transformBaseUrl" : array_get($settings, 'image_derivative');
	// WP Localized globals. Use dynamic PHP stuff in JavaScript via `cgbGlobal` object.
	wp_localize_script(
		'bynder_block-cgb-block-js',
		'cgbGlobal', // Array containing dynamic data for a JS Global.
		array(
			'pluginDirPath' => plugin_dir_path( __DIR__ ),
			'pluginDirUrl'  => plugin_dir_url( __DIR__ ),
			'language' => get_locale(),
			'bynderDomain' => array_get($settings, 'domain'),
			'bynderImageDerivative' => $derivative,
			'bynderDefaultSearchTerm' => array_get($settings, 'default_search_term'),
			'bynderSelectionMode' => !array_get($settings, 'selection_mode') ? "SingleSelect" : array_get($settings, 'selection_mode'),
			'bynderNonce' => wp_create_nonce('bynder-nonce')
			// Add more data here that you want to access from `cgbGlobal` object.
		)
	);

	/**
	 * Register Gutenberg block on server-side.
	 *
	 * Register the block on server-side to ensure that the block
	 * scripts and styles for both frontend and backend are
	 * enqueued when the editor loads.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/blocks/writing-your-first-block-type#enqueuing-block-scripts
	 * @since 1.16.0
	 */
	register_block_type(
		'cgb/block-bynder-block', array(
			// Enqueue blocks.style.build.css on both frontend & backend.
			'style'         => 'bynder_block-cgb-style-css',
			// Enqueue blocks.build.js in the editor only.
			'editor_script' => 'bynder_block-cgb-block-js',
			// Enqueue blocks.editor.build.css in the editor only.
			'editor_style'  => 'bynder_block-cgb-block-editor-css',
		)
	);

	// Register the server-side rendered video embed block.
	// The embed code is stored as a JSON attribute inside the Gutenberg block
	// HTML comment, which wp_kses never parses, so <script> and <iframe srcdoc>
	// content is preserved even when non-admin users save the post.
	register_block_type(
		'bynder/video-embed',
		array(
			'attributes'      => array(
				'embedCode' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_callback' => 'bynder_render_video_embed_block',
		)
	);
}

// Hook: Block assets.
add_action( 'init', 'bynder_block_cgb_block_assets' );

/**
 * Server-side render callback for the bynder/video-embed block.
 *
 * Outputs the Bynder embed code stored as a block attribute. Because the
 * attribute is encoded as JSON inside a Gutenberg HTML block comment it is
 * never touched by wp_kses during post save, preserving <script> and
 * <iframe srcdoc> elements that WordPress would otherwise strip for
 * non-admin users.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function bynder_render_video_embed_block( $attributes ) {
	if ( empty( $attributes['embedCode'] ) ) {
		return '';
	}
	
	return $attributes['embedCode'];
}

function bynder_enqueue_admin_scripts( $hook ) {
    if ( 'settings_page_bynder' != $hook ) {
        return;
    }
    wp_enqueue_script('my-admin-page', plugin_dir_url( __FILE__ ) . 'admin.js', array(), null, true);
}
add_action( 'admin_enqueue_scripts', 'bynder_enqueue_admin_scripts' );
