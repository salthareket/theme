<?php
/**
 * customShortcodes — Shortcode registry + TinyMCE UI builder.
 *
 * Class adı backward compat için değiştirilmedi (tinymce plugin bağımlı).
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   $sc = customShortcodes::getInstance();
 *   $sc->add([[ 'shortcode' => 'my_sc', 'name' => 'My SC', 'callback' => 'my_fn',
 *       'atts' => [
 *           'title'     => ['label' => 'Title', 'ui' => 'text', 'value' => ''],
 *           'post_type' => ['label' => 'Type', 'ui' => 'select', 'value' => [], 'func' => 'post_types'],
 *           'style'     => ['label' => 'Style', 'ui' => 'radio', 'value' => ['*a' => 'A', 'b' => 'B']],
 *       ],
 *   ]]);
 *
 * Examples:
 *   $shortcodes->get_shortcodes();          // select dropdown HTML
 *   $shortcodes->get_fields('my_sc');        // form fields HTML
 *   $shortcodes->get_all();                  // tum kayitli shortcode array
 *   // UI types: text|email|number|textarea|select|checkbox|radio|note|seperator
 *   // func options: post_types|user_roles|image_sizes|animations_in|animations_out|cf7_forms
 *
 * @package SaltHareket
 * @since   1.0.0
 */

class customShortcodes {

    private array $shortcodes = [];
    private static ?self $instance = null;
    public string $js = '';

    private const ANIMATIONS_IN = [
        'flash','bounce','shake','tada','swing','wobble','pulse','flip',
        'flipInX','flipInY','fadeIn','fadeInUp','fadeInDown','fadeInLeft','fadeInRight',
        'fadeInUpBig','fadeInDownBig','fadeInLeftBig','fadeInRightBig',
        'bounceIn','bounceInDown','bounceInUp','bounceInLeft','bounceInRight',
        'rotateIn','rotateInDownLeft','rotateInDownRight','rotateInUpLeft','rotateInUpRight','rollIn',
    ];

    private const ANIMATIONS_OUT = [
        'flash','bounce','shake','tada','swing','wobble','pulse','flip',
        'flipOutX','flipOutY','fadeOut','fadeOutUp','fadeOutDown','fadeOutLeft','fadeOutRight',
        'fadeOutUpBig','fadeOutDownBig','fadeOutLeftBig','fadeOutRightBig',
        'bounceOut','bounceOutDown','bounceOutUp','bounceOutLeft','bounceOutRight',
        'rotateOut','rotateOutDownLeft','rotateOutDownRight','rotateOutUpLeft','rotateOutUpRight',
        'hinge','rollOut',
    ];

    private const BOOTSTRAP_CLASSES = [
        'text' => 'form-control', 'email' => 'form-control', 'number' => 'form-control',
        'textarea' => 'form-control', 'select' => 'form-select',
        'checkbox' => 'form-check-input', 'radio' => 'form-check-input',
        'note' => 'alert alert-info', 'seperator' => 'my-5',
    ];

    public static function getInstance(): self {
        self::$instance ??= new self();
        return self::$instance;
    }

    public function add( array $shortcode ): void {
        foreach ( $shortcode as $sc ) {
            if ( empty( $sc['shortcode'] ) || empty( $sc['atts'] ) ) continue;
            if ( ! isset( $sc['callback'] ) || ! is_callable( $sc['callback'] ) ) continue;
            $this->shortcodes[ $sc['shortcode'] ] = $sc;
            $cb = $sc['callback'];
            $defs = $sc['atts'];
            add_shortcode( $sc['shortcode'], static function ( $atts, $content = null ) use ( $cb, $defs ) {
                $atts = shortcode_atts( array_map( fn( $v ) => is_array( $v ) ? ( $v['value'] ?? '' ) : $v, $defs ), $atts );
                return call_user_func( $cb, $atts, $content );
            } );
        }
    }

    public function get_all(): array { return $this->shortcodes; }

    // =========================================================================
    // DATA HELPERS — TinyMCE popup select options
    // =========================================================================

    public function user_roles(): array {
        global $wp_roles;
        $out = [];
        foreach ( $wp_roles->roles as $key => $role ) { $out[ $key ] = $role['name']; }
        return $out;
    }

