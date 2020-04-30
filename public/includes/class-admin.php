<?php

namespace WP_Stateless\API\Admin;

/**
 * Admin side of the plugin
 *
 * @link  https://github.com/asiermusa
 * @since 1.0.0
 */
class Admin_Class
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The ID of this plugin.
     */
    private $plugin_name;
    
    /**
     * The domain especified for the plugin.
     *
     * @since    1.0.0
     *
     * @var string The domain used for this plugin.
     */
    private $domain;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name The name of the plugin.
     */
    public function __construct($plugin_name)
    {
        $this->plugin_name = $plugin_name;
        $this->domain = $plugin_name;
        
        // Display the admin notification
        add_action( 'admin_notices', array( $this, 'admin_notice_activation' ) );
        
        // Change avatar
        add_filter( 'get_avatar', array( $this, 'social_login_custom_avatar' ), 15, 5 );
    }
  	 
    /**
     * Display notice message when activating the plugin.
     *
     * @since	  1.0.0
     *
     * @return  void
     */
  	public function admin_notice_activation() {

      if ( get_option( 'stateless-system-activation-message' ) == true ) {
      
  			$html  = '<div class="notice notice-success is-dismissible">';
  			$html .= '<p>';
  			$html .= '<h4>' . $this->plugin_name . '</h4>';
  			$html .= sprintf( __( 'Thanks for using the <code>' . $this->plugin_name . '</code> plugin. Take a momment and configure the options in the Settings Page.', $this->domain ));
  			$html .= '</p>';
  			$html .= '</div>';
  			echo $html;
			
        delete_option( 'stateless-system-activation-message' );
      }
  	}
  	
  	
  	/**
     * Display custom avatar for social users
     *
     * @since	  1.0.0
     *
     * @return  void
     */
  	public function social_login_custom_avatar( $avatar, $mixed, $size, $default, $alt = '' ) {
      
      $user_id = $mixed;
      
      $google_avatar = get_user_meta($user_id, 'google-oauth-avatar', true);
      $twitter_avatar = get_user_meta($user_id, 'twitter-oauth-avatar', true);
      
      if($google_avatar){
        $user_picture = $google_avatar;
      }elseif($twitter_avatar){
        $user_picture = $twitter_avatar;
      }
      
      if($user_picture) {
        return '<img src="' . $user_picture . '" class="avatar" height="' . $size . '" width="' . $size . '"/>';
      } else {
        return $avatar;
      }
  	}
}
