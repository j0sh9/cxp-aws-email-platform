<?php

/*
  Plugin Name: _AWS_Email_Marketing
  Version: 0.1
  Plugin URI: https://corexp.com
  Description: A built in email marketing platform using Amazon AWS SES
  Author: Josh Buchanan
  Author URI: https://corexp.com
 * Text Domain: cxpemail
 * Domain Path: /
 */

//*** function cxp_email_preview($post) Need to prevent the "Send Preview" button from causing the "do you want to leave this site?" alert.

define('PLUGIN_PATH', plugin_dir_path( __FILE__ ));

require_once(PLUGIN_PATH.'includes/templates/template.php');
require_once(PLUGIN_PATH.'includes/class.html2text.inc');

// Register Custom Post Type
function cxp_email_custom_post_type() {

	$labels = array(
		'name'                  => _x( 'Emails', 'Post Type General Name', 'cxpemail' ),
		'singular_name'         => _x( 'Email', 'Post Type Singular Name', 'cxpemail' ),
		'menu_name'             => __( 'Marketing Emails', 'cxpemail' ),
		'name_admin_bar'        => __( 'Marketing Email', 'cxpemail' ),
		'archives'              => __( 'Email Archives', 'cxpemail' ),
		'attributes'            => __( 'Email Attributes', 'cxpemail' ),
		'parent_item_colon'     => __( 'Parent Item:', 'cxpemail' ),
		'all_items'             => __( 'All Emails', 'cxpemail' ),
		'add_new_item'          => __( 'Add New Email', 'cxpemail' ),
		'add_new'               => __( 'Add New Email', 'cxpemail' ),
		'new_item'              => __( 'New Email', 'cxpemail' ),
		'edit_item'             => __( 'Edit Email', 'cxpemail' ),
		'update_item'           => __( 'Update Email', 'cxpemail' ),
		'view_item'             => __( 'View Email', 'cxpemail' ),
		'view_items'            => __( 'View Emails', 'cxpemail' ),
		'search_items'          => __( 'Search Email', 'cxpemail' ),
		'not_found'             => __( 'Not found', 'cxpemail' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'cxpemail' ),
		'featured_image'        => __( 'Featured Image', 'cxpemail' ),
		'set_featured_image'    => __( 'Set featured image', 'cxpemail' ),
		'remove_featured_image' => __( 'Remove featured image', 'cxpemail' ),
		'use_featured_image'    => __( 'Use as featured image', 'cxpemail' ),
		'insert_into_item'      => __( 'Insert into item', 'cxpemail' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'cxpemail' ),
		'items_list'            => __( 'Items list', 'cxpemail' ),
		'items_list_navigation' => __( 'Items list navigation', 'cxpemail' ),
		'filter_items_list'     => __( 'Filter items list', 'cxpemail' ),
	);
	$args = array(
		'label'                 => __( 'Email', 'cxpemail' ),
		'description'           => __( 'Create and Send Marketing Email', 'cxpemail' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', ),
		//'taxonomies'            => array( 'category', 'post_tag' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-email-alt',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,		
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
	);
	register_post_type( 'cxp_email', $args );

}
add_action( 'init', 'cxp_email_custom_post_type', 0 );

/*
* Stop TinyMCE from striping HTML from the editor
*/
add_action( 'admin_head', 'cxp_dont_strip_email_html' );
function cxp_dont_strip_email_html() {
    // Do nothing if it isn't our custom post type
    if ( 'cxp_email' !== get_post_type() ) return;
    // It's our post type. Don't Strip!
    add_filter('tiny_mce_before_init', function($init) {
        $init['wpautop'] = false;
        return $init;
    });
}

/*
** Get the list of users to send the email too
1. Add Email Black List
*/
function cxp_email_get_list($post_id) {
    $list_type = get_post_meta( $post_id, '_cxp_email_list', true );
    if ( empty($list_type) ) return false;
    if ( $list_type == 'all' ) {
        $args = array(
            'meta_query'  => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '1',
                    'compare' => '!='
                ),
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
    }
    if ( $list_type == 'retail') {
        $args = array(
            'meta_query'  => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '1',
                    'compare' => '!='
                ),
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'role__in' => array( 'administrator', 'customer', 'subscriber' )
        );
    }
    if ( $list_type == 'wholesale') {
        $args = array(
            'meta_query'  => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '1',
                    'compare' => '!='
                ),
                array(
                    'key'     => '_cxp_email_unsubscribed',
                    'value'   => '',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'role__in' => array( 'administrator', 'wholesale_customer', 'distributor' )
        );
    }
    $users = get_users( $args );
    
    if ( empty($users) ) return false;
    return $users;
}

