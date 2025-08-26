<?php
/**
 * Plugin Name: Bynder
 * Description: Allows users to easily import and use their Bynder or WebDAM images and videos directly in WordPress, helping brands save time and maintain consistency. Requires at least WordPress 5.9, tested up to 6.6.1.
 * Author: Bynder BV
 * Author URI: https://www.bynder.com/
 * Version: 5.5.4
 *
 * @package bynder-wordpress
 * @author Bynder
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bynder_authorize( $settings ) {
	$url = 'https://' . $settings['domain'] . '/v6/authentication/oauth2/token';
	$requestBody = [
		'client_id' => $settings['client_id'],
		'client_secret' => $settings['client_secret'],
		'grant_type' => 'client_credentials',
		'scope' => 'asset.usage:read asset.usage:write'
	];
	$data = wp_remote_post( $url, array(
		'body' => $requestBody
	));

	if(!is_array($data) || empty($data['body']) || $data['response']['code'] != 200) {
		echo '<p>Could not authorize, please verify domain and OAuth credentials are configured correctly.</p>';
		return;
	}
	$responseBody = json_decode($data['body'], true);
	if(empty($responseBody['access_token'])) {
		echo '<p>Access token could not be retrieved, please verify domain and OAuth credentials are configured correctly.</p>';
		return;
	}
	return $responseBody['access_token'];
}

function array_get($array, $key, $default = null) {
	return isset($array[$key]) ? $array[$key]: $default;
}

/**
 * Block Initializer.
 */
require_once plugin_dir_path( __FILE__ ) . 'src/init.php';

/**
 * Settings
 */

add_action( 'admin_menu', 'bynder_add_admin_menu' );
add_action( 'admin_init', 'bynder_settings_init' );

function bynder_add_admin_menu() {
	add_options_page(
		'bynder',
		'Bynder',
		'manage_options',
		'bynder',
		'bynder_options_page'
	);
}

function bynder_settings_init() {
	register_setting(
		'bynder',
		'bynder_settings',
		array( 'sanitize_callback' => 'bynder_sanitize_settings' )
	);

	add_settings_section(
		'bynder_settings_general',
		'General',
		null,
		'bynder'
	);

	add_settings_section(
		'bynder_settings_derivatives',
		'Derivatives',
		'bynder_settings_derivatives_section_callback',
		'bynder'
	);

	add_settings_field(
		'bynder_domain',
		'Portal domain',
		field ( 'domain', 'Used to synchronize asset usage and set the domain in Compact View. E.g. myportal.getbynder.com / myportal.webdamdb.com' ),
		'bynder',
		'bynder_settings_general'
	);

	add_settings_field(
		'bynder_client_id',
		'Client ID',
		field(
			'client_id',
			'<b>For Bynder portals only:</b> Used to fetch derivatives and sync usage. Read more about registering OAuth apps ' .
			'<a target="blank" href="https://support.bynder.com/hc/en-us/articles/360013875180-Create-your-OAuth-Apps">here</a>'
		),
		'bynder',
		'bynder_settings_general'
	);

	add_settings_field(
		'bynder_client_secret',
		'Client Secret',
		'bynder_client_secret_field_render',
		'bynder',
		'bynder_settings_general'
	);

	add_settings_field(
		'bynder_default_search_term',
		'Default search term',
		field( 'default_search_term', 'When set, Compact View will automatically search for the entered value' ),
		'bynder',
		'bynder_settings_general'
	);

	add_settings_field(
		'bynder_selection_mode',
		'Asset selection mode',
		'bynder_selection_mode_field_render',
		'bynder',
		'bynder_settings_general'
	);
	add_settings_field(
		'bynder_image_derivative',
		'Image derivative',
		'bynder_image_derivative_field_render',
		'bynder',
		'bynder_settings_derivatives'
	);
}

function bynder_sanitize_settings($values) {
	$existing_values = get_option( 'bynder_settings' );
	if(!$existing_values) {  // Passing default to get_option does not seem to work.
		$existing_values = array();
	}
	if($values['domain'] != '') {
		$urlParts = parse_url($values['domain']);
		$prependScheme = isset($urlParts['scheme']) ? "" : 'https://';
		$domain = $prependScheme.$values['domain'];
		if (esc_url_raw($domain) !== $domain) {
			$values['domain'] = $existing_values['domain'];
			add_settings_error(
				'bynder_settings',
				'_',
				'Invalid domain, please only enter the domain name',
				'error'
			);
		}
	}

	return array_merge($existing_values, $values);
}

