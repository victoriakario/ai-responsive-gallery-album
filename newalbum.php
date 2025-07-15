<?php
global $wpdb;

// Confirm user capability before proceeding
if ( ! current_user_can( 'upload_files' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

$ai_show_table_album = $wpdb->prefix . "ai_album";

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['Submit'] ) && $_POST['Submit'] !== 'Cancel' ) {
	$ai_check_album_nonce = $_POST['_wpnonce'] ?? '';

	if ( ! wp_verify_nonce( $ai_check_album_nonce, 'add-album' ) ) {
		wp_die( __( 'Security check failed.', 'aigallery' ) );
	}
}


// Cancel button action
if ( isset( $_POST['Submit'] ) && $_POST['Submit'] === 'Cancel' ) {
	wp_redirect( admin_url( 'admin.php?page=ai_gallery' ) );
	exit;
}

// Helper functions
function ai_upload_dir( $upload ) {
	$upload['subdir'] = '/al_gallery_files';
	$upload['path']   = $upload['basedir'] . $upload['subdir'];
	$upload['url']    = $upload['baseurl'] . $upload['subdir'];
	return $upload;
}

function generate_random_string( $name_length = 4 ) {
	$alpha_numeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	return substr( str_shuffle( $alpha_numeric ), 0, $name_length );
}

function sanitize_filename( $filename ) {
	$parts = explode( '.', $filename );
	$base  = trim( preg_replace( "/\W+/", "-", $parts[0] ), "-" );
	return $base . '.' . $parts[1];
}

// Handle Add
if ( isset( $_POST['Submit'], $_POST['Action'] ) && $_POST['Submit'] === 'Save' && $_POST['Action'] === 'Add' ) {
	$album_title   = sanitize_text_field( $_POST['album_title'] );
	$album_visible = ( $_POST['album_visible'] === '1' ) ? '1' : '0';

	// Slug
	$album_slug = preg_replace( "/\W+/", "-", strtolower( $album_title ) );
	$album_slug = trim( $album_slug, "-" );

	// Avoid duplicate titles/slugs
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ai_show_table_album WHERE album_title = %s", $album_title ) ) ) {
		$album_title .= '-' . generate_random_string();
	}
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ai_show_table_album WHERE album_slug = %s", $album_slug ) ) ) {
		$album_slug .= '-' . generate_random_string();
	}

	// Order
	$album_order = (int) $wpdb->get_var( "SELECT MAX(album_order) FROM $ai_show_table_album" ) + 1;

	$filename = '';
	if ( ! empty( $_FILES['album_image']['name'] ) && $_FILES['album_image']['size'] > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$_FILES['album_image']['name'] = sanitize_filename( $_FILES['album_image']['name'] );

		// Avoid overwrite
		$upload_dir = AI_GALLERY_DIR_PATH;
		if ( file_exists( $upload_dir . '/' . $_FILES['album_image']['name'] ) ) {
			$parts = explode( '.', $_FILES['album_image']['name'] );
			$_FILES['album_image']['name'] = $parts[0] . '_' . generate_random_string() . '.' . $parts[1];
		}

		$allowed   = [ 'image/jpg', 'image/jpeg', 'image/png', 'image/gif' ];
		$filetype  = wp_check_filetype( $_FILES['album_image']['name'] )['type'];
		$upload_ok = in_array( $filetype, $allowed, true );

		if ( $upload_ok ) {
			add_filter( 'upload_dir', 'ai_upload_dir' );
			$upload = wp_handle_upload( $_FILES['album_image'], [ 'test_form' => false ] );
			remove_filter( 'upload_dir', 'ai_upload_dir' );

			if ( ! isset( $upload['error'] ) ) {
				$filename = basename( $upload['file'] );

				// Create thumbnail
				$image = wp_get_image_editor( $upload['file'] );
				if ( ! is_wp_error( $image ) ) {
					$image->resize( 200, 200, false );
					$image->set_quality( 100 );
					$image->save( $image->generate_filename( 'thumb', AI_GALLERY_THUMB_DIR_PATH ) );
				}
			}
		}
	}

	// Insert into DB
	$wpdb->insert(
		$ai_show_table_album,
		[
			'album_title'       => $album_title,
			'album_date'        => current_time( 'Y-m-d' ),
			'album_cover_image' => $filename,
			'album_slug'        => $album_slug,
			'album_visible'     => $album_visible,
			'album_order'       => $album_order,
		]
	);

	wp_redirect( admin_url( 'admin.php?page=ai_gallery&album_insert_success=1' ) );
	exit;
}

