<?php

namespace WP_Stateless\API\Realtime;

// Import WP_Error class
use WP_Error;
use WP_REST_Response;
use \Predis;

Predis\Autoloader::register();

/**
 * Reset password functionality from the frontend, without
 * use WordPress secreens
 *
 * @link  https://github.com/asiermusa
 * @since 1.0.0
 */
class Realtime_Class
{
    
    /**
     * The domain especified for the plugin.
     *
     * @since    1.0.0
     *
     * @var string The domain used for this plugin.
     */
    private static $domain;
    
    /**
     * The IP of Redis server
     *
     * @since    1.0.0
     *
     * @var string The IP
     */
    private static $redis_ip = REDIS_IP;
    
    /**
     * The password of Redis server
     *
     * @since    1.0.0
     *
     * @var string The IP
     */
    private static $redis_pwd = REDIS_PWD;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name The name of the plugin.
     */
    public function __construct($plugin_name)
    {
      self::$domain = $plugin_name;
    }
    
    /**
     * Send data to node.js server across Redis
     *
     * @param object $request message inputs (uid, text...)
     *
     * @return array Server response
     */
    public static function pusher($request, $token){
      
      $error = new WP_Error();

      $message = array(
      "uid" => $request->get_param('uid'),
      "txt" => $request->get_param('txt')
      );
            
      //$redis = new Predis\Client();
    	$redis = new Predis\Client(array(
        'scheme'   => 'tcp',
        'host'     => self::$redis_ip,
        'port'     => 6379,
        'password' => self::$redis_pwd,
        //'read_write_timeout' => 60
      ));
    	        
    	$redis->publish('canal', json_encode($message)); // send message to channel 1.  
    	
    	$response['code'] = 200;
      $response['message'] = __("Message sent to node server", self::$domain);
      return new WP_REST_Response($response, 200);
      
    }
}
