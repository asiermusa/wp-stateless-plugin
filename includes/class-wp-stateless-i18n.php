<?php

namespace WP_Stateless\i18n;

/**
 * The internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link  https://github.com/asiermusa
 * @since 1.0.0
 */
class WP_Stateless_i18n
{
    /**
     * The domain especified for the plugin.
     *
     * @since    1.0.0
     *
     * @var string The domain used for this plugin.
     */
    private $domain;
    
    
    public function __construct()
    {
      // Load plugin text domain
      add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since  1.0.0
     *
     * @return void
     */
    public function load_plugin_textdomain()
    {      
      $locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );
      load_textdomain( $this->domain, dirname(dirname(plugin_basename(__FILE__))).'/languages/stateless-system-' . $locale . '.mo' );
      load_plugin_textdomain($this->domain, false, dirname(dirname(plugin_basename(__FILE__))).'/languages/');
    }

    /**
     * Set the domain equal to that of the specified domain.
     *
     * @since    1.0.0
     *
     * @param string $domain - The domain that represents the locale of this plugin.
     * @return  $domain (string)
     */
    public function set_domain($domain)
    {
        $this->domain = $domain;
    }
}