/*
** The post is published... SEND the email
*/
function post_published_notification( $post_id, $post ) {
    if ( get_post_meta( $post_id, '_cxp_email_sent', true ) ) return;//Check to make sure email wasn't already sent
    
    $users = cxp_email_get_list($post_id);
    
    $speed_limit = floor( 1000000 / 14 );
    
    if ( $users ) {
        ignore_user_abort(); // run script in background 
        set_time_limit(0); // run script forever 
        $headers[] = '';
        $sent = array();
        $errors = array();
        update_post_meta( $post_id, '_cxp_email_queued', count($users) );
		$post = cxp_add_post_data($post);
        foreach( $users as $user ) {
            
            $to = $user->display_name." <".$user->user_email.">";
            $subject = $post->post_title;
            
            $message = cxp_get_email_message($post, $user);
            
            $send = wpses_mail( $to, $subject, $message, $headers );
            if ($send) {
                $sent[] = array($user->ID => $user->user_email );
                update_post_meta( $post_id, '_cxp_email_sent_count', count($sent) );
            } else {
                $errors[] = array($user->ID => $user->user_email );
                update_post_meta( $post_id, '_cxp_email_errors_count', count($errors) );
            }
            usleep($speed_limit);
        }
        update_post_meta( $post_id, '_cxp_email_sent', $sent );
        update_post_meta( $post_id, '_cxp_email_errors', $errors );
        update_post_meta( $post_id, '_cxp_email_sent_count', count($sent) );
        update_post_meta( $post_id, '_cxp_email_errors_count', count($errors) );
        update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'success','msg' => count($sent).' emails sent successfully.' ));
    } else {
        update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'error','msg'=>'Email not sent. No Users Found.' ) );
    }
}
add_action( 'publish_cxp_email', 'post_published_notification', 10, 2 );

/**
 * Adds a submenu page under a custom post type parent.
 */
add_action('admin_menu', 'cxp_email_settings_menu');
function cxp_email_settings_menu() {
    if ( !current_user_can('manage_options') ) return;
    add_submenu_page(
        'edit.php?post_type=cxp_email',
        __( 'Email Marketing Settings', 'cxpemail' ),
        __( 'Settings', 'cxpemail' ),
        'manage_options',
        'email-marketing-settings',
        'cxp_email_settings_page_callback'
    );
}
 
/**
 * Display callback for the submenu page.
 */
function cxp_email_settings_page_callback() { 
    ?>
    <div class="wrap">
        <h1><?php _e( 'Email Marketing Settings', 'cxpemail' ); ?></h1>
        <p><?php _e( 'Helpful stuff here', 'cxpemail' ); ?></p>
    </div>
    <?php
}


/**
 * Add custom metaboxes
 */
add_action( 'add_meta_boxes', 'cxp_add_email_metaboxes' );
function cxp_add_email_metaboxes() {
    // Email Stats MetaBox
	add_meta_box(
		'cxp_email_stats',
		__( 'Quick Stats', 'cxp-email' ),
		'cxp_email_stats_html',
		'cxp_email',
		'side',
		'high',
        null
	);
    // Send Email Preview MetaBox
	add_meta_box(
		'cxp_email_preview',
		__( 'Send Email Preview', 'cxp-email' ),
		'cxp_email_preview_html',
		'cxp_email',
		'side',
		'high',
        null
	);
    // Select List MetaBox
	add_meta_box(
		'cxp_email_list',
		__( 'Select Email List', 'cxp-email' ),
		'cxp_email_list_html',
		'cxp_email',
		'side',
		'high',
        null
	);
    // Background Image MetaBox
    add_meta_box(
        'cxp_email_background_image',
        __( 'Email Background', 'cxp-email' ),
        'cxp_email_background_image_html',
        'cxp_email',
        'side',
        'low',
        null
    );
}

/*
* Save preview email address and send mail if the "Send Preview" button was clicked
*/
add_action( 'save_post_cxp_email', 'cxp_email_just_save_meta_box_data' );
function cxp_email_just_save_meta_box_data( $post_id ){
	// verify taxonomies meta box nonce
	if ( !isset( $_POST['cxp_email_preview_nonce'] ) || !wp_verify_nonce( $_POST['cxp_email_preview_nonce'], basename( __FILE__ ) ) ){
		return;
	}
	// return if autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){
		return;
	}
	// Check the user's permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ){
		return;
	}
	if ( isset( $_REQUEST['cxp_email_preview_address'] ) ) {
        $email = $_POST['cxp_email_preview_address'];
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if ( $_REQUEST['send_preview'] != 'Sending Preview' && filter_var($email, FILTER_VALIDATE_EMAIL) ) {
		  update_post_meta( $post_id, '_cxp_email_preview_address', $email );
        }
	}
}

