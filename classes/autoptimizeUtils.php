<?php
/**
 * General helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeUtils
{
    /**
     * Returns true when mbstring is available.
     *
     * @param bool|null $override Allows overriding the decision.
     *
     * @return bool
     */
    public static function mbstring_available( $override = null )
    {
        static $available = null;

        if ( null === $available ) {
            $available = \extension_loaded( 'mbstring' );
        }

        if ( null !== $override ) {
            $available = $override;
        }

        return $available;
    }

    /**
     * Returns true when iconv is available.
     *
     * @param bool|null $override Allows overriding the decision.
     *
     * @return bool
     */
    public static function iconv_available( $override = null )
    {
        static $available = null;

        if ( null === $available ) {
            $available = \extension_loaded( 'iconv' );
        }

        if ( null !== $override ) {
            $available = $override;
        }

        return $available;
    }

    /**
     * Multibyte-capable strpos() if support is available on the server.
     * If not, it falls back to using \strpos().
     *
     * @param string      $haystack Haystack.
     * @param string      $needle   Needle.
     * @param int         $offset   Offset.
     * @param string|null $encoding Encoding. Default null.
     *
     * @return int|false
     */
    public static function strpos( $haystack, $needle, $offset = 0, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            return ( null === $encoding ) ? \mb_strpos( $haystack, $needle, $offset ) : \mb_strlen( $haystack, $needle, $offset, $encoding );
        } elseif ( self::iconv_available() ) {
            return ( null === $encoding ) ? \iconv_strpos( $haystack, $needle, $offset ) : \iconv_strpos( $haystack, $needle, $offset, $encoding );
        } else {
            return \strpos( $haystack, $needle, $offset );
        }
    }

    /**
     * Attempts to return the number of characters in the given $string if
     * mbstring or iconv is available. Returns the number of bytes
     * (instead of characters) as fallback.
     *
     * @param string      $string   String.
     * @param string|null $encoding Encoding.
     *
     * @return int Number of charcters or bytes in given $string
     *             (characters if/when supported, bytes otherwise).
     */
    public static function strlen( $string, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            return ( null === $encoding ) ? \mb_strlen( $string ) : \mb_strlen( $string, $encoding );
        } elseif ( self::iconv_available() ) {
            return ( null === $encoding ) ? @iconv_strlen( $string ) : @iconv_strlen( $string, $encoding );
        } else {
            return \strlen( $string );
        }
    }

    /**
     * Our wrapper around implementations of \substr_replace()
     * that attempts to not break things horribly if at all possible.
     * Uses mbstring and/or iconv if available, before falling back to regular
     * substr_replace() (which works just fine in the majority of cases).
     *
     * @param string      $string      String.
     * @param string      $replacement Replacement.
     * @param int         $start       Start offset.
     * @param int|null    $length      Length.
     * @param string|null $encoding    Encoding.
     *
     * @return string
     */
    public static function substr_replace( $string, $replacement, $start, $length = null, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            $strlen = self::strlen( $string, $encoding );

            if ( $start < 0 ) {
                if ( -$start < $strlen ) {
                    $start = $strlen + $start;
                } else {
                    $start = 0;
                }
            } elseif ( $start > $strlen ) {
                $start = $strlen;
            }

            if ( null === $length || '' === $length ) {
                $start2 = $strlen;
            } elseif ( $length < 0 ) {
                $start2 = $strlen + $length;
                if ( $start2 < $start ) {
                    $start2 = $start;
                }
            } else {
                $start2 = $start + $length;
            }

            if ( null === $encoding ) {
                $leader  = $start ? \mb_substr( $string, 0, $start ) : '';
                $trailer = ( $start2 < $strlen ) ? \mb_substr( $string, $start2, null ) : '';
            } else {
                $leader  = $start ? \mb_substr( $string, 0, $start, $encoding ) : '';
                $trailer = ( $start2 < $strlen ) ? \mb_substr( $string, $start2, null, $encoding ) : '';
            }

            return "{$leader}{$replacement}{$trailer}";
        } elseif ( self::iconv_available() ) {
            $strlen = self::strlen( $string, $encoding );

            if ( $start < 0 ) {
                $start = \max( 0, $strlen + $start );
                $start = $strlen + $start;
                if ( $start < 0 ) {
                    $start = 0;
                }
            } elseif ( $start > $strlen ) {
                $start = $strlen;
            }

            if ( $length < 0 ) {
                $length = \max( 0, $strlen - $start + $length );
            } elseif ( null === $length || ( $length > $strlen ) ) {
                $length = $strlen;
            }

            if ( ( $start + $length ) > $strlen ) {
                $length = $strlen - $start;
            }

            if ( null === $encoding ) {
                return self::iconv_substr( $string, 0, $start ) . $replacement . self::iconv_substr( $string, $start + $length, $strlen - $start - $length );
            }

            return self::iconv_substr( $string, 0, $start, $encoding ) . $replacement . self::iconv_substr( $string, $start + $length, $strlen - $start - $length, $encoding );
        }

        return ( null === $length ) ? \substr_replace( $string, $replacement, $start ) : \substr_replace( $string, $replacement, $start, $length );
    }

    /**
     * Wrapper around iconv_substr().
     *
     * @param string      $s        String.
     * @param int         $start    Start offset.
     * @param int|null    $length   Length.
     * @param string|null $encoding Encoding.
     *
     * @return string
     */
    protected static function iconv_substr( $s, $start, $length = null, $encoding = null )
    {
        if ( $start < 0 ) {
            $start = self::strlen( $s, $encoding ) + $start;
            if ( $start < 0 ) {
                $start = 0;
            }
        }

        if ( null === $length ) {
            $length = 2147483647;
        } elseif ( $length < 0 ) {
            $length = self::strlen( $s, $encoding ) + ( $length - $start );
            if ( $length < 0 ) {
                return '';
            }
        }

        return (string) ( null === $encoding ) ? \iconv_substr( $s, $start, $length ) : \iconv_substr( $s, $start, $length, $encoding );
    }
}