function bynder_settings_derivatives_section_callback() {
	$settings = get_option( 'bynder_settings' );
    echo '<p>Defines which public derivative will be used after an asset is selected from Compact View. When not configured or available for the selected asset, the webImage will be used as fallback.</p>';

	if (!empty($settings) && $settings['domain'] != "" && ((!empty($settings['client_id']) && !empty($settings['client_secret'])) || !isBynder($settings))) {
		echo '<a class="button button-secondary" href="?page=bynder&action=fetchDerivatives">Fetch derivatives</a>';
	} else {
		echo '<a class="button button-disabled">Fetch derivatives</a>';
		echo '<em> You must configure portal domain and client credentials to fetch derivatives (client credentials required for Bynder portals only).</em>';
	}
}

function field( $name, $description ) {
	return function () use ( $name, $description ) {
		$settings = get_option( 'bynder_settings' );
		$value = isset($settings[$name]) ? $settings[$name] : "";
		echo '<input type="text" class="regular-text" name="bynder_settings[' . $name . ']" value="' . $value . '" />';
		echo '<p class="description">' . $description . '</p>';
	};
}

function bynder_image_derivative_field_render() {
	$settings = get_option( 'bynder_settings' );

	if(!isset($settings['available_derivatives'])) {
		echo '<p class="description"><em>Please fetch derivatives first</em></p>';
		return;
	}

	$availableDerivatives = array_merge([''], $settings['available_derivatives']);
	echo '<select name="bynder_settings[image_derivative]">';
	foreach($availableDerivatives as $derivative) {
		$selected = $derivative == $settings['image_derivative'] ? 'selected' : '';
		echo '<option ' . $selected . '>' . $derivative . '</option>';
	}
	echo '</select>';
	echo '<p class="description"><em>Default derivative will be applied to the Bynder Asset block in the SingleSelect select mode and the Bynder Gallery Block.</em></p>';
}

function bynder_client_secret_field_render() {
	$settings = get_option( 'bynder_settings' );

	if(!empty($settings['client_secret'])) {
		echo '<div id="client-secret-container"></div>';
		echo '<p class="description">';
		echo '<button class="button button-secondary" id="edit-client-secret">Replace Client Secret</button>';
		echo '<button class="button button-secondary" id="cancel-client-secret" style="display:none">Cancel</button>';
		echo '</p>';
		return;
	}

	echo '<input type="text" class="regular-text" name="bynder_settings[client_secret]">';
}

function bynder_selection_mode_field_render() {
	$settings = get_option( 'bynder_settings' );
	$availableSelectionModes = [
		"SingleSelect",
		"SingleSelectFile"
	];
	echo '<select name="bynder_settings[selection_mode]">';
	foreach($availableSelectionModes as $mode) {
		$selected = ($mode == $settings['selection_mode'])
			|| (empty($settings['selection_mode']) && $mode == "SingleSelect") ? 'selected' : '';

		echo '<option ' . $selected . '>' . $mode . '</option>';
	}
	echo '</select>';
	echo '<p class="description"><b>Only affects the Bynder Asset Gutenberg block.</b></p>';
	echo '<p class="description">SingleSelect: Compact View will use the selected derivative below. </p>';
	echo '<p class="description">SingleSelectFile: Enables derivative selection and DAT UI in Compact View.</p>';
}

function bynder_fetch_derivatives() {
	$settings = get_option( 'bynder_settings' );

	if(isBynder($settings)) {
		if ($settings['domain'] == '' || $settings['client_id'] == '' || $settings['client_secret'] == '') {
			echo '<p>Domain or OAuth token not configured!</p>';
			return;
		}
		$bynderAPIToken = "";
		if(wp_cache_get('bynder_api_token') === false) {
			$bynderAPIToken = bynder_authorize($settings);
			wp_cache_set('bynder_api_token', $bynderAPIToken);
		} else {
			$bynderAPIToken = wp_cache_get('bynder_api_token');
		}
		$url = 'https://' . $settings['domain'] . '/api/v4/account/derivatives';
		$data = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bynderAPIToken,
				'Content-Type' => 'application/json; charset=utf-8',
			)
		));

		if(!is_array($data) || $data['response']['code'] != 200) {
			echo '<p>Could not fetch derivatives, please verify domain and OAuth credentials are configured correctly.</p>';
			return;
		}
		$derivatives = array_filter(json_decode($data['body']), function($derivative) {
			return $derivative->isPublic && !$derivative->isOnTheFly;
		});

		$settings['available_derivatives'] = array_map(function($derivative) {
			return $derivative->prefix;
		}, $derivatives);
	} else {
		$settings['available_derivatives'] = [
			"webImage", "thumbnail", "mini", "original", "transformBaseURL"
		];
	}

	asort($settings['available_derivatives']);

	update_option('bynder_settings', $settings);

	echo '<p>The following custom derivatives were retrieved:</p><ul class="ul-disc">';
	foreach ($settings['available_derivatives'] as $derivative) {
		echo '<li>' . $derivative . '</li>';
	}
	echo '</ul>';
}

