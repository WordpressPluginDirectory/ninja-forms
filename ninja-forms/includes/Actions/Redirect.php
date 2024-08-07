<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class NF_Action_Redirect
 */
final class NF_Actions_Redirect extends NF_Abstracts_Action
{
    /**
    * @var string
    */
    protected $_name  = 'redirect';

    /**
    * @var array
    */
    protected $_tags = array();

    /**
     * @var string
     */
    protected $_documentation_url = 'https://ninjaforms.com/docs/redirect-action/';

    /**
    * @var string
    */
    protected $_timing = 'late';

    /**
    * @var int
    */
    protected $_priority = 20;

    /**
     * @var string
     */
    protected $_group = 'core';

    /**
    * Constructor
    */
    public function __construct()
    {
        parent::__construct();

        $this->_nicename = esc_html__( 'Redirect', 'ninja-forms' );

        $settings = Ninja_Forms::config( 'ActionRedirectSettings' );

        $this->_settings = array_merge( $this->_settings, $settings );
    }

    /*
    * PUBLIC METHODS
    */

    public function save( $action_settings )
    {

    }

    public function process( $action_settings, $form_id, $data )
    {
        $data[ 'actions' ][ 'redirect' ] = $action_settings[ 'redirect_url' ];

        return $data;
    }
}
