<?php

namespace WP_Stateless\API\Password;

/**
 * Reset password functionality from the frontend, without
 * use WordPress secreens
 *
 * @link  https://github.com/asiermusa
 * @since 1.0.0
 */
class Password_Reset_Class
{
    
    /**
     * The domain especified for the plugin.
     *
     * @since    1.0.0
     *
     * @var string The domain used for this plugin.
     */
    private $domain;
    
    /**
     * The slug of the 'password complete' page
     *
     * @since    1.0.0
     *
     * @var string The slug of the 'password complete' page.
     */
    private $complete_page;
    
    /**
     * Initialize the class
     *
     * @since    1.0.0
     *
     */
    public function __construct($plugin_name)
    {
        
        $this->domain = $plugin_name;
        $this->complete_page = 'complete';
        
        // Load password reset logic
        add_action( 'template_redirect', array( $this, 'reset_post_request' ), 99 );
        add_shortcode( 'reset_password', array( $this, 'reset_password_shortcode') );
        add_shortcode( 'complete_form', array( $this, 'complete_form_shortcode') );
    }
    
    /**
     * Password reset form shortcode [reset_password]
     *
     * @since    1.0.0
     *
     * @return   void
     */
    public function reset_password_shortcode() {
    	return $this->wcpt_get_template( 'password-reset-form.php');
    }
    