function bynder_options_page() {
	wp_cache_delete('bynder_api_token');
	if(array_get($_GET, 'action') == 'fetchDerivatives') {
		bynder_fetch_derivatives();
		echo '<a class="button button-primary" href="?page=bynder">Go back</a>';
		return;
	}
	echo '<h1>Bynder Settings</h1>';
	echo '<form action="options.php" method="post">';
	settings_fields( 'bynder' );
	do_settings_sections( 'bynder' );
	submit_button();
	echo '</form>';
}

/**
 * Asset tracking
 */

register_activation_hook(__FILE__, 'bynder_schedule_cron');
register_deactivation_hook(__FILE__, 'bynder_clear_scheduled_cron');
add_action('bynder_sync_usage_cron', 'bynder_sync_usage');

function bynder_schedule_cron() {
	if ( !wp_next_scheduled( 'bynder_sync_usage_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'bynder_sync_usage_cron' );
	}
}

function bynder_clear_scheduled_cron() {
	wp_clear_scheduled_hook('bynder_sync_usage_cron');
}

function bynder_submit_usage( $settings, $data ) {
	$url = 'https://' . $settings['domain'] . '/api/media/usage/sync';
	if(wp_cache_get('bynder_api_token') === false) {
		$bynderAPIToken = bynder_authorize($settings);
		wp_cache_set('bynder_api_token', $bynderAPIToken);
	} else {
		$bynderAPIToken = wp_cache_get('bynder_api_token');
	}
	wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $bynderAPIToken,
			'Content-Type' => 'application/json; charset=utf-8',
		),
		'body' => json_encode( $data ),
		'method' => 'POST',
		'data_format' => 'body'
	));
}

function bynder_sync_usage() {
	$settings = get_option( 'bynder_settings' );
	if ($settings['domain'] == '' || $settings['client_id'] == '' || $settings['client_secret'] == '') {
		return;
	}

	$query = new WP_Query( [
		'post_type' => array( 'post', 'page' ),
		'post_status' => array( 'any', 'trash')
	] );

	$syncData = array(
		'integration_id' => 'b242c16d-70f4-4101-8df5-87b35bbe56f0',
		'uris' => array(),
		'usages' => array()
	);

	foreach ( $query->posts as $post ) {
		array_push( $syncData['uris'], $post->guid );

		preg_match_all( '/data-bynder-id="(.*?)"/', $post->post_content, $matches );
		if ( empty( $matches[1] ) ) {
			continue;
		}

		foreach ( $matches[1] as $assetId ) {
			array_push( $syncData['usages'], array(
				'asset_id' => $assetId,
				'uri' => $post->guid,
				'additional' => $post->post_title
			) );
		}
	}

	bynder_submit_usage( $settings, $syncData );
}

function isBynder($settings) {
	$url = 'https://' . $settings['domain'] . '/feeds/media/is-bynder-portal/';
	$domainData = wp_remote_get( $url, array(
		'headers' => array(
			'Content-Type' => 'application/json; charset=utf-8',
		)
	));

	$platformCheck = json_decode($domainData['body']);
	if($domainData['response']['code'] != 200) {
		echo '<p>Could not verify the domain, please confirm the domain is entered correctly.</p>';
		return;
	}

	return !isset($platformCheck->webdam);
}


/**
 * Adds an endpoint to the wpadmin endpoint
 */
add_action( 'wp_ajax_bynder_featured', 'set_bynder_featured_image' );