add_action( 'draft_cxp_email', 'cxp_email_save_meta_box_data' );
add_action( 'pending_cxp_email', 'cxp_email_save_meta_box_data' );
function cxp_email_save_meta_box_data( $post_id ){
	// verify taxonomies meta box nonce
	if ( !isset( $_POST['cxp_email_preview_nonce'] ) || !wp_verify_nonce( $_POST['cxp_email_preview_nonce'], basename( __FILE__ ) ) ){
		return;
	}
	// return if autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){
		return;
	}
	// Check the user's permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ){
		return;
	}
    
	if( isset( $_POST['_cxp_email_background_image'] ) ) {
		$image_id = (int) $_POST['_cxp_email_background_image'];
		update_post_meta( $post_id, '_cxp_email_background_image_id', $image_id );
	}
    
	if ( isset( $_REQUEST['cxp_email_preview_address'] ) ) {
        $email = $_POST['cxp_email_preview_address'];
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
		update_post_meta( $post_id, '_cxp_email_preview_address', $email );
        if ( $_REQUEST['send_preview'] == 'Sending Preview' && filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            update_post_meta( $post_id, '_cxp_email_preview_sent', 'sending' );
            send_email_preview( $post_id, $email );
        }
	}
}

/*
* Email Preview status messages
*/
function cxp_email_preview_notice() {
	global $post;
    if ( !$post ) return;
	$post_id = $post->ID;
	$preview_notice = get_post_meta( $post_id, '_cxp_email_notice', true );
	if ( empty($preview_notice) ) return;
    ?>
    <div class="notice-<?php echo $preview_notice['status']; ?> notice">
        <p><?php _e( $preview_notice['msg'], 'cxp_email' ); ?></p>
    </div>
    <?php
	delete_post_meta( $post_id, '_cxp_email_notice');
}
add_action( 'admin_notices', 'cxp_email_preview_notice' );

/*
* Email preview MetaBox HTML
*/
function cxp_email_preview_html($post) {
	
	wp_nonce_field( basename( __FILE__ ), 'cxp_email_preview_nonce' );
    $cxp_email_preview_address = get_post_meta( $post->ID, '_cxp_email_preview_address', true );
    if ( empty($cxp_email_preview_address) ) {
        $user = wp_get_current_user();
        $cxp_email_preview_address = $user->user_email;
    }
    if ( $post->post_status == 'pending' || $post->post_status == 'draft' || $post->post_status == 'new' || $post->post_status == 'auto-draft' ) {
        $disabled = '';
    } else {
        $disabled = ' disabled';
    }
    echo '<p><input type="email" name="cxp_email_preview_address" value="'.$cxp_email_preview_address.'" class="widefat"></p>';
    echo '<input type="hidden" id="send-preview" name="send_preview" value="" />';
    echo '<p><input type="button" id="send-preview-btn" name="send_preview_btn" value="Send Preview" class="button button-primary button-large preview_submit"'.$disabled.' ></p>';
    echo "<script>
            jQuery('.preview_submit').click(function(e) {
                e.preventDefault();
                jQuery('#send-preview-btn').val('Sending Preview...');
                jQuery('#send-preview').val('Sending Preview');
                jQuery('#save-post').click();
            });
          </script>";
}

/*
* Send the email Preview
*/
/** IMPORTANT! the amazon ses plugin must be edited on line 550 in the wpses_mail function */
function send_email_preview( $post_id, $email ) { 
	if ( empty($email) ) {
		update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'error','msg'=>'Preview email not sent. No email address.' ) );
		return;
	}
	
    $post = get_post($post_id);
	if ( empty($post->post_title) ) {
		update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'error','msg'=>'Preview email not sent. No subject.' ) );
		return;
	}
	if ( empty($post->post_content) ) {
		update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'error','msg'=>'Preview email not sent. No body.' ) );
		return;
	}
	
    $to = $email;
    $post->post_title = "PREVIEW: ".$post->post_title;
	
	$post = cxp_add_post_data($post);
    
    $user = get_user_by( 'email', $email );
    $user = $user ? $user : wp_get_current_user();
    
    $message = cxp_get_email_message($post, $user);
	
	$subject = $message['subject'];
    
    $headers[] = '';
    
    $sent = wpses_mail( $to, $subject, $message, $headers );
    if ( $sent ) {
        update_post_meta( $post_id, '_cxp_email_preview_sent', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'success','msg'=>'Preview email sent to '.$email ));
    } else {
        update_post_meta( $post_id, '_cxp_email_preview_sent', $sent );
		update_post_meta( $post_id, '_cxp_email_notice', array('status'=>'error','msg'=>'Preview email not sent. Error sending.' ) );	
    }
}

