<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class NF_MergeTags_Other
 */
class NF_MergeTags_Other extends NF_Abstracts_MergeTags
{
    protected $id = 'other';

    public function __construct()
    {
        parent::__construct();
        $this->title = esc_html__( 'Other', 'ninja-forms' );
        $this->merge_tags = Ninja_Forms()->config( 'MergeTagsOther' );

        add_action( 'init', array( $this, 'init' ) );
    }

    public function replace( $subject )
    {
        $subject = parent::replace( $subject );

        if (is_string($subject)) {
            preg_match_all("/{querystring:(.*?)}/", $subject, $matches );
        }

        if ( ! isset( $matches ) || ! is_array( $matches ) ) return $subject;

        /**
         * $matches[0][$i]  merge tag match     {post_meta:foo}
         * $matches[1][$i]  captured meta key   foo
         */
        foreach( $matches[0] as $i => $search ){
            // Replace unused querystring merge tags.
            $subject = str_replace( $matches[0][$i], '', $subject );
        }

        return $subject;
    }

    public function init()
    {
        if( is_admin() ) {
            if( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) return;

            $referrer = wp_get_referer();

            if(!is_string($referrer)){
                return;
            }

            $variables = $this->constructVariablesFromReferrer($referrer);
        } else {
            $variables = $_GET;
        }

        if( ! is_array( $variables ) ) return;

        foreach( $variables as $key => $value ){
            if ( is_array( $value ) ) {
                $value = wp_kses_post_deep( $value );
                $value = map_deep( $value, 'esc_attr' );
            } else {
                $value = wp_kses_post( $value );
                $value = esc_attr( $value );
            }
            $this->set_merge_tags( $key, $value );
        }
    }

    /**
     * Construct key-value responses from a referrer string
     *
     * @param string $referrer
     * @return array
     */
    protected function constructVariablesFromReferrer(string $referrer): array
    {
        $return = [];

        $url_query = parse_url( $referrer, PHP_URL_QUERY );

        if(is_string($url_query)){

            parse_str( $url_query, $return );
        }

        return $return;
    }
    
    public function __call($name, $arguments)
    {
        return $this->merge_tags[ $name ][ 'value' ];
    }

    /**
     * Assign a merge tag construct array to a key
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set_merge_tags( $key, $value )
    {
        // Remove static callback potential
        if( false !== strpos( $key, '::' ) ) return;

        $callback = ( is_numeric( $key ) ) ? 'querystring_' . $key : $key;

        $this->merge_tags[ $callback ] = array(
            'id' => $key,
            'tag' => "{querystring:" . $key . "}",
            'callback' => $callback,
            'value' => $value
        );

        $this->merge_tags[ $callback . '_deprecated' ] = array(
            'id' => $key,
            'tag' => "{" . $key . "}",
            'callback' => $callback,
            'value' => $value
        );
    }

    protected function system_date()
    {
        $format = Ninja_Forms()->get_setting( 'date_format' );
        if ( empty( $format ) ) {
            $format = 'Y/m/d';
        }
        return date( $format, time() );
    }

    protected function system_time()
    {
        return date_i18n( get_option( 'time_format' ), current_time( 'timestamp' ) );
    }

    protected function user_ip()
    {
        $ip = '127.0.0.1';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return apply_filters( 'ninja_forms-get_ip', apply_filters( 'nf_get_ip', $ip ) );
    }

    protected function referer_url()
    {   
        return apply_filters( 'ninja_forms-referer_url_mt', wp_get_referer() );
    }

    protected function mergetag_random( $length = 5 ) {
        $characters    = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = '';
        
        for ( $i = 0; $i < $length; $i++ ) {
            $random_string .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
        }
    
        return apply_filters('ninja_forms-mergetag_random', $random_string );
    }

    protected function mergetag_year()
    {   


        return apply_filters( 'ninja_forms-mergetag_year', date( 'Y' ) );
    }

    protected function mergetag_month()
    {   
        return apply_filters( 'ninja_forms-mergetag_month', date( 'm' ) );
    }

    protected function mergetag_day()
    {   
        return apply_filters( 'ninja_forms-mergetag_day', date( 'd' ) );
    }

} // END CLASS NF_MergeTags_Other