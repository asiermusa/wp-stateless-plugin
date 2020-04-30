<?php

namespace WP_Stateless;

/**
 * The file that defines the core plugin class.
 *
 * @link       https://github.com/asiermusa
 * @since      1.0.0
 */
class WP_Stateless_Class
{

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     *
     * @var string The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
      $this->plugin_name = PLUGIN_NAME;
      $this->version = PLUGIN_VERSION;

      $this->load_dependencies();
      $this->set_locale();
      
      // Activation/deactivation hooks
      register_activation_hook( STATELESS_SYSTEM__FILE__, array( $this, 'activate' ) );
      register_deactivation_hook( STATELESS_SYSTEM__FILE__, array( $this, 'deactivate' ) );

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     *
     * @return void
     */
    private function load_dependencies()
    {

      /**
       * Load dependecies managed by composer.
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'includes/vendor/autoload.php';

      /**
       * The class responsible for defining internationalization functionality
       * of the plugin.
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-stateless-i18n.php';

      /**
       * The class responsible for defining all actions that occur in the public-facing
       * side of the site.
       */
      require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-stateless-public.php';

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     *
     * @return void
     */
    private function set_locale()
    {
      $plugin_i18n = new i18n\WP_Stateless_i18n();
      $plugin_i18n->set_domain($this->get_plugin_name());
    }
    

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     *
     * @return void
     */
    public function start()
    {
      new API\WP_Stateless_Public(
        $this->plugin_name,
        $this->version
      );
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     *
     * @return string The name of the plugin.
     */
    public function get_plugin_name()
    {
      return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     *
     * @return string The version number of the plugin.
     */
    public function get_version()
    {
      return $this->version;
    }
    
    /**
  	 * Fired for each blog when the plugin is activated.
  	 *
  	 * @since    1.0.0
  	 */
  	public function activate() {
    	if ( false == get_option( 'stateless-system-activation-message' ) ) {
			  add_option( 'stateless-system-activation-message', true );
		  }
  	}
  
  	/**
  	 * Fired for each blog when the plugin is deactivated.
  	 *
  	 * @since    1.0.0
  	 */
  	public function deactivate() {
  		delete_option( 'stateless-system-activation-message' );
  	}
    
}