if (!function_exists('download_url')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

function set_bynder_featured_image() {
	// Nonce verification
	if (!wp_verify_nonce($_POST['bynder-nonce'], 'bynder-nonce')) {
		wp_send_json_error(array(
			'error_code' => 403,
			'error' => "Unauthorized request."
		));
		return;
	}
	$url = $_POST['url'];
	$post_id = $_POST['id'];


	if(!current_user_can('upload_files')) {
		wp_send_json_error(array(
			'error_code' => 401,
			'error' => "Unauthorized request to upload files to Wordpress."
		));
		return;
	}
	// For asset URLs without file extensions, need to determine file extension from mime-type
	if(empty(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
		$image_tmp = download_url($url);
		if(is_wp_error($image_tmp)){
			sendJsonError($image_tmp);
		} else {
			// Get image data
			$image_mime = mime_content_type($image_tmp);
			$image_ext = mime2ext($image_mime);
			$image_size = filesize($image_tmp);
			$image_name = basename($image_tmp) . ".". $image_ext; // .jpg optional

			$file = array(
				'name' => $image_name,
				'type' => $image_mime,
				'tmp_name' => $image_tmp,
				'error' => 0,
				'size' => $image_size
			);

			$att_id = media_handle_sideload( $file, $post_id, $desc);

			if(!current_user_can('edit_post', $post_id)
				&& !current_user_can('edit_post_meta', $post_id, '_thumbnail_id')) {
				@unlink($image_tmp);
				sendUnauthorizedFeaturedImageJsonError();
				return;
			}
			set_post_thumbnail($post_id, $att_id);

			if(is_wp_error($att_id)){
				sendJsonError($att_id);
				return;
			}
			@unlink($image_tmp);
			wp_send_json_success(array('att_id' => $att_id, 'url' => wp_get_attachment_url($att_id)));
		}
		return;
	}

	$img = media_sideload_image($url, $post_id);
	if(is_wp_error($img)){
		sendJsonError($img);
		return;
	}
	$img = explode("'",$img)[1];
	$att_id = attachment_url_to_postid($img);

	if(!current_user_can('edit_post', $post_id)
		&& !current_user_can('edit_post_meta', $post_id, '_thumbnail_id')) {
		@unlink($image_tmp);
		sendUnauthorizedFeaturedImageJsonError();
		return;
	}
	set_post_thumbnail($post_id, $att_id);
    wp_send_json_success(array('att_id' => $att_id, 'url' => wp_get_attachment_url($att_id)));
}

function sendJsonError($error) {
	wp_send_json_error(array(
		'error_code' => $error->get_error_code(),
		'error' => $error->get_error_message()
	));
	return;
}

function sendUnauthorizedFeaturedImageJsonError() {
	wp_send_json_error(array(
		'error_code' => 401,
		'error' => "Image successfully imported from Bynder but could not be set as the featured image due to insufficient user permissions."
	));
	return;
}
/**
 * Image mime type to file extension mapping
 * Source: https://gist.github.com/alexcorvi/df8faecb59e86bee93411f6a7967df2c
 */
function mime2ext($mime) {
	$mime_map = [
		'image/bmp'                                                                 => 'bmp',
		'image/x-bmp'                                                               => 'bmp',
		'image/x-bitmap'                                                            => 'bmp',
		'image/x-xbitmap'                                                           => 'bmp',
		'image/x-win-bitmap'                                                        => 'bmp',
		'image/x-windows-bmp'                                                       => 'bmp',
		'image/ms-bmp'                                                              => 'bmp',
		'image/x-ms-bmp'                                                            => 'bmp',
		'image/cdr'                                                                 => 'cdr',
		'image/x-cdr'                                                               => 'cdr',
		'image/gif'                                                                 => 'gif',
		'image/x-icon'                                                              => 'ico',
		'image/x-ico'                                                               => 'ico',
		'image/vnd.microsoft.icon'                                                  => 'ico',
		'image/jp2'                                                                 => 'jp2',
		'image/jpx'                                                                 => 'jp2',
		'image/jpm'                                                                 => 'jp2',
		'image/jpeg'                                                                => 'jpeg',
		'image/pjpeg'                                                               => 'jpeg',
		'image/png'                                                                 => 'png',
		'image/x-png'                                                               => 'png',
		'image/vnd.adobe.photoshop'                                                 => 'psd',
		'image/svg+xml'                                                             => 'svg',
		'image/tiff'                                                                => 'tiff',
	];

	return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
}
