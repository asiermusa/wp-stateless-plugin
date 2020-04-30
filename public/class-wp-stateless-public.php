<?php

namespace WP_Stateless\API;

/** Require the JWT library & Authy/Twilio helpers */
use Firebase\JWT\JWT;
use Authy\AuthyApi;
use Google_Client;
use Abraham\TwitterOAuth\TwitterOAuth;

/** Import WP_Error and WP_REST_Response classes */
use WP_Error;
use WP_REST_Response;
use Exception;

/**
 * The public-facing functionality of the plugin.
 *
 * @link  https://github.com/asiermusa
 * @since 1.0.0
 */
class WP_Stateless_Public
{
    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The string used to uniquely identify this plugin.
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     *
     * @var string The current version of the plugin.
     */
    private $version;
    
    /**
     * The domain especified for the plugin.
     *
     * @since    1.0.0
     *
     * @var string The domain used for this plugin.
     */
    private $domain;
    
    /**
     * The namespace to add to the api calls.
     *
     * @since    1.0.0
     *
     * @var string The namespace to add to the api calls.
     */
    private $namespace;

    /**
     * The Authy API key.
     *
     * @since    1.0.0
     *
     * @var string The Authy API key.
     */
    private $authy_secret;
    
    /**
     * Store errors to display if the JWT is wrong
     *
     * @var WP_Error
     */
    private $jwt_error = null;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name - The name of the plugin.
     * @param string $version - The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
      $this->plugin_name = $plugin_name;
      $this->version = $version;
      $this->domain = $plugin_name;
      
      $this->namespace = $this->plugin_name . '/v' . intval($this->version);
      
      $this->authy_secret = AUTHY_SECRET;
      
      // Include require parts
      $this->includes();
      
      // Main JWT 
      add_action('rest_api_init', array( $this, 'add_api_routes') );
      add_filter('rest_api_init', array( $this, 'add_cors_support') );
      add_filter('rest_pre_dispatch', array( $this, 'rest_pre_dispatch'), 10, 2);
      // Not necessary to determine the user
      //add_filter('determine_current_user', array( $this, 'determine_current_user'), 10); 
      
