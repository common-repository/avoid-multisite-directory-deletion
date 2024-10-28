<?php
/**
 * Plugin Name: Avoid Multisite Directory Deletion
 * Description: This plugin will skip directory deletion when a multisite sub blog is deleted.
 * Version: 1.0.0
 * Author: Zain hassan
 */

add_action( 'wp_uninitialize_site', 'wp_uninitialize_site', 10, 1 );
remove_action( 'wp_uninitialize_site', 'wp_uninitialize_site', 10, 1 );
add_action( 'wp_uninitialize_site', 'my_wp_uninitialize_site', 10, 1 );
 
function my_wp_uninitialize_site( $site_id ) {
    global $wpdb;
 
    if ( empty( $site_id ) ) {
        return new WP_Error( 'site_empty_id', __( 'Site ID must not be empty.' ) );
    }
 
    $site = get_site( $site_id );
    if ( ! $site ) {
        return new WP_Error( 'site_invalid_id', __( 'Site with the ID does not exist.' ) );
    }
 
    if ( ! wp_is_site_initialized( $site ) ) {
        return new WP_Error( 'site_already_uninitialized', __( 'The site appears to be already uninitialized.' ) );
    }
 
    $users = get_users(
        array(
            'blog_id' => $site->id,
            'fields'  => 'ids',
        )
    );
 
    // Remove users from the site.
    if ( ! empty( $users ) ) {
        foreach ( $users as $user_id ) {
            remove_user_from_blog( $user_id, $site->id );
        }
    }
 
    $switch = false;
    if ( get_current_blog_id() !== $site->id ) {
        $switch = true;
        switch_to_blog( $site->id );
    }
 
    $uploads = wp_get_upload_dir();
 
    $tables = $wpdb->tables( 'blog' );
 
    /**
     * Filters the tables to drop when the site is deleted.
     *
     * @since MU (3.0.0)
     *
     * @param string[] $tables  Array of names of the site tables to be dropped.
     * @param int      $site_id The ID of the site to drop tables for.
     */
    $drop_tables = apply_filters( 'wpmu_drop_tables', $tables, $site->id );
 
    foreach ( (array) $drop_tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
 
    /**
     * Filters the upload base directory to delete when the site is deleted.
     *
     * @since MU (3.0.0)
     *
     * @param string $uploads['basedir'] Uploads path without subdirectory. @see wp_upload_dir()
     * @param int    $site_id            The site ID.
     */
    $dir     = apply_filters( 'wpmu_delete_blog_upload_dir', $uploads['basedir'], $site->id );
    $dir     = rtrim( $dir, DIRECTORY_SEPARATOR );
    $top_dir = $dir;
    $stack   = array( $dir );
    $index   = 0;
 
    // phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
    if ( $switch ) {
        restore_current_blog();
    }
 
    return true;
}