/*
* Email Stats MetaBox HTML
*/
function cxp_email_stats_html($post) {
    $queued = get_post_meta( $post->ID, '_cxp_email_queued', true );
    $sent = get_post_meta($post->ID, '_cxp_email_sent_count', true);
    $errors = get_post_meta($post->ID, '_cxp_email_errors_count', true);
    
    $opens = get_post_meta($post->ID, '_cxp_email_tracking_pixel', true);
    $opens = empty($opens)?0:$opens;
    $clicks = get_post_meta($post->ID, '_cxp_email_link_clicks', true);
    //$sales = "-";
    
    $unsubscribes = get_post_meta($post->ID, '_cxp_email_unsubscribes', true );
    
    echo "<p><strong>Emails Queued:</strong> $queued <br>";
    echo "<strong>Emails Sent:</strong> $sent <br>";
    echo "<strong>Email Errors:</strong> $errors </p>";
    
    echo "<p><strong>Email Opens:</strong> $opens <br>";
    echo "<strong>Link Clicks:</strong> $clicks <br>";
    //echo "<strong>Promo Sales:</strong> $sales </p>";
    
    echo "<p><strong>Unsubscribes:</strong> $unsubscribes </p>";
}

/*
** Email List MetaBox HTML
*/
function cxp_email_list_html($post) {
    $selected_list = get_post_meta( $post->ID, '_cxp_email_list', true);
    $options = array('all','retail','wholesale');
    foreach ( $options as $option ) {
        if ( $option == $selected_list ) {
            ${"selected_$option"} = 'checked';
        } else {
            ${"selected_$option"} = '';
        }
    }
?>
    <label><input type="radio" name="cxp_email_list" id="cxp_email_list_all" value="all" <?=$selected_all?> /> <strong>All</strong> Customers</label><br>
    <label><input type="radio" name="cxp_email_list" id="cxp_email_list_retail" value="retail" <?=$selected_retail?> /> <strong>Retail</strong> Customers</label><br>
    <label><input type="radio" name="cxp_email_list" id="cxp_email_list_wholesale" value="wholesale" <?=$selected_wholesale?> /> <strong>Wholesale</strong> Customers</label>
<?php
}

/*
** Email List MetaBox SAVE
*/
add_action( 'save_post', 'cxp_email_list_save', 10, 1 );
function cxp_email_list_save($post_id) {
    if( isset( $_POST['cxp_email_list'] ) ) {
		update_post_meta( $post_id, '_cxp_email_list', $_POST['cxp_email_list'] );
	}    
}

