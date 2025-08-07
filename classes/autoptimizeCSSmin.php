<?php
/**
 * Thin wrapper around css minifiers to avoid rewriting a bunch of existing code.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCSSmin
{
    /**
     * Minifier instance.
     *
     * @var Autoptimize\tubalmartin\CssMin\Minifier|null
     */
    protected $minifier = null;

    /**
     * Construtor.
     *
     * @param bool $raise_limits Whether to raise memory limits or not. Default true.
     */
    public function __construct( $raise_limits = true )
    {
        $this->minifier = new Autoptimize\tubalmartin\CssMin\Minifier( $raise_limits );
    }

    /**
     * Runs the minifier on given string of $css.
     * Returns the minified css.
     *
     * @param string $css CSS to minify.
     *
     * @return string
     */
    public function run( $css )
    {
        // hide calc() if filter says yes (as YUI CSS compressor PHP port has problems retaining spaces around + and - operators).
        // regex see tests at https://regex101.com/r/ofGQG9/1
        if ( apply_filters( 'autoptimize_filter_css_hide_calc', true ) ) {
            $css = autoptimizeBase::replace_contents_with_marker_if_exists( 'CALC', 'calc(', '#(calc|min|max|clamp)\([^;}]*\)#m', $css );
        }

        // minify.
        $result = $this->minifier->run( $css );

        // restore calc() if filter says yes.
        if ( apply_filters( 'autoptimize_filter_css_hide_calc', true ) ) {
            $result = autoptimizeBase::restore_marked_content( 'CALC', $result );
        }

        return $result;
    }

    /**
     * Static helper.
     *
     * @param string $css CSS to minify.
     *
     * @return string
     */
    public static function minify( $css )
    {
        $minifier = new self();

        return $minifier->run( $css );
    }
}
