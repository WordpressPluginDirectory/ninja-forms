<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class NF_Fields_HTML
 */
class NF_Fields_HTML extends NF_Abstracts_Input
{
    protected $_name = 'html';

    protected $_section = 'layout';

    protected $_icon = 'code';

    protected $_aliases = array( 'html' );

    protected $_type = 'html';

    protected $_templates = 'html';

    protected $_settings_only = array( 'label', 'default', 'classes', 'admin_label', 'key' );

    protected $_use_merge_tags_include = array( 'calculations' );

    public function __construct()
    {
        parent::__construct();

        $this->_settings[ 'label' ][ 'width' ] = 'full';
        $this->_settings[ 'default' ][ 'group' ] = 'primary';
        $this->_settings[ 'default' ][ 'type' ]  = 'rte';
        $this->_settings[ 'default' ][ 'use_merge_tags' ]  = array(
            'include' => array(
                'calcs'
            ),
            'exclude' => array(
                'form',
                'fields'
            ),
        );

        $this->_nicename = esc_html__( 'HTML', 'ninja-forms' );

        add_filter( 'nf_sub_hidden_field_types', array( $this, 'hide_field_type' ) );
        add_filter( 'ninja_forms_localize_field_html', [ $this,'localizeField'], 10, 2);
        add_filter( 'ninja_forms_localize_field_html_preview', [ $this,'localizeField'], 10, 2);
    }

    function hide_field_type( $field_types )
    {
        $field_types[] = $this->_name;

        return $field_types;
    }

    /**
     * Localizaiton filter.
     * @param Array $field The field to be localized.
     * @return Array The localized field.
     */
    public function localizeField( $field )
    {
        if(isset($field['settings']['default'])){
            $incomingDefault = $field['settings']['default'];
        }else{
            $incomingDefault = '';
        }

        $field['settings']['default'] = $this->filter_tags($incomingDefault);
        
        return $field;
    }

    /**
     * Bypass method for wp_filter_content_tags.
     * @param String $content The HTML content.
     * @return String The filtered content.
     */
    private function filter_tags( $content )
    {
        if( function_exists('wp_filter_content_tags') ) {
            $content = wp_filter_content_tags( $content );
        }
        return $content;
    }
}