      // Login attempts
      add_filter( 'authenticate', array($this, 'check_attempted_login'), 30, 3 );
      add_action( 'wp_login_failed', array($this, 'login_failed'), 10, 1 ); 
    }
    
    
    /**
     * Include the following files that make up the plugin
     *
     * @since	1.0.0
     *
     * @param	 void
     * @return object - Instances
     */
    private function includes()
    {
      
      /**
       * The class responsible for adding admin section
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'public/includes/class-admin.php';
      new Admin\Admin_Class($this->plugin_name);
      
      /**
       * The class responsible for admin password reset options
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'public/includes/class-password-reset.php';
      new Password\Password_Reset_Class($this->plugin_name);
      
      /**
       * The class responsible for realtime features
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'public/includes/class-realtime.php';
      new Realtime\Realtime_Class($this->plugin_name);
      
    }

      
    /**
     * Endpoints of the API
     *
     * @since	1.0.0
     *
     * @param	void
     */
    public function add_api_routes()
    {

      // Register
      register_rest_route($this->namespace, 'register', array(
  			'methods' => 'POST',
  			'callback' => array($this, 'register_user'),
  		));
  		// Social register
      register_rest_route($this->namespace, 'social-register', array(
  			'methods' => 'POST',
  			'callback' => array($this, 'social_register_user'),
  		));
  		register_rest_route($this->namespace, 'lost-password', array(
  			'methods' => 'POST',
  			'callback' => array($this, 'lost_password'),
  		));
  		register_rest_route($this->namespace, 'reset-password', array(
  			'methods' => 'POST',
  			'callback' => array($this, 'reset_password'),
  		));

      // Login (Get token)
      /*
      register_rest_route($this->namespace, 'users/token', array(
          'methods' => 'POST',
          'callback' => array($this, 'generate_token'),
      ));
      */

      register_rest_route($this->namespace, 'token/validate', array(
          'methods' => 'POST',
          'callback' => array($this, 'validate_token'),
      ));

      register_rest_route($this->namespace, 'send-sms', array(
          'methods' => 'POST',
          'callback' => array($this, 'send_sms'),
      ));
      
      register_rest_route($this->namespace, 'verify-code', array(
          'methods' => 'POST',
          'callback' => array($this, 'verify_sms_token'),
      ));
      
      // Pusher
      register_rest_route($this->namespace, 'pusher', array(
          'methods' => 'POST',
          'callback' => array($this, 'pusher'),
      ));
      
      register_rest_route($this->namespace, 'hi', array(
  			'methods' => 'GET',
  			'callback' => array($this, 'hi'),
  		));

    }
    
    public function hi($request){
      return 'HII';
    }
    
    /**
     * Include the following files that make up the plugin
     *
     * @since	1.0.0
     *
     * @param	  array $request
     * @return	array - Response of the socket server
     */
    public function pusher($request){
      
      // Login Middleware 
      $token = $this->validate_token(false);
      
      if (is_wp_error($token)) {
        return $token;
      }

      return Realtime\Realtime_Class::pusher($request, $token);
    }
    
    
    /**
     * Verify if user's phone exist
     *
     * @since	1.0.0
     *
     * @param   string $email
     * @param   string $phone_number
     * @param   string $country_code default 34
     * @return	(bool|int)
     */
    private function create_authy_user($email, $phone_number, $country_code = '34'){

      $country_code = '34';
      $user_email = sanitize_text_field($email);
      $user_phone_number = sanitize_text_field($phone_number);

      // Compare if phone number exist (only for new users)
      $args = array(
        'meta_query' => array(
          array(
            'key' => 'phone_number',
            'value' => $user_phone_number,
            'compare' => 'EXISTS',
          ),
        )
      );

      if(empty(get_users($args))){
        // Request Authy to save the user ID
        $authy_api = new AuthyApi($this->authy_secret);
        $authy_user = $authy_api->registerUser($user_email, $user_phone_number, $country_code);
        $user_authy_id = $authy_user->id();

        // Validate Authy response
        if($user_authy_id){
          return $user_authy_id;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }

    /**
     * Register new user
     *
     * @since	1.0.0
     *
     * @param	  object $request - User data (username, email, password, first_name, last_name, phone_number)
     * @return	string - Response of the server
     */
  	public function register_user($request = null) {

  		$response = array();
  		$parameters = $request->get_json_params();
      
      //userdata
  		$username = sanitize_text_field($parameters['username']);
  		$email = sanitize_text_field($parameters['email']);
  		$password = sanitize_text_field($parameters['password']);
  		$first_name = sanitize_text_field($parameters['first_name']);
  		$last_name = sanitize_text_field($parameters['last_name']);
      $phone_number = sanitize_text_field($parameters['phone_number']);
      
  		//$role = sanitize_text_field($parameters['role']);
  		$error = new WP_Error();

  		if (empty($username)) {
  			$error->add(400, __("Username field 'username' is required.", $this->domain ), array('status' => 400));
  			return $error;
  		}
  		if (empty($email)) {
  			$error->add(401, __("Email field 'email' is required.", $this->domain ), array('status' => 400));
  			return $error;
  		}
  		if (empty($password)) {
  			$error->add(404, __("Password field 'password' is required.", $this->domain ), array('status' => 400));
  			return $error;
  		}

      // Phone verification (Authy)
      if (empty($phone_number)) {
  			$error->add(404, __("Phone number field 'phone_number' is required.", $this->domain ), array('status' => 400));
  			return $error;
  		} else {
        
        /**
         *
         * Create Authy user
         * We verified if username or email have been registered before create authy user 
         *
         */
        if (!username_exists($username) && email_exists($email) == false) {
          if(!$this->create_authy_user($email, $phone_number)){
            $error->add(406, __("Authy error (phone exists)", $this->domain ), array('status' => 400));
            return $error;
          } else {
            $user_authy_id = $this->create_authy_user($email, $phone_number);
          }
        }
      }

  		if (empty($role)) {
  			// WooCommerce specific code
  			if (class_exists('WooCommerce')) {
  				$role = 'customer';
  			} else {
  				$role = 'subscriber';
  			}
  		} else {
  			if ($GLOBALS['wp_roles']->is_role($role)) {
  				if ($role == 'administrator' || $role == 'Editor' || $role == 'Author') {
  					$error->add(406, __("Role field 'role' is not a permitted. Only 'contributor', 'subscriber' and your custom roles are allowed.", $this->domain ), array('status' => 400));
  					return $error;
  				} else {
  					// Silence is gold
  				}
  			} else {
  				$error->add(405, __("Role field 'role' is not a valid. Check your User Roles from Dashboard.", $this->domain ), array('status' => 400));
  				return $error;
  			}
  		}

  		$user_id = username_exists($username);
  		if (!$user_id && email_exists($email) == false) {
    		
    		$userdata = array(
          'user_login'  => $username,
          'user_email'  => $email,
          'user_pass'   => $password,
          'display_name'=> $username,
          'first_name'  => $first_name,
          'last_name'   => $last_name,
          'user_url'    => '',
        );
        
        $user_id 	= wp_insert_user($userdata);

  			if (!is_wp_error($user_id)) {
    			
  				// Get User Meta Data (Sensitive, Password included. DO NOT pass to front end.)
  				$user = get_user_by('id', $user_id);
  				$user->set_role($role);
  				do_action('wp_rest_user_create_user', $user); // Deprecated
  				do_action('wp_rest_user_user_register', $user, $parameters);

          // Update user meta
          update_user_meta($user->ID, 'phone_number', $phone_number, false);
          update_user_meta($user->ID, 'authy_id', $user_authy_id, false);

          // Get User Data (Non-Sensitive, Pass to front end.)
          $response['code'] = 200;
          $response['message'] = __("User '" . $username . "' Registration was Successful", $this->domain);

  			} else {
  				return $user_id;
  			}
  		} else if ($user_id) {
  			$error->add(406, __("Username already exists, please enter another username", $this->domain), array('status' => 400));
  			return $error;
  		} else {
  			$error->add(406, __("Email already exists, please try 'Reset Password'", $this->domain), array('status' => 400));
  			return $error;
  		}

  		return new WP_REST_Response($response, 200);
  	}
    
    
    
    /**
     * Register new social user
     *
     * @since	1.0.0
     *
     * @param	  string $request - token_id, phone
     * @return	string - Response of the server
     */
  	public function social_register_user($request = null) {

  		$response = array();
  		$token = $request->get_param('token');
  		$token_secret = $request->get_param('token_secret');
  		$phone_number = $request->get_param('phone_number');
  		$social = $request->get_param('social');
  		$error = new WP_Error();

  		if (empty($token)) {
  			$error->add(400, __("'id_token' is required.", $this->domain ), array('status' => 400));
  			return $error;
  		}
  		
  		if (empty($phone_number)) {
  			$error->add(400, __("'phone_number' is required to complete the registration.", $this->domain ), array('status' => 400));
  			return $error;
  		}
      
      if($social == 'google'){
        $client = new Google_Client(['client_id' => GOOGLE_CLIENT_ID]);  // Specify the CLIENT_ID of the app that accesses the backend
        $payload = $client->verifyIdToken($token);
      } elseif( $social == 'twitter'){
        // new TwitterOAuth instance to get email
        $twitterOAuth = new TwitterOAuth(
          TWITTER_API_KEY, 
          TWITTER_API_SECRET, 
          $token, 
          $token_secret
        );
        // Let's get the user's info with email
        $payload = $twitterOAuth->get('account/verify_credentials', ['include_entities' => 'false', 'include_email'=>'true', 'skip_status'=>'true',]);
      }
      
      /* 
       *
       * For debug purposes (Google)
       *
       * $idtoken_validation_result = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token);
       * $userinfo = json_decode($idtoken_validation_result['body'], true);
       *
      */      
      if(!empty($payload) ) {
        
        if($social == 'google'){
          $social_user_id         = $payload['sub'];
          $social_name            = $payload['name'];
          $social_first           = $payload['given_name'];
          $social_last            = $payload['family_name'];
          $social_picture         = $payload['picture'];
          $social_email           = $payload['email'];
          $social_verified_email  = $payload['email_verified'];
        } elseif( $social == 'twitter'){
          $social_user_id         = $payload->id;
          $social_name            = $payload->screen_name;
          $social_first           = $payload->name;
          $social_last            = '';
          $social_picture         = $payload->profile_image_url;
          $social_email           = $payload->email;
          $social_verified_email  = true;
        }
        

        // Compare if social user ID exist in DB
        $args = array(
          'meta_query' => array(
            array(
              'key' => $social . '-oauth-user',
              'value' => $social_user_id,
              'compare' => 'EXISTS',
            ),
          )
        );
                
        if( !empty(get_users($args))){
          $error->add(406, __($social . " social account already exists for this user. Please try with different one", $this->domain), array('status' => 400));
          return $error;
        }

        if (!$social_verified_email) {
          $error->add(406, __("Email needs to be verified on your Google Account", $this->domain), array('status' => 400));
          return $error;
        } 
          
        $check_email = get_user_by('email', $social_email);
        $check_login = get_user_by('login', $social_name);
        
        //if email no exist create new WP user
        if (! $check_email ) {
          
          /**
           *
           * Create Authy user
           * We verified if email have been registered before create authy user 
           *
           */
          if(!$this->create_authy_user($social_email, $phone_number)){
            $error->add(406, __("Authy error (phone exists)", $this->domain ), array('status' => 400));
            return $error;
          } else {
            $user_authy_id = $this->create_authy_user($social_email, $phone_number);
          }
          

          //create google user
          $random_password = wp_generate_password( 12, false );
          
          if ( $check_login ) {
            $social_name = $social_name . '_social_auth';
          }
          
          
          $userdata = array(
            'user_login'  => preg_replace('/[^A-Za-z0-9-]+/', '-', $social_name),
            'user_email'  => $social_email,
            'user_pass'   => $random_password,
            'display_name'=> $social_name,
            'first_name'  => $social_first,
            'last_name'   => $social_last,
            'user_url'    => '',
          );
          
          $user_id 	= wp_insert_user($userdata);

          // Update user meta
          add_user_meta( $user_id, $social . '-oauth-user', $social_user_id, true);
          add_user_meta( $user_id, $social . '-oauth-avatar', $social_picture, true);
          add_user_meta( $user_id, 'phone_number', $phone_number, false);
          add_user_meta( $user_id, 'authy_id', $user_authy_id, false);
          
          $user = get_user_by('id', $user_id);
          
        } else {
          
          //$email_array = explode('@', $user_email);
          if ( isset($check_email) ) {
            $user_id = $check_email->ID;
            add_user_meta( $user_id, $social . '-oauth-user', $social_user_id, true);
            add_user_meta( $user_id, $social . '-oauth-avatar', $social_picture, true);
          }
        }        
      } else {
        $error->add(406, __("Some error happened connecting with oauth google", $this->domain), array('status' => 400));
        return $error;
      }
      
      // Get User Data (Non-Sensitive, Pass to front end.)
      $response['code'] = 200;
      $response['message'] = __($social . ' social user have been created. Lets save more data (phone...)', $this->domain);

  		return new WP_REST_Response($response, 200);    
  	}
  	

    /**
     * Add CORs suppot to the request.
     *
     * @since	1.0.0
     *
     * @param   void
     * @return	void
     */
    public function add_cors_support()
    {
        $enable_cors = defined('JWT_AUTH_CORS_ENABLE') ? JWT_AUTH_CORS_ENABLE : false;
        if ($enable_cors) {
          $headers = apply_filters('jwt_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization');
          header(sprintf('Access-Control-Allow-Headers: %s', $headers));
        }
    }
    
    
    /**
     * Login. Get the user and password in the request body and send sms verification (OTP)
     * username and password
     *
     * @since	1.0.0
     *
     * @param  Object $request - Username and Password to auth the user
     * @return array - Server response
     */
    public function send_sms($request){

      $username = $request->get_param('username');
      $password = $request->get_param('password');
      $error = new WP_Error();

      /** Try to authenticate the user with the passed credentials*/
      $user = wp_authenticate($username, $password);
      
      /** Catch login attempts error */
      if (is_wp_error($this->jwt_error)) {
          return $this->jwt_error;
      }
      
      /** If the authentication fails return a error*/
      if (is_wp_error($user)) {
        $error->add(403, __("User login failed", $this->domain), array('status' => 400));
        return $error;
      }
      
      // Get user data (phone and authy id)
      $user_id = $user->data->ID;
      $user_info = get_userdata($user_id);
      $phone_number = $user_info->phone_number;
      $authy_id = $user_info->authy_id;
      
      // connect with Authy API
      $authy_api = new AuthyApi($this->authy_secret);
      $sms = $authy_api->requestSms($authy_id);

      if ($sms->ok()) {
        $response['code'] = 200;
        $response['data'] = array('id' => $user_id);
        $response['message'] = __("SMS sent to '" . $phone_number . "'", $this->domain);
      } else {
        $error->add(406, __("Something went wrong with Authy API", $this->domain), array('status' => 400));
        return $error;
      }
      
      return new WP_REST_Response($response, 200);
      
    }
    
    /**
     * A filter to control login attempts 
     *
     * @since	1.0.0
     *
     * @param   object $user
     * @param   string $username
     * @param   string $password
     * @return  array The user
     */
    public function check_attempted_login( $user, $username, $password ) {
      if ( get_transient( 'attempted_login' ) ) {
        $datas = get_transient( 'attempted_login' );

        if ( $datas['tried'] >= ATTEMPT_LIMIT ) {
            $until = get_option( '_transient_timeout_' . 'attempted_login' );
            $time = $this->time_to_go( $until );
            
            // Now we are ready to build our email
/*
            $admin_email = get_option( 'admin_email' );
            $to = 'asiermusa@gmail.com';
            $subject = "Many login attempts ['" . get_option( 'blogname' ) ."']";
            $body = '
              <p>Aupa</p>
              <p>In your <strong>' . get_option( 'blogname' ) . '</strong> web somebody did too many attempts to login.</p>
              <p>Username: <strong>' . $username . '</strong></p>
              <p>Install <strong>WordFence</strong> to control the site.</p>
              <p>Thanks</p>';
        
            $headers = array('Content-Type: text/html; charset=UTF-8');
            if (wp_mail($to, $subject, $body, $headers)) {
              error_log("email has been successfully sent to user whose email is " . $to);
            }else{
              error_log("email failed to sent to user whose email is " . $to);
            }
*/
            
            // API error
            $this->jwt_error = new WP_Error( 403, sprintf( __( 'You have reached authentication limit, you will be able to try again in %1$s.' ) , $time ), array('status' => 400) );

            // To active error on the frontend (Wordpress default screen)
            $error = new WP_Error();
            $error->add(406, __('You have reached authentication limit, you will be able to try again in ' . $time, $this->domain), array('status' => 400));
            return $error;

        }
      }
      return $user;
    }
    
    /**
     * Action to count how many tries to login
     *
     * @since	1.0.0
     *
     * @param   string $username
     * @return  void
     */
    public function login_failed( $username ) {
      if ( get_transient( 'attempted_login' ) ) {
          $datas = get_transient( 'attempted_login' );
          $datas['tried']++;
          $datas['login_username'] = $username;
          
          if ( $datas['tried'] <= ATTEMPT_LIMIT )
              set_transient( 'attempted_login', $datas , LOCKOUT_DURATION );
      } else {
          $datas = array(
              'tried'     => 1
          );
          set_transient( 'attempted_login', $datas , LOCKOUT_DURATION );
      }
    }
    
    
    /**
     * Humanize unix timestamp 
     *
     * @since	1.0.0
     *
     * @param   string $timestamp
     * @return  string Humanized date
     */
    private function time_to_go($timestamp){
      
      // converting the timestamp to php time
      $periods = array(
          "second",
          "minute",
          "hour",
          "day",
          "week",
          "month",
          "year"
      );
      $lengths = array(
          "60",
          "60",
          "24",
          "7",
          "4.35",
          "12"
      );
      $current_timestamp = time();
      $difference = abs($current_timestamp - $timestamp);
      for ($i = 0; $difference >= $lengths[$i] && $i < count($lengths) - 1; $i ++) {
        $difference /= $lengths[$i];
      }
      $difference = round($difference);
      if (isset($difference)) {
          if ($difference != 1)
            $periods[$i] .= "s";
            $output = "$difference $periods[$i]";
            return $output;
      }
    }

      
    /**
     * Get verification code (OTP)
     *
     * @since	1.0.0
     *
     * @param  object $request - sms token and user ID
     * @return array - Server response
     */
    public function verify_sms_token($request){
      
      $token = $request->get_param('token');
      $user_id = $request->get_param('user');
      $error = new WP_Error();
      
      if(!$token || !$user_id){
        $error->add(406, __("No data to verify", $this->domain), array('status' => 400));
        return $error;
      }
      
      // Get user data ()
      $user_info = get_userdata($user_id);
      $authy_id = $user_info->authy_id;
            
      // connect with Authy API
      if($authy_id && strlen($token) === 6){

        $authy_api = new AuthyApi($this->authy_secret);
        $verification = $authy_api->verifyToken($authy_id, $token);
        
        if ($verification->ok()) {
          // $response['code'] = 200;
          // $response['message'] = __("Token verified successfully", "wp-rest-user");
        } else {
          $error->add(406, __("Invalid token", $this->domain), array('status' => 400));
          return $error;
        }
      
      } else {
        $error->add(406, __("Token format error or invalid token", $this->domain), array('status' => 400));
        return $error;
      }
      
      // Send user ID to generate JWT
      return $this->generate_token($user_info);
      
    }


    /**
     * Get the user data and generate or reject to create a new token 
     *
     * @since	1.0.0
     *
     * @param   object $user
     * @return  string The token
     */
    private function generate_token($user)
    {
      $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
      $error = new WP_Error();
      
      /** First thing, check the secret key if not exist return a error*/
      if (!$secret_key) {
        $error->add(403, __("JWT is not configurated properly, please contact the admin", $this->domain), array('status' => 400));
        return $error;
      }
      
      /** Valid credentials, the user exists create the according Token */
      $issuedAt = time();
      $notBefore = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
      $expire = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

      $token = array(
        'iss' => get_bloginfo('url'),
        'iat' => $issuedAt,
        'nbf' => $notBefore,
        'exp' => $expire,
        'data' => array(
          'user' => array(
            'id' => $user->data->ID,
          ),
        ),
      );

      /** Let the user modify the token data before the sign. */
      $token = JWT::encode(apply_filters('jwt_auth_token_before_sign', $token, $user), $secret_key);

      /** The token is signed, now create the object with no sensible user data to the client*/
      $data = array(
        'token' => $token,
        'id' => $user->data->ID,
        'user_email' => $user->data->user_email,
        'user_nicename' => $user->data->user_nicename,
        'user_display_name' => $user->data->display_name,
      );

      /** Let the user modify the data before send it back */
      return apply_filters('jwt_auth_token_before_dispatch', $data, $user);
    }

    /**
     * This is our Middleware to try to authenticate the user according to the
     * token send.
     *
     * @since	1.0.0
     *
     * @param  (int|bool) $user Logged User ID
     * @return (int|bool)
     */
    public function determine_current_user($user)
    {
      /**
       * This hook only should run on the REST API requests to determine
       * if the user in the Token (if any) is valid, for any other
       * normal call ex. wp-admin/.* return the user.
       *
       * @since 1.0.0
       **/
      $rest_api_slug = rest_get_url_prefix();
      $valid_api_uri = strpos($_SERVER['REQUEST_URI'], $rest_api_slug);
      if (!$valid_api_uri) {
          return $user;
      }

      /*
       * if the request URI is for validate the token don't do anything,
       * this avoid double calls to the validate_token function.
       */
      $validate_uri = strpos($_SERVER['REQUEST_URI'], 'token/validate');
      if ($validate_uri > 0) {
          return $user;
      }

      $token = $this->validate_token(false);

      if (is_wp_error($token)) {
          if ($token->get_error_code() != 'jwt_auth_no_auth_header') {
              /** If there is a error, store it to show it after see rest_pre_dispatch */
              $this->jwt_error = $token;
              return $user;
          } else {
              return $user;
          }
      }
      /** Everything is ok, return the user ID stored in the token*/
      return $token->data->user->id;
    }

    /**
     * Main validation function, this function try to get the Autentication
     * headers and decoded.
     *
     * @since	1.0.0
     *
     * @param bool $output
     * @return WP_Error | Object | Array
     */
    public function validate_token($output = true)
    {
        /*
         * Looking for the HTTP_AUTHORIZATION header, if not present just
         * return the user.
         */
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
        $error = new WP_Error();
        
        /* Double check for different auth header string (server dependent) */
        if (!$auth) {
          $auth = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;
        }

        if (!$auth) {
            return new WP_Error(
                'jwt_auth_no_auth_header',
                'Authorization header not found.',
                array(
                    'status' => 403,
                )
            );
        }

        /*
         * The HTTP_AUTHORIZATION is present verify the format
         * if the format is wrong return the user.
         */
        list($token) = sscanf($auth, 'Bearer %s');
        if (!$token) {
          $error->add(403, __("Authorization header malformed.", $this->domain), array('status' => 400));
          return $error;
        }

        /** Get the Secret Key */
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        if (!$secret_key) {
          $error->add(403, __("JWT is not configurated properly, please contact the admin", $this->domain), array('status' => 400));
          return $error;
        }

        /** Try to decode the token */
        try {

            $token = JWT::decode($token, $secret_key, array('HS256'));
                        
            /** The Token is decoded now validate the iss */
            if ($token->iss != get_bloginfo('url')) {
              /** The iss do not match, return error */
              $error->add(403, __("The iss do not match with this server", $this->domain), array('status' => 400));
              return $error;
            }
            
            /** So far so good, validate the user id in the token */
            if (!isset($token->data->user->id)) {
              /** No user id in the token, abort!! */
              $error->add(403, __("User ID not found in the token", $this->domain), array('status' => 400));
              return $error;
            }
            
            /** Check if user id exist in our database */
            if (!get_user_by( 'ID', $token->data->user->id)) {
              /** No user id in the DB, abort!! */
              $error->add(403, __("User ID not exist", $this->domain), array('status' => 400));
              return $error;
            }
            
            return $token;
            
            /** Everything looks good return the decoded token if the $output is false */
            if (!$output) {
                return $token;
            }
            /** If the output is true return an answer to the request to show it */
            $response['code'] = 200;
            $response['message'] = __("JWT auth valid token", $this->domain);

            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            /** Something is wrong trying to decode the token, send back the error */
            $error->add(403, $e->getMessage(), array('status' => 400));
            return $error;
        }
    }
    
    
  /**
	 * Get the username or email in the request body and Send a Forgot Password email
	 *
   * @since	1.0.0
   *
	 * @param  object $request user info
	 * @return array - Sever response
	 */
	public function lost_password($request = null) {

		$response = array();
		$parameters = $request->get_json_params();
		$user_login = sanitize_text_field($parameters['user_login']);
		$error = new WP_Error();

		if (empty($user_login)) {
			$error->add(400, __("The field 'user_login' is required.", $this->domain), array('status' => 400));
			return $error;
		} else {
			$user_id = username_exists($user_login);
			if ($user_id == false) {
				$user_id = email_exists($user_login);
				if ($user_id == false) {
					$error->add(401, __("User '" . $user_login . "' not found.", $this->domain), array('status' => 401));
					return $error;
				}
			}
		}

		$user = null;
		$email = "";
		if (strpos($user_login, '@')) {
			$user = get_user_by('email', $user_login);
			$email = $user_login;
		} else {
			$user = get_user_by('login', $user_login);
			$email = $user->user_email;
		}
		
		$key = get_password_reset_key($user);
		$random_number = mt_rand(100000, 999999);
		$now = time();
    $timestamp = $now; // add minutes ... (minute 60)

		update_user_meta($user->ID, 'pr_code', $random_number, false);
		update_user_meta($user->ID, 'pr_code_exp', $timestamp, false);
		
    // Now we are ready to build our password reset email
    $to = $email;
    $subject = "Reset Password";
    $body = '
      <p>Aupa ' . $user_login . ',</p>
      <p>Thank you for joining our site. Your account is now active.</p>
      <p>This is the code to reset the password: <h3>' . $random_number. '</h3></p>
      <a href="' . site_url() . "/adibide-orrialdea?act=reset&key=$key&login=" . rawurlencode($user->user_login) . '">Egin klik hemen</a>';

              
    $headers = array('Content-Type: text/html; charset=UTF-8');
    if (wp_mail($to, $subject, $body, $headers)) {
      error_log("email has been successfully sent to user whose email is " . $email);
    }else{
      error_log("email failed to sent to user whose email is " . $email);
    }
    
    $response['code'] = 200;
    $response['message'] = __("Email was sent to '" . $email . "'", $this->domain);
    
    return new WP_REST_Response($response, 200);

  }
  
  /**
	 * Reset password (Not used | experimental)
	 *
   * @since	1.0.0
   *
	 * @param object $request
	 * @return
	 */
	public function reset_password($request = null) {

		$response = array();
		$parameters = $request->get_json_params();
		$token = sanitize_text_field($parameters['token']);
		$error = new WP_Error();

		if (empty($token)) {
			$error->add(400, __("The field 'token' is required.", $this->domain), array('status' => 400));
			return $error;
		}
		
		// Compare if token exist
    $args = array(
      'meta_query' => array(
        array(
          'key' => 'pr_code',
          'value' => $token,
          'compare' => 'EXISTS',
        ),
      )
    );

    if(!empty(get_users($args))){
      
      $user_id = get_users($args)[0]->data->ID;
      $user_info = get_userdata($user_id);
      $pr_code_exp = $user_info->pr_code_exp;
      
      // 30 minutes to change the pasword
      if( time() - ($pr_code_exp + 60 * 30) < 0 ) {
        $response['code'] = 200;
        $response['message'] = __("Password can be restored", $this->domain);
        delete_user_meta($user_id, 'pr_code');
        delete_user_meta($user_id, 'pr_code_exp');
      } else {
        $error->add(400, __("Password reset token expired", $this->domain), array('status' => 400));
        return $error;
      }
        
    } else {
      $error->add(400, __("The token is not valid", $this->domain), array('status' => 400));
			return $error;
    }

    return new WP_REST_Response($response, 200);

  }
  

  /**
   * Filter to hook the rest_pre_dispatch, if there is an error in the request
   * send it, if there is no error just continue with the current request.
   *
   * @param $request
   */
  public function rest_pre_dispatch($request)
  {
      if (is_wp_error($this->jwt_error)) {
          return $this->jwt_error;
      }
      return $request;
  }
}
