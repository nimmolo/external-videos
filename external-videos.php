<?php
/*
* Plugin Name: External Videos
* Plugin URI: http://wordpress.org/extend/plugins/external-videos/
* Description: This is a WordPress post types plugin for videos posted to external social networking sites. It creates a new WordPress post type called "External Videos" and aggregates videos from a external social networking site's user channel to the WordPress instance. For example, it finds all the videos of the user "Fred" on YouTube and addes them each as a new post type.
* Author: Silvia Pfeiffer
* Version: 1.0
* Author URI: http://www.gingertech.net/
* License: GPL2
* Text Domain: external-videos
* Domain Path: /localization
*/

/*
  Copyright 2010+  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  @package    external-videos
  @author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>
  @copyright  Copyright 2010+ Silvia Pfeiffer
  @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
  @version    1.0
  @link       http://wordpress.org/extend/plugins/external-videos/

*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists( 'SP_External_Videos' ) ) :

class SP_External_Videos {

  public function __construct() {

    global $features_3_0;
    global $wp_version;

    $features_3_0 = false;

    if ( version_compare( $wp_version, "3.0", ">=" ) ) {
      $features_3_0 = true;
    }

    require_once( ABSPATH . 'wp-admin/includes/taxonomy.php' );

    require_once( plugin_dir_path( __FILE__ ) . 'core/ev-admin.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'core/ev-helpers.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'core/ev-widget.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'core/ev-shortcode.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'core/simple_html_dom.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'hosts/ev-youtube.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'hosts/ev-vimeo.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'hosts/ev-dotsub.php' );
    require_once( plugin_dir_path( __FILE__ ) . 'hosts/ev-wistia.php' );

    /// *** vendor includes moved to files

    if ( $features_3_0 ) {
      require_once( plugin_dir_path( __FILE__ ) . 'core/ev-media-gallery.php' );
    }

    // includes do not bring methods into the class! they're standalone functions
    register_activation_hook( __FILE__, array( $this, 'activation' ) );
    register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
    register_activation_hook( __FILE__, array( $this, 'rewrite_flush' ) );

    add_action( 'init', array( $this, 'initialize' ) );

    add_action( 'admin_menu', array( $this, 'admin_settings' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

    /// *** Setup of Videos Gallery: implemented in ev-media-gallery.php *** ///
    add_shortcode( 'external-videos', array( $this, 'gallery' ) );

    /// *** Setup of Widget: implemented in ev-widget.php file *** ///
    add_action( 'widgets_init',  array( $this, 'load_widget' ) );

    add_filter( 'pre_get_posts', array( $this, 'filter_query_post_type' ) );
    add_filter( 'request', array( $this, 'feed_request' ) );

  }

  /*
  *  initialize
  *
  *  actions that need to go on the init hook
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function initialize() {

    $plugin_dir = basename( dirname( __FILE__ ) );
    load_plugin_textdomain( 'external-videos', false, $plugin_dir . '/localization/' );

    // create a "video" category to store posts against
    wp_create_category(__( 'External Videos', 'external-videos' ) );

    // create "external videos" post type
    register_post_type( 'external-videos', array(
      'label'           => __( 'External Videos', 'external-videos' ),
      'singular_label'  => __( 'External Video', 'external-videos' ),
      'description'     => __( 'Pulls in videos from external hosting sites', 'external-videos' ),
      'public'          => true,
      'publicly_queryable' => true,
      'show_ui'         => true,
      'capability_type' => 'post',
      'hierarchical'    => false,
      'query_var'       => true,
      'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' ),
      'taxonomies'      => array( 'post_tag', 'category' ),
      'has_archive'     => true,
      'rewrite'         => array( 'slug' => 'external-videos' ),
      'yarpp_support'   => true
    ));

    // enable thickbox use for gallery
    wp_enqueue_style( 'thickbox' );
    wp_enqueue_script( 'thickbox' );

  }

  /*
  *  admin_settings
  *
  *  Settings page
  *  Add the options page for External Videos Settings
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function admin_settings() {

    add_options_page(
      __( 'External Videos Settings', 'external-videos' ),
      __( 'External Videos', 'external-videos' ),
      'edit_posts',
      __FILE__,
      array( $this, 'settings_page' )
    );

  }

  /*
  *  settings_page
  *
  *  Used by admin_settings()
  *  This separate callback function creates the settings page html
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  function settings_page() {

    // activate cron hook if not active
    $this->activation();
    // The form HTML
    include( plugin_dir_path( __FILE__ ) . 'core/ev-settings-forms.php' );

  }

  /*
  *  admin_scripts
  *
  *  Settings page
  *  Script necessary for presenting proper form options per host
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param    $hook
  *  @return
  */

  function admin_scripts( $hook ) {

    if( "settings_page_external-videos/external-videos" != $hook ) return;

    wp_register_style( 'ev-admin', plugin_dir_url( __FILE__ ) . '/css/ev-admin.css', array(), null, 'all' );
    wp_enqueue_style( 'ev-admin' );
    wp_register_script( 'ev-admin', plugin_dir_url( __FILE__ ) . '/js/ev-admin.js', array('jquery'), false, true );
    wp_enqueue_script( 'ev-admin' );

    // Pass this array to the admin js
    // For the nonce
    $settings_nonce = wp_create_nonce( 'ev_settings' );

    $VIDEO_HOSTS = SP_EV_Admin::admin_get_hosts_quick();

    // Make these variables an object array for the jquery later
    wp_localize_script( 'ev-admin', 'evSettings', array(
      'ajax_url'       => admin_url( 'admin-ajax.php' ),
      'nonce'         => $settings_nonce,
      'videohosts'    => $VIDEO_HOSTS,
    ) );

  }

  /*
  *  get_options
  *
  *  Used by ev-admin.php, feed_request(), daily_function()
  *  Gets sp_external_videos_options, returns usable array $options
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  1.0
  *
  *  @param
  *  @return
  */

  static function get_options(){

    // Load existing plugin options for this form
    $raw_options = get_option( 'sp_external_videos_options' );
    // echo '<pre>$raw_options: '; print_r($raw_options); echo '</pre>';

    // Set defaults for the basic options
    if( !$raw_options ) {
      $options = array( 'version' => 1, 'authors' => array(), 'hosts' => array(), 'rss' => false, 'delete' => true, 'attrib' => false );
    } else {
      $options = $raw_options;
      // below is needed, because if anything gets unset it throws an error
      if( !array_key_exists( 'version', $options ) ) $options['version'] = 1;
      if( !array_key_exists( 'authors', $options ) ) $options['authors'] = array();
      if( !array_key_exists( 'hosts', $options ) ) $options['hosts'] = array();
      if( !array_key_exists( 'rss', $options ) ) $options['rss'] = false;
      if( !array_key_exists( 'delete', $options ) ) $options['delete'] = false;
      if( !array_key_exists( 'attrib', $options ) ) $options['attrib'] = false;
    };
    // echo '<pre style="margin-left:150px;">$options: '; print_r($options); echo '</pre>';

    return $options;

  }

  /*
  *  filter_query_post_type
  *
  *  add external-video posts to query on Category and Tag archive pages
  *  FIX by Chris Jean, chris@ithemes.com
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $query
  *  @return
  */

  function filter_query_post_type( $query ) {

    if ( ( isset( $query->query_vars['suppress_filters'] ) && $query->query_vars['suppress_filters'] ) || ( ! is_category() && ! is_tag() && ! is_author() ) )
      return $query;

    $post_type = get_query_var( 'post_type' );

    if ( 'any' == $post_type )
      return $query;

    if ( empty( $post_type ) ) {
      $post_type = 'any';
    }

    else {
      if ( ! is_array( $post_type ) )
        $post_type = array( $post_type );

      $post_type[] = 'external-videos';
    }

    $query->set( 'post_type', $post_type );

    return $query;

  }

  /*
  *  feed_request
  *
  *  add external-video posts to RSS feed
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param    $qv
  *  @return  $qv
  */

  function feed_request( $qv ) {

    $options = SP_External_Videos::get_options();

    if ( $options['rss'] == true ) {
      if ( isset( $qv['feed'] ) && !isset( $qv['post_type'] ) )
        $qv['post_type'] = array( 'external-videos', 'post' );
    }

    return $qv;
  }

  /*
  *  load_widget
  *
  *  load the widget defined in ev-widget.php
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function load_widget() {

    return register_widget( 'WP_Widget_SP_External_Videos' );

  }

  /*
  *  activation
  *
  *  register daily cron on plugin activation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  static function activation() {

    if ( !wp_next_scheduled( 'ev_daily_event' ) ) {
      wp_schedule_event( time(), 'daily', 'ev_daily_event' );
    }

  }

  /*
  *  activation
  *
  *  unregister daily cron on plugin deactivation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function deactivation() {

    wp_clear_scheduled_hook( 'ev_daily_event' );

  }

  /*
  *  rewrite_flush
  *
  *  Flush rewrite on plugin activation
  *
  *  @type  function
  *  @date  31/10/16
  *  @since  0.26
  *
  *  @param
  *  @return
  */

  function rewrite_flush() {
    // First, we "add" the custom post type via the initialize function.
    $this->initialize();

    // ATTENTION: This is *only* done during plugin activation hook in this example!
    // You should *NEVER EVER* do this on every page load!!
    flush_rewrite_rules();
  }

} // end class
endif;

/*
* Launch the whole plugin
*/

global $SP_External_Videos;
$SP_External_Videos = new SP_External_Videos();
?>