    /**
     * Password reset complete shortoce [complete_form]
     *
     * @since    1.0.0
     *
     * @return   void
     */
    public function complete_form_shortcode() {
    	return $this->wcpt_get_template( 'password-reset-complete.php');
    }
    
    
    /**
     * Locate template
     *
     * @param string $template_name Name of the file to load
     * @param string $template_path Path of the template to load (pugin dir)
     * @param string $default_path Name of the folder that contains the templates
     *
     * @return array filter
     */
    private function wcpt_locate_template( $template_name, $template_path = '', $default_path = '' ) {
      
    	if ( ! $template_path ) :
    		$template_path = plugin_dir_path( __FILE__ );
    	endif;
    
    	// Set default plugin templates path.
    	if ( ! $default_path ) :
    		$default_path = plugin_dir_path( __FILE__ ) . 'partials/'; // Path to the template folder
    	endif;
    	
    	// Search template file in theme folder.
    	$template = locate_template( array(
    		$template_path . $template_name,
    		$template_name
    	) );
    
    	// Get plugins template file.
    	if ( ! $template ) :
    		$template = $default_path . $template_name;
    	endif;
    
    	return apply_filters( 'wcpt_locate_template', $template, $template_name, $template_path, $default_path );
    
    }

    
    /**
     * Get the template to load the shotcodes
     *
     * @see wcpt_locate_template()
     *
     * @param string $template_name Name of the file to load
     * @param string $template_path Path of the template to load (pugin dir)
     * @param string $default_path Name of the folder that contains the templates
     *
     * @return void
     */
    private function wcpt_get_template( $template_name, $tempate_path = '', $default_path = '' ) {
    
    	$template_file = $this->wcpt_locate_template( $template_name, $tempate_path, $default_path );
    
    	if ( ! file_exists( $template_file ) ) :
    		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template_file ), '1.0.0' );
    		return;
    	endif;
      
      
      // Generate form
      if($template_name == "password-reset-form.php"){
        $this->generate_reset_form($template_file);
      } 
      elseif($template_name == "password-reset-complete.php"){
        $this->complete_form($template_file);
      } 
      else {
        $this->generate_reset_form($template_file);
      }
       
    }
    
    
    /**
     * Load reset form template (Pass vars to the html form)
     *
     * @param string $template_file The absolute path to the template to show
     *
     * @return
     */ 
    private function generate_reset_form($template_file){
      
      ob_start();
      
      $errors = isset( $_REQUEST['errors'] ) ? $_REQUEST['errors'] : array() ;
    	$url = isset( $_REQUEST['reset_url'] ) ? $_REQUEST['reset_url'] : '' ;
    	$email_confirmed = isset( $_POST['email_confirmed'] ) ? intval( $_POST['email_confirmed'] ) : false ;
	
      $min_length = 8;
      $reset_text = __( 'Please enter a new password.', $this->domain );
      $form_title= "Reset password";
      $button_text = 'Send';
      $hide_form = false;
      
      $key = sanitize_text_field( $_GET['key'] );
  		$login = sanitize_text_field( $_GET['login'] );
  		$user = check_password_reset_key( $key, $login );

      $html = ob_get_contents();
    	ob_end_clean();
    	
    	// If checks to determine which template/form to show the user
      if ( ! $email_confirmed &&  ( isset( $_GET['act'] ) && $_GET['act'] == 'reset' ) ) {
                
    		if ( is_wp_error( $user ) ) {
          
          $hide_form = true;
          
    			if ( $user->get_error_code() === 'expired_key' ) {
    
    				$errors['expired_key'] = __( 'That key has expired. Please reset your password again.', $this->domain );
    
    			} else {
    
    				$code = $user->get_error_code();
    				if ( empty( $code ) ) {
    					$code = '00';
    				}
    				$errors['invalid_key'] = __( 'That key is no longer valid. Please reset your password again. Code: ' . $code, $this->domain );
    			}
    			
    		} 
    		
    		require($template_file);
    		
    	}
    	
    	return $html;
    }
    
    
    /**
     * Load the complete form template (Pass vars to the html form)
     *
     * @param string $template_file The absolute path to the template to show
     *
     * @return
     */ 
    private function complete_form($template_file){
      
      ob_start();
      $form_title = "Complete form";
      $html = ob_get_contents();
    	ob_end_clean();
    	
    	require($template_file);

    	return $html;
    }

    
    /**
     * Post request from frontend form (Action)
     *
     * @return void
     */
    public function reset_post_request() {
      
    	// Bail if not a POST action
    	if ( ! ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] )))
    		return;
    
    	// Bail if no action
    	if ( empty( $_POST['reset_post_action'] ) )
    		return;
    
    	$this->reset_post_request_handler($_POST['reset_post_action']);
    }
    
    
    /**
     * Post request Logic
     *
     * @param string $action
     *
     * @return void
     */
    private function reset_post_request_handler( $action = '' ) {

    	// Bail if action is not som_reset_pass
    	if ( ! ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] )))
    		return;
      
      $errors = array();
      $success = null;

    	// Check the nonce
      $result = isset( $_REQUEST['somfrp_nonce'] ) ? wp_verify_nonce( $_REQUEST['somfrp_nonce'], $action ) : false;
      // Nonce check failed
      if ( empty( $result ) || empty( $action ) ) {
        $result = false;
	    }
	    
	    if(!$result){
  	    $errors['nonce_error'] = __( 'Something went wrong with that!', $this->domain );
	    }
	    
    	$user_pass = trim( $_POST['som_new_user_pass'] );
    	$user_pass_repeat = trim( $_POST['som_new_user_pass_again'] );
    
    	if ( empty( $user_pass ) || empty( $user_pass_repeat ) ) {
    		$errors['no_password'] = __( 'Please enter a new password.', $this->domain );
    		$_REQUEST['errors'] = $errors;
    		return;
    	} elseif ( $user_pass !== $user_pass_repeat ) {
    		$errors['password_mismatch'] = __( 'The passwords don\'t match.', $this->domain );
    		$_REQUEST['errors'] = $errors;
    		return;
    	}
    
    	//list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
    	//$rp_cookie = 'wp-resetpass-' . COOKIEHASH;
    
    	$key = sanitize_text_field( $_GET['key'] );
    	$login = sanitize_text_field( $_GET['login'] );
    	//$login = sanitize_text_field( $_GET['login'] ); // This is the user ID number
    
    	if ( empty( $key ) || empty( $login ) ) {
    		$errors['key_login'] = __( 'The reset link is not valid.', $this->domain );
    		$_REQUEST['errors'] = $errors;
    		// return;
    	}
    
    	$user = check_password_reset_key( $key, $login );
    
    	if ( is_wp_error( $user ) ) {
    		if ( $user->get_error_code() === 'expired_key' ) {
    			$errors['expired_key'] = __( 'Sorry, that key has expired. Please reset your password again.', $this->domain );
    		} else {
    			$errors['invalid_key'] = __( 'Sorry, that key does not appear to be valid. Please reset your password again.', $this->domain );
    		}
    	}
    
    	if ( ! empty( $errors ) ) {
    		$_REQUEST['errors'] = $errors;
    		return;
    	}
    	
    	reset_password( $user, $user_pass );
    	
	    wp_redirect( home_url( '/' ) . $this->complete_page );
	    exit;

    }

}
