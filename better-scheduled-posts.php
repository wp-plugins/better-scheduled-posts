<?php
/**
 * Plugin Name: Better Scheduled Posts
 * Description: Improves the management of your scheduled Posts by making them visible on the front end to administrators/contributors, adding them to the internal link box and enabling you to push them back by any number of days.
 * Author: Carlo Manf
 * Author URI: http://carlomanf.id.au
 * Version: 1.0.3
 */

// Show scheduled posts to administrators/contributors
add_filter( 'pre_get_posts', 'bsp_show_scheduled_posts' );
function bsp_show_scheduled_posts( $query ) {

	if ( current_user_can( 'edit_posts' ) && !$query->is_singular() && !is_admin() ) {
		$statuses = $query->get( 'post_status' );

		if ( !$statuses )
			$statuses = 'publish';

		if ( is_string( $statuses ) )
			$statuses = explode( ',', $statuses );

		if ( !in_array( 'future', $statuses ) ) {
			$statuses[] = 'future';
			$query->set( 'post_status', $statuses );
		}
	}

	return $query;
}

// Validate data before rescheduling posts
function bsp_validate_post_data() {
	$valid = true;
	$date = explode( '-', $_POST[ 'start_date' ] );

	if ( 3 !== count( $date ) || !checkdate( $date[ 1 ], $date[ 2 ], $date[ 0 ] ) ) {
		echo '<div class="error"><p>Invalid start date.</p></div>';
		$valid = false;
	}

	if ( !intval( $_POST[ 'no_of_days' ] ) ) {
		echo '<div class="error"><p>Invalid number of days.</p></div>';
		$valid = false;
	}

	return $valid;
}

// Reschedule posts
function bsp_push_posts() {

	$scheduled_posts = get_posts( array( 'post_status' => 'future', 'posts_per_page' => -1 ) );
	$success = 0;

	foreach ( $scheduled_posts as $post ) {
		$date = strtotime( $post->post_date );
		$date_gmt = strtotime( $post->post_date_gmt );
		$start_date = strtotime( $_POST[ 'start_date' ] );

		if ( $date_gmt < $start_date )
			continue;

		$updated_post[ 'ID' ] = $post->ID;
		$updated_post[ 'post_date' ] = date( 'Y-m-d H:i:s', $date + ( (int) $_POST[ 'no_of_days' ] * 86400 ) );
		$updated_post[ 'post_date_gmt' ] = date( 'Y-m-d H:i:s', $date_gmt + ( (int) $_POST[ 'no_of_days' ] * 86400 ) );

		wp_update_post( $updated_post );

		$success++;
	}

	if ( $success )
		echo '<div class="updated"><p>Successfully rescheduled ' . $success . ' post(s)!</p></div>';
	else
		echo '<div class="updated"><p>No changes were made because no scheduled posts were found.</p></div>';

}

// Tools page
function bsp_tools_page() {

	if ( !empty( $_POST[ 'push' ] ) )
		if ( bsp_validate_post_data() )
			bsp_push_posts();

	?><div class="wrap">
		<h2>Better Scheduled Posts</h2>
		<p>Use this tool to push back your scheduled posts.</p>
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="start_date">Start Date</label></th>
					<td>
						<input type="text" id="start_date" name="start_date" value=""><p class="description">Format: yyyy-mm-dd</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="no_of_days">Number of Days</label></th>
					<td><input type="text" id="no_of_days" name="no_of_days" value=""></td>
				</tr>
			</table>
			<p class="submit"><input type="submit" name="push" class="button button-primary" value="Reschedule Posts"></p>
		</form>
	</div><?php

}

// Add menu page
add_action( 'admin_menu', 'bsp_add_menu_page' );
function bsp_add_menu_page() {
	add_submenu_page( 'tools.php', 'Better Scheduled Posts', 'Scheduled Posts', 'edit_others_posts', 'bsp', 'bsp_tools_page' );
}

// Clean permalinks for scheduled posts
add_filter( 'post_link', 'bsp_clean_permalinks', 10, 3 );
function bsp_clean_permalinks( $permalink, $post, $leavename ) {

	if ( 'future' === $post->post_status ) {
		$temp_post = clone $post;
		$temp_post->post_status = 'publish';
		$permalink = get_permalink( $temp_post );
	}

	return $permalink;
}

// Add scheduled posts to link box
add_action( 'pre_get_posts', 'bsp_link_box' );
function bsp_link_box( $query ) {

	// Ensure request for internal linking
	if ( ! isset( $_POST ) || ! isset( $_POST[ 'action' ] ) || 'wp-link-ajax' !== $_POST[ 'action' ] )
		return;

	// Add future posts to the query
	$post_status = (array) $query->query_vars[ 'post_status' ];
	if ( !in_array( 'future', $post_status ) )
		$post_status[] = 'future';

	$query->set( 'post_status', $post_status );
}