/*
** Email background Image MetaBox HTML
*/
function cxp_email_background_image_html ( $post ) {
	global $content_width, $_wp_additional_image_sizes;
	$image_id = get_post_meta( $post->ID, '_cxp_email_background_image_id', true );
	$old_content_width = $content_width;
	$content_width = 254;
	if ( $image_id && get_post( $image_id ) ) {
		if ( ! isset( $_wp_additional_image_sizes['post-thumbnail'] ) ) {
			$thumbnail_html = wp_get_attachment_image( $image_id, array( $content_width, $content_width ) );
		} else {
			$thumbnail_html = wp_get_attachment_image( $image_id, 'post-thumbnail' );
		}
		//if ( ! empty( $thumbnail_html ) ) {
			$content = $thumbnail_html;
			$content .= '<p class="hide-if-no-js"><a href="javascript:;" id="remove_cxp_email_background_image_button" >' . esc_html__( 'Remove Email Background Image', 'cxp-email' ) . '</a></p>';
			$content .= '<input type="hidden" id="upload_cxp_email_background_image" name="_cxp_email_background_image" value="' . esc_attr( $image_id ) . '" />';
		//}
		$content_width = $old_content_width;
	} else {
		$content = '<img src="" style="width:' . esc_attr( $content_width ) . 'px;height:auto;border:0;display:none;" />';
		$content .= '<p class="hide-if-no-js"><a title="' . esc_attr__( 'Set Email Background Image', 'cxp-email' ) . '" href="javascript:;" id="upload_cxp_email_background_image_button" data-uploader_title="' . esc_attr__( 'Choose an image', 'cxp-email' ) . '" data-uploader_button_text="' . esc_attr__( 'Set Email Background Image', 'cxp-email' ) . '">' . esc_html__( 'Set Email Background Image', 'cxp-email' ) . '</a></p>';
		$content .= '<input type="hidden" id="upload_cxp_email_background_image" name="_cxp_email_background_image" value="" />';
	}
	
		$content .= "<script>jQuery(document).ready(function($) {

					// Uploading files
					var file_frame;

					jQuery.fn.upload_cxp_email_background_image = function( button ) {
						var button_id = button.attr('id');
						var field_id = button_id.replace( '_button', '' );

						// If the media frame already exists, reopen it.
						if ( file_frame ) {
						  file_frame.open();
						  return;
						}

						// Create the media frame.
						file_frame = wp.media.frames.file_frame = wp.media({
						  title: jQuery( this ).data( 'uploader_title' ),
						  button: {
							text: jQuery( this ).data( 'uploader_button_text' ),
						  },
						  multiple: false
						});

						// When an image is selected, run a callback.
						file_frame.on( 'select', function() {
						  var attachment = file_frame.state().get('selection').first().toJSON();
						  jQuery('#'+field_id).val(attachment.id);
						  jQuery('#cxp_email_background_image img').attr('src',attachment.url);
						  jQuery( '#cxp_email_background_image img' ).show();
						  jQuery( '#' + button_id ).attr( 'id', 'remove_cxp_email_background_image_button' );
						  jQuery( '#remove_cxp_email_background_image_button' ).text( 'Remove Email Background Image' );
						});

						// Finally, open the modal
						file_frame.open();
					};

					jQuery('#cxp_email_background_image').on( 'click', '#upload_cxp_email_background_image_button', function( event ) {
						event.preventDefault();
						jQuery.fn.upload_cxp_email_background_image( jQuery(this) );
					});

					jQuery('#cxp_email_background_image').on( 'click', '#remove_cxp_email_background_image_button', function( event ) {
						event.preventDefault();
						jQuery( '#upload_cxp_email_background_image' ).val( '' );
						jQuery( '#cxp_email_background_image img' ).attr( 'src', '' );
						jQuery( '#cxp_email_background_image img' ).hide();
						jQuery( this ).attr( 'id', 'upload_cxp_email_background_image_button' );
						jQuery( '#upload_cxp_email_background_image_button' ).text( 'Set Email Background Image' );
					});

				});
				</script>";
	echo $content;
}
add_action( 'save_post', 'cxp_email_background_image_save', 10, 1 );
function cxp_email_background_image_save( $post_id ) {
	if( isset( $_POST['_cxp_email_background_image'] ) ) {
		$image_id = (int) $_POST['_cxp_email_background_image'];
		update_post_meta( $post_id, '_cxp_email_background_image_id', $image_id );
	}
}

/*
* Change the Title Input placeholder to "Email Subject"
*/
add_filter('gettext','custom_enter_title');
function custom_enter_title( $input ) {

    global $post_type;

    if( is_admin() && 'Enter title here' == $input && 'cxp_email' == $post_type )
        return 'Email Subject';

    return $input;
}




/*
 * Change the featured image metabox title text
 */
add_action('do_meta_boxes', 'cxp_email_replace_featured_image_box');  
function cxp_email_replace_featured_image_box()  
{  
    remove_meta_box( 'postimagediv', 'cxp_email', 'side' );  
    add_meta_box('postimagediv', __('Email Header Banner'), 'post_thumbnail_meta_box', 'cxp_email', 'side', 'default', null);  
}
/*
 * Change the featured image metabox link text
 *
 * @param  string $content Featured image link text
 * @return string $content Featured image link text, filtered
 */
function cxp_email_change_featured_image_text( $content ) {
	if ( 'cxp_email' === get_post_type() ) {
		$content = str_replace( 'Set featured image', __( 'Set Email Banner', 'cxp_email' ), $content );
		$content = str_replace( 'Remove featured image', __( 'Remove Email Banner', 'cxp_email' ), $content );
	}
	return $content;
}
add_filter( 'admin_post_thumbnail_html', 'cxp_email_change_featured_image_text' );