    public function image_sizes(): array {
        global $_wp_additional_image_sizes;
        $out = [];
        foreach ( get_intermediate_image_sizes() as $size ) {
            if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
                $w = $_wp_additional_image_sizes[ $size ]['width'];
                $h = $_wp_additional_image_sizes[ $size ]['height'];
                $out[ $size ] = "{$size} ({$w}x{$h})";
            } else {
                $out[ $size ] = $size;
            }
        }
        return $out;
    }

    public function post_types(): array {
        $out = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
            $out[ $pt->name ] = $pt->labels->singular_name;
        }
        return $out;
    }

    public function animations_in(): array {
        return array_combine( self::ANIMATIONS_IN, self::ANIMATIONS_IN );
    }

    public function animations_out(): array {
        return array_combine( self::ANIMATIONS_OUT, self::ANIMATIONS_OUT );
    }

    public function cf7_forms(): array {
        $cached = get_transient( 'sh_cf7_forms_list' );
        if ( is_array( $cached ) ) return $cached;

        $forms = get_posts( [ 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
        $out = [];
        foreach ( $forms as $form ) {
            $hash = get_post_meta( $form->ID, '_hash', true );
            if ( ! empty( $hash ) ) {
                $out[ substr( $hash, 0, 7 ) ] = get_the_title( $form->ID );
            }
        }
        set_transient( 'sh_cf7_forms_list', $out, HOUR_IN_SECONDS );
        return $out;
    }


    // =========================================================================
    // FIELD BUILDER — TinyMCE popup form
    // =========================================================================

    public function get_fields( string $shortcode ): string {
        if ( ! isset( $this->shortcodes[ $shortcode ] ) ) return '';

        $atts   = $this->shortcodes[ $shortcode ]['atts'];
        $output = '';
        $js     = '';

        foreach ( $atts as $key => $value ) {
            $type    = $value['ui'] ?? 'text';
            $label   = $value['label'] ?? '';
            $options = $value['value'] ?? [];
            $func    = $value['func'] ?? '';
            $vis     = $value['visibility'] ?? [];
            $class   = self::BOOTSTRAP_CLASSES[ $type ] ?? 'form-control';

            if ( $func !== '' && method_exists( $this, $func ) ) {
                $options = array_merge( is_array( $options ) ? $options : [], $this->$func() );
            }

            $output .= match ( $type ) {
                'text', 'email', 'number' => $this->render_input( $type, $key, $label, $class, $options ),
                'textarea'  => $this->render_textarea( $key, $label, $class, $options ),
                'select'    => $this->render_select( $key, $label, $options, $class ),
                'checkbox'  => $this->render_checkbox( $key, $label, $options, $class ),
                'radio'     => $this->render_radio( $key, $label, $options, $class ),
                'note'      => $this->render_note( $label, $options, $class ),
                'seperator' => "<hr class='" . esc_attr( $class ) . "'/>",
                default     => '',
            };

            if ( ! empty( $vis ) ) {
                $js .= $this->build_visibility_js( $key, $vis );
            }
        }

        return $output . $js;
    }

    public function get_shortcodes(): string {
        $opts = '';
        foreach ( $this->shortcodes as $code => $sc ) {
            $label = esc_html( $sc['name'] ?? $code );
            $opts .= "<option value='" . esc_attr( $code ) . "'>{$label}</option>";
        }
        return "<div class='mb-3'><label for='shortcodes' class='form-label'>Shortcodes</label>"
             . "<select class='form-select' name='shortcodes' id='shortcodes'>{$opts}</select></div>"
             . "<div class='mb-3'><div class='form-check ps-0'>"
             . "<input type='checkbox' id='render_shortcode' name='render_shortcode' value='true'>"
             . "<label class='form-check-label ps-2' for='render_shortcode'>Render Shortcode</label>"
             . "</div></div>";
    }

    // =========================================================================
    // PRIVATE — HTML renderers (XSS-safe)
    // =========================================================================

    private function render_input( string $type, string $name, string $label, string $class, $value ): string {
        $n = esc_attr( $name );
        $v = esc_attr( is_array( $value ) ? '' : $value );
        return "<div class='form-group mb-3'><label for='{$n}' class='form-label'>" . esc_html( $label ) . "</label>"
             . "<input type='" . esc_attr( $type ) . "' class='" . esc_attr( $class ) . "' id='{$n}' name='{$n}' value='{$v}'></div>";
    }

    private function render_textarea( string $name, string $label, string $class, $value ): string {
        $n = esc_attr( $name );
        $v = esc_html( is_array( $value ) ? '' : $value );
        return "<div class='form-group mb-3'><label for='{$n}' class='form-label'>" . esc_html( $label ) . "</label>"
             . "<textarea class='" . esc_attr( $class ) . "' id='{$n}' name='{$n}'>{$v}</textarea></div>";
    }

    private function render_select( string $name, string $label, array $options, string $class ): string {
        $n = esc_attr( $name );
        $opts = '';
        foreach ( $options as $val => $text ) {
            $selected = '';
            if ( str_contains( (string) $val, '*' ) ) {
                $selected = ' selected';
                $val = str_replace( '*', '', $val );
            }
            $opts .= "<option value='" . esc_attr( $val ) . "'{$selected}>" . esc_html( $text ) . "</option>";
        }
        return "<div class='form-group mb-3'><label for='{$n}' class='form-label'>" . esc_html( $label ) . "</label>"
             . "<select class='" . esc_attr( $class ) . "' id='{$n}' name='{$n}'>{$opts}</select></div>";
    }

    private function render_checkbox( string $name, string $label, array $options, string $class ): string {
        $n = esc_attr( $name );
        $multiple = count( $options ) > 1 ? '[]' : '';
        $html = '';
        foreach ( $options as $val => $text ) {
            $checked = '';
            if ( str_contains( (string) $val, '*' ) ) {
                $checked = ' checked';
                $val = str_replace( '*', '', $val );
            }
            $v  = esc_attr( $val );
            $id = esc_attr( $name . '-' . $val );
            $html .= "<div class='form-check'>"
                    . "<input class='" . esc_attr( $class ) . "' type='checkbox' id='{$id}' name='{$n}{$multiple}' value='{$v}'{$checked}>"
                    . "<label class='form-check-label' for='{$id}'>" . esc_html( $text ) . "</label></div>";
        }
        return "<div class='form-group mb-3'><label class='form-label'>" . esc_html( $label ) . "</label>{$html}</div>";
    }

    private function render_radio( string $name, string $label, array $options, string $class ): string {
        $n = esc_attr( $name );
        $html = '';
        foreach ( $options as $val => $text ) {
            $checked = '';
            if ( str_contains( (string) $val, '*' ) ) {
                $checked = ' checked';
                $val = str_replace( '*', '', $val );
            }
            $v  = esc_attr( $val );
            $id = esc_attr( $name . '-' . $val );
            $html .= "<div class='form-check'>"
                    . "<input class='" . esc_attr( $class ) . "' type='radio' id='{$id}' name='{$n}' value='{$v}'{$checked}>"
                    . "<label class='form-check-label' for='{$id}'>" . esc_html( $text ) . "</label></div>";
        }
        return "<div class='form-group mb-3'><label class='form-label'>" . esc_html( $label ) . "</label>{$html}</div>";
    }

    private function render_note( string $label, $content, string $class ): string {
        return "<div class='" . esc_attr( $class ) . " mb-3'><h3 class='mb-2'>" . esc_html( $label ) . "</h3>"
             . wp_kses_post( is_array( $content ) ? '' : $content ) . "</div>";
    }


    // =========================================================================
    // PRIVATE — Visibility JS (unified)
    // =========================================================================

    private function build_visibility_js( string $elementId, array $visibility ): string {
        $blocks = [];

        foreach ( [ 'show' => [ '&&', 'block', 'none' ], 'hide' => [ '&&', 'none', 'block' ], 'show_or' => [ '||', 'block', 'none' ] ] as $key => $cfg ) {
            if ( ! isset( $visibility[ $key ] ) ) continue;

            [ $joiner, $match_display, $no_match_display ] = $cfg;
            $conditions = [];
            $dep_ids    = [];

            foreach ( $visibility[ $key ] as $depId => $cond ) {
                $op  = $cond['compare'] ?? '==';
                $val = esc_js( $cond['value'] ?? '' );
                $dep_ids[] = "'" . esc_js( $depId ) . "': document.getElementById('" . esc_js( $depId ) . "')";
                $conditions[] = "d['" . esc_js( $depId ) . "'] && ((d['" . esc_js( $depId ) . "'].type==='checkbox' && d['" . esc_js( $depId ) . "'].checked && d['" . esc_js( $depId ) . "'].value {$op} '{$val}') || (d['" . esc_js( $depId ) . "'].type!=='checkbox' && d['" . esc_js( $depId ) . "'].value {$op} '{$val}'))";
            }

            $cond_js = implode( " {$joiner} ", $conditions );
            $deps_js = implode( ",\n", $dep_ids );
            $eid     = esc_js( $elementId );

            $blocks[] = "(function(){var el=document.querySelector('#{$eid}');if(!el)return;el=el.closest('.form-group')||el;var d={{$deps_js}};function c(){el.style.display=({$cond_js})?'{$match_display}':'{$no_match_display}';}Object.values(d).forEach(function(e){if(e)e.addEventListener('change',c);});c();})();";
        }

        if ( empty( $blocks ) ) return '';

        return "<script>document.addEventListener('DOMContentLoaded',function(){" . implode( '', $blocks ) . "});</script>";
    }
}