?>

<script type="text/javascript">

document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('frmnewalbum');

	form.addEventListener('submit', function (event) {
		let valid = true;
		let errors = [];

		// Check Album Title
		const albumTitle = document.getElementById('album_title');
		if (!albumTitle.value.trim()) {
			valid = false;
			errors.push('Please enter an album title.');
		}

		// Check Album Image File Type (if file selected)
		const albumImage = document.getElementById('album_image');
		if (albumImage.files.length > 0) {
			const file = albumImage.files[0];
			const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
			if (!allowedTypes.includes(file.type)) {
				valid = false;
				errors.push('Only JPG, JPEG, PNG or GIF files are allowed.');
			}
		}

		// Display errors and prevent submit
		if (!valid) {
			event.preventDefault();
			alert(errors.join('\n'));
		}
	});
});

</script>

<div class="wrap">
	<h1><?php _e('Add New Album for AI Gallery', 'aigallery'); ?></h1>
	<form method="post" name="frmnewalbum" id="frmnewalbum" enctype="multipart/form-data">
		<?php wp_nonce_field( 'add-album' ); ?>
		
		<div class="form-field">
			<label for="album_title"><?php _e('Album Title', 'aigallery'); ?> *</label>
			<input type="text" name="album_title" id="album_title" class="regular-text" required
			       value="<?php echo esc_attr( $ai_edit_res[0]['album_title'] ?? '' ); ?>">
		</div>

		<div class="form-field">
			<label for="album_image"><?php _e('Album Cover Image', 'aigallery'); ?></label>
			<input type="file" name="album_image" id="album_image" accept=".jpg,.jpeg,.png,.gif">
			<?php
			if ( ! empty( $ai_file_thumb ) && ! empty( $thub_album_name ) ) {
				$cover_url = esc_url( $ai_file_thumb . $thub_album_name );
				echo '<div><img src="' . $cover_url . '" alt="Album Cover" style="max-width: 200px;"></div>';
			}
			?>
			<p class="description"><?php _e('Allowed file types: .jpg, .jpeg, .png, .gif', 'aigallery'); ?></p>
		</div>

		<div class="form-field">
			<label for="album_visible"><?php _e('Visible?', 'aigallery'); ?></label>
			<select name="album_visible" id="album_visible">
				<option value="1" <?php selected( $ai_edit_res[0]['album_visible'] ?? '', '1' ); ?>><?php _e('Yes', 'aigallery'); ?></option>
				<option value="0" <?php selected( $ai_edit_res[0]['album_visible'] ?? '', '0' ); ?>><?php _e('No', 'aigallery'); ?></option>
			</select>
		</div>

		<p class="submit">
			<input type="submit" class="button-primary" value="<?php echo esc_attr( $button_value ); ?>" name="Submit">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Cancel' ); ?>" name="Submit">
			<input type="hidden" name="Action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="album_date" value="<?php echo esc_attr( $ai_edit_res[0]['album_date'] ?? '' ); ?>">
			<input type="hidden" name="album_slug" value="<?php echo esc_attr( $ai_edit_res[0]['album_slug'] ?? '' ); ?>">
			<input type="hidden" name="album_id" value="<?php echo esc_attr( $ai_edit_res[0]['album_id'] ?? '' ); ?>">
		</p>

		<p class="description">
			<?php _e('Fields marked with * are required.', 'aigallery'); ?>
		</p>
	</form>
</div>
