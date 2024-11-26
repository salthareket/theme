<?php

/**
* Extends WP_Scripts class to filter inline script tags added via wp_localize_script().
*/
class WP_Filterable_Scripts extends WP_Scripts {

    private $type_attr;

    /**
    * Executes the parent class constructor and initialization, then copies in the 
    * pre-existing $wp_scripts contents
    */
    public function __construct() {
        parent::__construct();

        if (
            function_exists( 'is_admin' ) && ! is_admin()
            &&
            function_exists( 'current_theme_supports' ) && ! current_theme_supports( 'html5', 'script' )
        ) {
            $this->type_attr = " type='text/javascript'";
        }

        /**
        * Copy the contents of existing $wp_scripts into the new one.
        * This is needed for numerous plug-ins that do not play nice.
        *
        * https://wordpress.stackexchange.com/a/284495/198117
        */
        if ( $GLOBALS['wp_scripts'] instanceof WP_Scripts ) {
            $missing_scripts = array_diff_key( $GLOBALS['wp_scripts']->registered, $this->registered );
            foreach ( $missing_scripts as $mscript ) {
                $this->registered[ $mscript->handle ] = $mscript;
            }
        }
    }

    /**
     * Adapted from wp-includes/class.wp-scripts.php and added the
     * filter `wp_filterable_script_extra_tag`
     *
     * @param string $handle
     * @param bool $echo
     *
     * @return bool|mixed|string|void
     */
    public function print_extra_script( $handle, $echo = true ) {
        $output = $this->get_data( $handle, 'data' );
        if ( ! $output ) {
            return;
        }

        if ( ! $echo ) {
            return $output;
        }

        $tag = sprintf( "<script%s id='%s-js-extra'>\n", $this->type_attr, esc_attr( $handle ) );

        /**
        * Filters the entire inline script tag.
        *
        * @param string $tag    <script type="text/javascript" id="plug-js-extra">...</script>
        * @param string $handle Script handle.
        */
        $tag = apply_filters( 'wp_filterable_script_extra_tag', $tag, $handle );

        // CDATA is not needed for HTML 5.
        if ( $this->type_attr ) {
            $tag .= "/* <![CDATA[ */\n";
        }

        $output = apply_filters( 'wp_filterable_script_extra_body', $output, $handle );

        $tag .= "$output\n";

        if ( $this->type_attr ) {
            $tag .= "/* ]]> */\n";
        }

        $tag .= "</script>\n";

        echo $tag;

        return true;
    }
}

add_action( 'init', function () {
    $fscripts              = new WP_Filterable_Scripts;
    $GLOBALS['wp_scripts'] = $fscripts;
}, 1000 );

function add_cfasync( $tag, $handle ) {
    $attr    = " nowprocket ";
    $filters = array(
        '/^site_config_vars.*$/',
    );

    if ( ! is_admin() ) {
        foreach ( $filters as $exclude_regex ) {
            if ( preg_match( $exclude_regex, $handle ) != false ) {
                $tag = str_replace( '<script ', '<script ' . $attr, $tag );
            }
        }
    }
    return $tag;
}
function add_cfasync_body( $output, $handle ) {
    $filters = array(
        '/^site_config_vars.*$/',
    );

    if ( ! is_admin() ) {
        foreach ( $filters as $exclude_regex ) {
            if ( preg_match( $exclude_regex, $handle ) != false ) {
                $output = str_replace("window.addEventListener('DOMContentLoaded', function() {", "", $output);
                $output = str_replace("});</script>", "</script>", $output);
                echo("\n\r");
                print_r($output);
                echo("\n\r");
            }
        }
    }
    return $output;
}
add_filter( 'wp_filterable_script_extra_tag', 'add_cfasync', 0, 2 );
add_filter( 'wp_filterable_script_extra_body', 'add_cfasync_body', 0, 2 );