// Adding a WordPress post publishing confirmation message.
$c_message = ' You\'re about to publish this post. Are you sure that it\'s ready to go?';
function confirm_publish(){
global $c_message;
echo '';
}
 
add_action('admin_footer', 'confirm_publish');

//Tracking Pixel
add_action('template_redirect', 'cxp_email_tracking_pixel');
function cxp_email_tracking_pixel() {
    if ( !isset($_GET['cxp_email_tracking_pixel']) ) return;
    
    $post_id = $_GET['cxp_email_tracking_pixel'];
    $user_id = $_GET['user_id'];
    $email_opens = get_post_meta($post_id, '_cxp_email_tracking_pixel', true) + 1;
    update_post_meta($post_id, '_cxp_email_tracking_pixel', $email_opens);
    
    // Create an image, 1x1 pixel in size
    $im=imagecreate(1,1);
    // Set the background colour
    $white=imagecolorallocate($im,255,255,255);
    // Allocate the background colour
    imagesetpixel($im,1,1,$white);
    // Set the image type
    header("content-type:image/jpg");
    // Create a JPEG file from the image
    imagejpeg($im);
    // Free memory associated with the image
    imagedestroy($im);
    die();
}

/*
** Count Link Clicks
*/
add_action('template_redirect', 'cxp_email_link_clicks');
function cxp_email_link_clicks() {
    if ( !isset($_GET['cxp_email_link_id']) ) return;
    update_post_meta($_GET['cxp_email_link_id'], '_cxp_email_link_clicks', get_post_meta($_GET['cxp_email_link_id'], '_cxp_email_link_clicks', true ) + 1 );
}

/*
** Webview Clicks
*/
add_action('template_redirect', 'cxp_email_webview_clicks');
function cxp_email_webview_clicks() {
    if ( !isset($_GET['cxp_email_2_web']) ) return;
    update_post_meta($_GET['cxp_email_2_web'], '_cxp_email_web_view', get_post_meta($_GET['cxp_email_2_web'], '_cxp_email_web_view', true ) + 1 );
}

/*
** Unsubscribe
*/
add_action('template_redirect', 'cxp_email_unsubscribe');
function cxp_email_unsubscribe() {
    if ( !isset($_GET['cxp_email_unsub_user']) && !isset($_GET['cxp_email_unsub_post']) ) return;
    update_post_meta($_GET['cxp_email_unsub_post'], '_cxp_email_unsubscribes', get_post_meta($_GET['cxp_email_unsub_post'], '_cxp_email_unsubscribes', true ) + 1 );
    update_user_meta($_GET['cxp_email_unsub_user'], '_cxp_email_unsubscribed', 1 );
    $user = get_userdata($_GET['cxp_email_unsub_user']);
    echo "<br><br><div style='text-align:center;'><p>$user->user_email has been unsubscribed.</p></div>";
    echo "<div style='text-align:center;'><p>You will no longer receive ".get_bloginfo('name')." marketing emails.</p></div>";
    echo "<div style='text-align:center;'><p>You can change this setting at any time from <a href='".get_site_url()."/my-account/account-details/' >My Account->Account Details</a></p></div>";
    echo "<div style='text-align:center;'><p>Return to <a href='".get_site_url()."' >".get_site_url()."</a> or just close this tab.</p></div>";
    die();
}

/*
** Replace Merge Tags
*/
function cxp_email_merge_tags($post, $user) {
	$user = get_userdata($user->ID);
	$search = array(
		'{first_name}',
		'{last_name}',
		'{display_name}',
		'{user_email}',
		'{ID}'
	);
	$replace = array(
		$user->first_name,
		$user->last_name,
		$user->display_name,
		$user->user_email,
		$user->ID,
	);
	$text['title'] = str_replace( $search, $replace, $post->post_title);
	$text['excerpt'] = str_replace( $search, $replace, $post->post_excerpt);
	$text['content'] = str_replace( $search, $replace, $post->post_content);
	return $text;
}

/*
** Add data to post object so we don't hit the DB over and over in the loop
*/
function cxp_add_post_data($post) {
    $bg_img_id = get_post_meta($post->ID, '_cxp_email_background_image_id', true);
    $bg_img = wp_get_attachment_image_src($bg_img_id, 'full', false);
    $post->bg_url = $bg_img ? $bg_img[0] : '';
	$post->img_url = get_the_post_thumbnail_url( $post->ID, 'full' );
	$post->post_url = get_permalink( $post->ID );
	$post->site_url = get_site_url();
	
	return $post;
}


