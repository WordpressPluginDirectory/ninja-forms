<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class NF_Database_Models_Form
 */
final class NF_Database_Models_Form extends NF_Abstracts_Model
{
    protected $_type = 'form';

    protected $_table_name = 'nf3_forms';

    protected $_meta_table_name = 'nf3_form_meta';

    protected $_columns = array(
        'title',
        'created_at',
        'form_title',
        'default_label_pos',
        'show_title',
        'clear_complete',
        'hide_complete',
        'logged_in',
        'seq_num'
    );

    protected $_fields;

    protected static $imported_form_id;

    public function __construct( $db, $id = '' )
    {
        add_action( 'ninja_forms_before_import_form', array( $this, 'import_form_backwards_compatibility' ) );
        parent::__construct( $db, $id );
    }

    public function delete()
    {
        parent::delete();

        $fields = Ninja_Forms()->form( $this->_id )->get_fields();

        foreach( $fields as $field ){
            $field->delete();
        }

        $actions = Ninja_Forms()->form( $this->_id )->get_actions();

        foreach( $actions as $action ){
            $action->delete();
        }

	    $chunked_option_flag = 'nf_form_' . $this->_id . '_chunks';
        $chunked_option_value = get_option( $chunked_option_flag );
	    // if there is nf_form_x_chunks option, we need to delete those
	    if( $chunked_option_value ) {
		    // if we have chunk'd it, get the list of chunks
		    $form_chunks = explode( ',', $chunked_option_value );

		    //get the option value of each chunk and concat them into the form
		    foreach( $form_chunks as $chunk ){
			    delete_option( $chunk );
		    }

		    delete_option( $chunked_option_flag );
	    }

	    $this->delete_submissions();

        WPN_Helper::delete_nf_cache( $this->_id );

        do_action( 'ninja_forms_after_form_delete', $this->_id );
    }

    private function delete_submissions( ) {
	    global $wpdb;
	    $total_subs_deleted = 0;
	    $post_result = 0;
	    $max_cnt = 250;

	    // SQL for getting 250 subs at a time
	    $sub_sql = "SELECT id FROM `" . $wpdb->prefix . "posts` AS p
			LEFT JOIN `" . $wpdb->prefix . "postmeta` AS m ON p.id = m.post_id
			WHERE p.post_type = 'nf_sub' AND m.meta_key = '_form_id'
			AND m.meta_value = %s LIMIT " . $max_cnt;

	    while ($post_result <= $max_cnt ) {
		    $subs = $wpdb->get_col( $wpdb->prepare( $sub_sql, $this->_id ),0 );
		    // if we are out of subs, then stop
		    if( 0 === count( $subs ) ) break;
		    // otherwise, let's delete the postmeta as well
		    $delete_meta_query = "DELETE FROM `" . $wpdb->prefix . "postmeta` WHERE post_id IN ( [IN] )";
		    $delete_meta_query = $this->prepare_in( $delete_meta_query, $subs );
		    $meta_result       = $wpdb->query( $delete_meta_query );
		    if ( $meta_result > 0 ) {
			    // now we actually delete the posts(nf_sub)
			    $delete_post_query = "DELETE FROM `" . $wpdb->prefix . "posts` WHERE id IN ( [IN] )";
			    $delete_post_query = $this->prepare_in( $delete_post_query, $subs );
			    $post_result       = $wpdb->query( $delete_post_query );
			    $total_subs_deleted = $total_subs_deleted + $post_result;

		    }
	    }
    }

	private function prepare_in( $sql, $vals ) {
		global $wpdb;
		$not_in_count = substr_count( $sql, '[IN]' );
		if ( $not_in_count > 0 ) {
			$args = array( str_replace( '[IN]', implode( ', ', array_fill( 0, count( $vals ), '%d' ) ), str_replace( '%', '%%', $sql ) ) );
			// This will populate ALL the [IN]'s with the $vals, assuming you have more than one [IN] in the sql
			for ( $i=0; $i < substr_count( $sql, '[IN]' ); $i++ ) {
				$args = array_merge( $args, $vals );
			}
			$sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( $args ) );
		}
		return $sql;
	}

    public static function get_next_sub_seq( $form_id )
    {
        global $wpdb;

        // TODO: Leverage form cache.

        $last_seq_num = $wpdb->get_var( $wpdb->prepare(
            'SELECT value FROM ' . $wpdb->prefix . 'nf3_form_meta WHERE `key` = "_seq_num" AND `parent_id` = %s'
        , $form_id ) );

        if( $last_seq_num ) {
            $wpdb->update( $wpdb->prefix . 'nf3_form_meta', array( 'value' => $last_seq_num + 1,
                'meta_value' => $last_seq_num + 1, 'meta_key' => '_seq_num' )
	            , array( 'key' => '_seq_num', 'parent_id'
            => $form_id ) );
            $wpdb->update( $wpdb->prefix . 'nf3_forms', array( 'seq_num' => $last_seq_num + 1 ), array( 'id' => $form_id ) );
        } else {
            $last_seq_num = 1;
            $wpdb->insert( $wpdb->prefix . 'nf3_form_meta',
	            array( 'key' => '_seq_num',
	                   'value' => $last_seq_num + 1,
	                   'parent_id' => $form_id,
		                'meta_key' => '_seq_num',
		                'meta_value' => $last_seq_num + 1
	            ) );
	        $wpdb->update( $wpdb->prefix . 'nf3_forms', array( 'seq_num' => $last_seq_num + 1 ), array( 'id' => $form_id ) );
        }

        return $last_seq_num;
    }

    public static function import( array $import, $id = '', $is_conversion = false )
    {
        $import = apply_filters( 'ninja_forms_before_import_form', $import );

        /*
        * Create Form
        */
        $form = Ninja_Forms()->form( $id )->get();
        $form->update_settings( $import[ 'settings' ] );
        
        if( ! $is_conversion ) {
            $form->update_setting( 'created_at', current_time( 'mysql' ) );
        }
		
        $form->save();
        $form_id = $form->get_id();

        $form_cache = array(
            'id' => $form_id,
            'fields' => array(),
            'actions' => array(),
            'settings' => $form->get_settings()
        );
        $update_process = Ninja_Forms()->background_process( 'update-fields' );
        foreach( $import[ 'fields' ] as $settings ){
			
            if( $is_conversion ) {
                $field_id = $settings[ 'id' ];
                $field = Ninja_Forms()->form($form_id)->field( $field_id )->get();
                $field->save();
            } else {
                unset( $settings[ 'id' ] );
                $settings[ 'created_at' ] = current_time( 'mysql' );
                $field = Ninja_Forms()->form($form_id)->field()->get();
                /**
                 * If this is the default contact form,
                 * ensure that we properly save the fields
                 * to avoid the loss of settings when the cache is disabled.
                 */
                if ( 1 == $form_id ) {
                    $field->update_settings( $settings );
                }
                $field->save();
            }

            $settings[ 'parent_id' ] = $form_id;

            array_push( $form_cache[ 'fields' ], array(
                'id' => $field->get_id(),
                'settings' => $settings
            ));

            $update_process->push_to_queue(array(
                'id' => $field->get_id(),
                'settings' => $settings
            ));
        }
        $update_process->save()->dispatch();

        foreach( $import[ 'actions' ] as $settings ){

            $action = Ninja_Forms()->form($form_id)->action()->get();

            if( ! $is_conversion ) {
                $settings[ 'created_at' ] = current_time( 'mysql' );
            }

            $action->update_settings( $settings )->save();

            array_push( $form_cache[ 'actions' ], array(
                'id' => $action->get_id(),
                'settings' => $settings
            ));
        }

        WPN_Helper::update_nf_cache( $form_id, $form_cache );

        add_action( 'admin_notices', array( 'NF_Database_Models_Form', 'import_admin_notice' ) );

        self::$imported_form_id = $form_id;

        return $form_id;
    }

    public static function import_admin_notice()
    {
        Ninja_Forms()->template( 'admin-notice-form-import.html.php', array( 'form_id'=> self::$imported_form_id ) );
    }

    /**
     * Processes repeater fields by updating child field IDs and settings for a new form.
     *
     * This function handles the migration of repeater fields from an old form to a new form.
     * It ensures that child field IDs are updated to match the new field ID and updates the
     * field settings accordingly.
     *
     * @param int $new_field_id The ID of the new field in the new form.
     * @param int $new_form_id The ID of the new form.
     * @param int $old_field_id The ID of the old field in the old form.
     * @param int $old_form_id The ID of the old form.
     *
     * @return void
     */
    private static function process_repeater_fields( $new_field_id, $new_form_id, $old_field_id, $old_form_id ) {

        $old = Ninja_Forms()->form( $old_form_id )->get_field( $old_field_id);
        if( $old->get_setting('type') === 'repeater') {
             // Get child fields into new field
            $new = Ninja_Forms()->form( $new_form_id )->get_field( $new_field_id );
            $field_controller = new NF_Database_FieldsController( $new_form_id, $new );
            $field_controller->update_field( $new_field_id, $new->get_settings() );
            // Get updated field and update child fields IDs
            $new = Ninja_Forms()->form( $new_form_id )->get_field( $new_field_id );
            foreach($new->_settings['fields'] as $index => $child_field) {
                $dotPosition = strpos($child_field['id'], '.');
                $partAfterDot = substr($child_field['id'], $dotPosition);
                $new->_settings['fields'][$index]['id'] = $new_field_id . $partAfterDot;
                $field_controller->update_field( $new_field_id, $new->_settings );
            }
        }
    }

    /**
     * This static method is called to duplicate a form using the form ID.
     *
     * To duplicate a form we:
     *
     *  Check to see if we've ran stage one of the db update process.
     *  Use SQL to insert a copy of our form and form meta.
     *  Grab all fields for a specific form.
     *  Loop over those fields and insert fields and field meta.
     *  Run ->update_settings() and ->save() on the form model.
     *  Call our WPN_Helper method to build a form cache.
     * 
     * @since  3.0
     * @update 3.4.0
     * @param  int  $form_id ID of the form being duplicated.
     * @return $new_form_id  ID of our form duplicate.
     */
    public static function duplicate( $form_id )
    {
        global $wpdb;


        /**
         * Check to see if we've got new field columns.
         *
         * We do this here instead of the get_sql_queries() method so that we don't hit the db multiple times.
         */
        $sql = "SHOW COLUMNS FROM {$wpdb->prefix}nf3_fields LIKE 'field_key'";
        $results = $wpdb->get_results( $sql );
        
        // If we don't have the field_key column, we need to remove our new columns.
        if ( empty ( $results ) ) {
            $db_stage_one_complete = false;
        } else {
            $db_stage_one_complete = true;
        }

        // Duplicate the Form Object.
        $wpdb->query( $wpdb->prepare(
            "
                INSERT INTO {$wpdb->prefix}nf3_forms ( `title` )
                SELECT CONCAT( `title`, ' - ', %s )
                FROM {$wpdb->prefix}nf3_forms 
                WHERE  id = %d;
            ", esc_html__( 'copy', 'ninja-forms' ), $form_id
        ) );
        $new_form_id = $wpdb->insert_id;

        $blacklist = array(
            '_seq_num',
            'embed_form',
            'public_link',
            'public_link_key',
            'allow_public_link',
        );
        $blacklist = apply_filters( 'ninja_forms_excluded_duplicate_form_settings', $blacklist );
        $blacklist = "'" . implode( "','", $blacklist ) . "'";

        // Duplicate the Form Meta.
        $wpdb->query( $wpdb->prepare(
           "
           INSERT INTO {$wpdb->prefix}nf3_form_meta ( `parent_id`, `key`, `value` )
                SELECT %d, `key`, `value`
                FROM   {$wpdb->prefix}nf3_form_meta
                WHERE  parent_id = %d
                AND `key` NOT IN({$blacklist});
           ", $new_form_id, $form_id
        ));

        // Get the fields to duplicate
        $old_fields = $wpdb->get_results( $wpdb->prepare(
            "
            SELECT `id`
            FROM {$wpdb->prefix}nf3_fields
            WHERE parent_id = %d
            ", $form_id
        ));

        // Get our field and field_meta table column names and values.
        $fields_sql = self::get_sql_queries( 'field_table_columns', $db_stage_one_complete );
        $field_meta_sql = self::get_sql_queries( 'field_meta_table_columns', $db_stage_one_complete );

        foreach( $old_fields as $old_field ){

            // Duplicate the Field Object.
            $wpdb->query( $wpdb->prepare(
               "
               INSERT INTO {$wpdb->prefix}nf3_fields ( {$fields_sql[ 'insert' ]} )
               SELECT {$fields_sql[ 'select' ]}
               FROM {$wpdb->prefix}nf3_fields
               WHERE id = %d
               ", $new_form_id, $old_field->id
            ));
            $new_field_id = $wpdb->insert_id;

            // Duplicate the Field Meta.
            $wpdb->query( $wpdb->prepare(
                "
                INSERT INTO {$wpdb->prefix}nf3_field_meta ( {$field_meta_sql[ 'insert' ]} )
                SELECT {$field_meta_sql[ 'select' ]}
                FROM   {$wpdb->prefix}nf3_field_meta
                WHERE  parent_id = %d;
                ", $new_field_id, $old_field->id
            ));

            // Detect repeater fieldset fields
            $get_field = Ninja_Forms()->form( $form_id )->get_field($old_field->id);
            if ($get_field->get_setting('type') === 'repeater') {
                $repeater_fields[] = [
                    'new_field_id' => $new_field_id,
                    'new_form_id' => $new_form_id,
                    'old_field_id' => $old_field->id,
                    'old_form_id' => $form_id
                ];
            }   
        }

        // Process repeater fields as we need to update the child field IDs.
        if (!empty($repeater_fields)) {
            foreach ($repeater_fields as $repeater_field) {
                self::process_repeater_fields(
                    $repeater_field['new_field_id'],
                    $repeater_field['new_form_id'],
                    $repeater_field['old_field_id'],
                    $repeater_field['old_form_id']
                );
            }
        }

        // Duplicate the Actions.

        // Get the actions to duplicate
        $old_actions = $wpdb->get_results( $wpdb->prepare(
            "
            SELECT `id`
            FROM {$wpdb->prefix}nf3_actions
            WHERE parent_id = %d
            ", $form_id
        ));

        // Get our action and action_meta table columns and values.
        $actions_sql = self::get_sql_queries( 'action_table_columns', $db_stage_one_complete );
        $actions_meta_sql = self::get_sql_queries( 'action_meta_table_columns', $db_stage_one_complete );

        foreach( $old_actions as $old_action ){
            // Duplicate the Action Object.
            $wpdb->query( $wpdb->prepare(
                "
               INSERT INTO {$wpdb->prefix}nf3_actions ( {$actions_sql[ 'insert' ]} )
               SELECT {$actions_sql[ 'select' ]}
               FROM {$wpdb->prefix}nf3_actions
               WHERE id = %d
               ", $new_form_id, $old_action->id
            ));
            $new_action_id = $wpdb->insert_id;
            // Duplicate the Action Meta.
            $wpdb->query( $wpdb->prepare(
                "
                INSERT INTO {$wpdb->prefix}nf3_action_meta ( {$actions_meta_sql[ 'insert' ]} )
                SELECT {$actions_meta_sql[ 'select' ]}
                FROM   {$wpdb->prefix}nf3_action_meta
                WHERE  parent_id = %d;
                ", $new_action_id, $old_action->id
            ));
        }

        /*
         * In order for our new form and form_meta fields to populate on
         * duplicate we need to update_settings and save
         */
        $new_form = Ninja_Forms()->form( $new_form_id )->get();
        $new_form->update_settings( $new_form->get_settings() );
        $new_form->save();

        /*
         * Build a cache for this new form.
         */
        WPN_Helper::build_nf_cache( $new_form_id );

        return $new_form_id;
    }

    /**
     * When duplicating a form, we need to build specific SQL queries.
     *
     * This is a fairly repetative task, so we've extrapolated the code to its own function.
     * 
     * @since  3.4.0
     * @param  string  $table_name Name of the table we want to update.
     * @return array   Associative array like: ['insert' => "`column1`, "`column2`", etc", 'select' => "`column1`, etc."]
     */
    private static function get_sql_queries( $table_name, $db_stage_one_complete = true )
    {
        /**
         * These arrays contain the columns in our database.
         *
         * Later, if the user hasn't ran stage one of our db update process, we'll remove the items that aren't supported. 
         */
        $db_columns = array(
            'field_table_columns' => array(
                'label',
                'key',
                'type',
                'parent_id',
                'field_label',
                'field_key',
                'order',
                'required',
                'default_value',
                'label_pos',
                'personally_identifiable',
            ),

            'field_meta_table_columns' => array(
                'parent_id',
                'key',
                'value',
                'meta_key',
                'meta_value',
            ),

            'action_table_columns' => array(
                'title',
                'key',
                'type',
                'active',
                'parent_id',
                'label',
            ),

            'action_meta_table_columns' => array(
                'parent_id',
                'key',
                'value',
                'meta_key',
                'meta_value',
            ),
        );

        // If we haven't been passed a table name or the table name is invalid, return false.
        if ( empty( $table_name ) || ! isset( $db_columns[ $table_name ] ) ) {return false;}
       
        // If we have not completed stage one of our db update, then we unset new db columns.
        if ( ! $db_stage_one_complete ) {

            $db_columns[ 'field_table_columns' ] = array_diff( 
                $db_columns[ 'field_table_columns' ], 
                array( 
                    'field_label',
                    'field_key',
                    'order',
                    'required',
                    'default_value',
                    'label_pos',
                    'personally_identifiable' 
                    )
                );
            
            $db_columns[ 'field_meta_table_columns' ] = array_diff(
                $db_columns[ 'field_meta_table_columns'],
                array(
                    'meta_key',
                    'meta_value'
                )
            );
            
            $db_columns[ 'action_table_columns' ] = array_diff(
                $db_columns[ 'action_table_columns' ],
                array(
                    'label'
                )
            );

            $db_columns[ 'action_meta_table_columns' ] = array_diff(
                $db_columns[ 'action_meta_table_columns' ],
                array(
                    'meta_key',
                    'meta_value'
                )
                );
        }

        /**
         * $sql_insert is a var that tracks which table columns we want to insert.
         */
        $sql_insert = "";
        /**
         * $sql_select is a var that tracks the values for each field table column.
         */
        $sql_select = "";

        /**
         * Loop over our table column names and add them to our $sql_insert var.
         */
        foreach( $db_columns[ $table_name ] as $column_name ) {
            $sql_insert .= "`{$column_name}`,";

            if ( 'parent_id' == $column_name ) {
                $sql_select .= "%d,";
            } else {
                $sql_select .= "`{$column_name}`,";
            }
        }

        // Remove any trailing commas.
        $sql_insert = rtrim( $sql_insert, ',' );
        $sql_select = rtrim( $sql_select, ',' );

        return array( 'insert' => $sql_insert, 'select' => $sql_select );
    }

    public static function export( $form_id, $return = FALSE )
    {
        //TODO: Set Date Format from Plugin Settings
        $date_format = 'm/d/Y';

        $form = Ninja_Forms()->form( $form_id )->get();
        
        $form_title = $form->get_setting( 'title' );
        $form_title = preg_replace( "/[^A-Za-z0-9 ]/", '', $form_title );
        $form_title = str_replace( ' ', '_', $form_title );

        $export = array(
            'settings' => $form->get_settings(),
            'fields' => array(),
            'actions' => array()
        );

        $fields = Ninja_Forms()->form( $form_id )->get_fields();

        foreach( $fields as $field ){
            // If the field is set.
            if ( ! is_null( $field ) && ! empty( $field ) ) {
                $export['fields'][] = $field->get_settings();
            }
        }

        $actions = Ninja_Forms()->form( $form_id )->get_actions();

        foreach( $actions as $action ){
            // If the action is set.
            if ( ! is_null( $action ) && ! empty( $action ) ) {
                $export[ 'actions' ][] = $action->get_settings();
            }
        }

        if( $return ){
            return $export;
        } else {

            $today = date( $date_format, current_time( 'timestamp' ) );
            $filename = apply_filters( 'ninja_forms_form_export_filename', 'nf_form_' . $today . '_' . $form_title );
            $filename = $filename . ".nff";

            header( 'Content-type: application/json');
            header( 'Content-Disposition: attachment; filename="'.$filename .'"' );
            header( 'Pragma: no-cache');
            header( 'Expires: 0' );
//            echo apply_filters( 'ninja_forms_form_export_bom',"\xEF\xBB\xBF" ) ; // Byte Order Mark
	        if( isset( $_REQUEST[ 'nf_export_form_turn_off_encoding' ] )
	            && $_REQUEST[ 'nf_export_form_turn_off_encoding' ] ) {
		        echo json_encode( $export );
	        } else {
		        echo json_encode( WPN_Helper::utf8_encode( $export ) );
	        }

            die();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Backwards Compatibility
    |--------------------------------------------------------------------------
    */

    public function import_form_backwards_compatibility( $import )
    {
        // Rename `data` to `settings`
        if( isset( $import[ 'data' ] ) ){
            $import[ 'settings' ] = $import[ 'data' ];
            unset( $import[ 'data' ] );
        }

        // Rename `notifications` to `actions`
        if( isset( $import[ 'notifications' ] ) ){
            $import[ 'actions' ] = $import[ 'notifications' ];
            unset( $import[ 'notifications' ] );
        }

        // Rename `form_title` to `title`
        if( isset( $import[ 'settings' ][ 'form_title' ] ) ){
            $import[ 'settings' ][ 'title' ] = $import[ 'settings' ][ 'form_title' ];
            unset( $import[ 'settings' ][ 'form_title' ] );
        }

        // Convert `last_sub` to `_seq_num`
        if( isset( $import[ 'settings' ][ 'last_sub' ] ) ) {
            $import[ 'settings' ][ '_seq_num' ] = $import[ 'settings' ][ 'last_sub' ] + 1;
        }

        // Make sure
        if( ! isset( $import[ 'fields' ] ) ){
            $import[ 'fields' ] = array();
        }

        // `Field` to `Fields`
        if( isset( $import[ 'field' ] ) ){
            $import[ 'fields' ] = $import[ 'field' ];
            unset( $import[ 'field' ] );
        }

        $import = apply_filters( 'ninja_forms_upgrade_settings', $import );

        // Combine Field and Field Data
        foreach( $import[ 'fields' ] as $key => $field ){

            if( '_honeypot' == $field[ 'type' ] ) {
                unset( $import[ 'fields' ][ $key ] );
                continue;
            }

            if( ! $field[ 'type' ] ) {
                unset( $import[ 'fields'][ $key ] );
                continue;
            }

            // TODO: Split Credit Card field into multiple fields.
            $field = $this->import_field_backwards_compatibility( $field );

            if( isset( $field[ 'new_fields' ] ) ){
                foreach( $field[ 'new_fields' ] as $new_field ){
                    $import[ 'fields' ][] = $new_field;
                }
                unset( $field[ 'new_fields' ] );
            }

            $import[ 'fields' ][ $key ] = $field;
        }

        $has_save_action = FALSE;
        foreach( $import[ 'actions' ] as $key => $action ){
            $action = $this->import_action_backwards_compatibility( $action );
            $import[ 'actions' ][ $key ] = $action;

            if( 'save' == $action[ 'type' ] ) $has_save_action = TRUE;
        }

        if( ! $has_save_action ) {
            $import[ 'actions' ][] = array(
                'type' => 'save',
                'label' => esc_html__( 'Save Form', 'ninja-forms' ),
                'active' => TRUE
            );
        }

        $import = $this->import_merge_tags_backwards_compatibility( $import );

        return apply_filters( 'ninja_forms_after_upgrade_settings', $import );
    }

    public function import_merge_tags_backwards_compatibility( $import )
    {
        $field_lookup = array();

        foreach( $import[ 'fields' ] as $key => $field ){

            if( ! isset( $field[ 'id' ] ) ) continue;

            $field_id  = $field[ 'id' ];
            $field_key = $field[ 'type' ] . '_' . $field_id;
            $field_lookup[ $field_id ] = $import[ 'fields' ][ $key ][ 'key' ] = $field_key;
        }

        foreach( $import[ 'actions' ] as $key => $action_settings ){
            foreach( $action_settings as $setting => $value ){
                foreach( $field_lookup as $field_id => $field_key ){

                    // Convert Tokenizer
                    $token = 'field_' . $field_id;
                    if( ! is_array( $value ) ) {
                        if (FALSE !== strpos($value, $token)) {
                            $value = str_replace($token, '{field:' . $field_key . '}', $value);
                        }
                    }

                    // Convert Shortcodes
                    $shortcode = "[ninja_forms_field id=$field_id]";
                    if( ! is_array( $value ) ) {
                        if ( FALSE !== strpos( $value, $shortcode ) ) {
                            $value = str_replace( $shortcode, '{field:' . $field_key . '}', $value );
                        }
                    }
                }

                //Checks for the nf_sub_seq_num short code and replaces it with the submission sequence merge tag
                $sub_seq = '[nf_sub_seq_num]';
                if( ! is_array( $value ) ) {
                    if( FALSE !== strpos( $value, $sub_seq ) ){
                        $value = str_replace( $sub_seq, '{submission:sequence}', $value );
                    }
                }

                if( ! is_array( $value ) ) {
                    if (FALSE !== strpos($value, '[ninja_forms_all_fields]')) {
                        $value = str_replace('[ninja_forms_all_fields]', '{field:all_fields}', $value);
                    }
                }
                $action_settings[ $setting ] = $value;
                $import[ 'actions' ][ $key ] = $action_settings;
            }
        }

        return $import;
    }

    public function import_action_backwards_compatibility( $action )
    {
        // Remove `_` from type
        if( isset( $action[ 'type' ] ) ) {
            $action['type'] = str_replace('_', '', $action['type']);
        }

        if( 'email' == $action[ 'type' ] ){
            $action[ 'to' ]            	= str_replace( '`', ',', $action[ 'to' ] );
            $action[ 'email_subject' ] 	= str_replace( '`', ',', $action[ 'email_subject' ] );
            $action[ 'cc' ] 		= str_replace( '`', ',', $action[ 'cc' ] );
            $action[ 'bcc' ] 		= str_replace( '`', ',', $action[ 'bcc' ] );
            // If our email is in plain text...
            if ( $action[ 'email_format' ] == 'plain' ) {
                // Record it as such.
                $action[ 'email_message_plain' ] = $action[ 'email_message' ];
            } // Otherwise... (It's not plain text.)
            else {
                // Record it as HTML.
                $action[ 'email_message' ] = nl2br( $action[ 'email_message' ] );
            }
        }

        // Convert `name` to `label`
        if( isset( $action[ 'name' ] ) ) {
            $action['label'] = $action['name'];
            unset($action['name']);
        }

        return apply_filters( 'ninja_forms_upgrade_action_' . $action[ 'type' ], $action );
    }

    public function import_field_backwards_compatibility( $field )
    {
        // Flatten field settings array
        if( isset( $field[ 'data' ] ) && is_array( $field[ 'data' ] ) ){
            $field = array_merge( $field, $field[ 'data' ] );
        }
        unset( $field[ 'data' ] );

        // Drop form_id in favor of parent_id, which is set by the form.
        if( isset( $field[ 'form_id' ] ) ){
            unset( $field[ 'form_id' ] );
        }

        // Remove `_` prefix from type setting
        $field[ 'type' ] = ltrim( $field[ 'type' ], '_' );

        // Type: `text` -> `textbox`
        if( 'text' == $field[ 'type' ] ){
            $field[ 'type' ] = 'textbox';
        }

        if( 'submit' == $field[ 'type' ] ){
            $field[ 'processing_label' ] = esc_html__( 'Processing', 'ninja-forms' );
        }

        if( isset( $field[ 'email' ] ) ){

            if( 'textbox' == $field[ 'type' ] && $field[ 'email' ] ) {
                $field['type'] = 'email';
            }
            unset( $field[ 'email' ] );
        }

        if( isset( $field[ 'class' ] ) ){
            $field[ 'element_class' ] = $field[ 'class' ];
            unset( $field[ 'class' ] );
        }

        if( isset( $field[ 'req' ] ) ){
            $field[ 'required' ] = $field[ 'req' ];
            unset( $field[ 'req' ] );
        }

        if( isset( $field[ 'default_value_type' ] ) ){

            /* User Data */
            if( '_user_id' == $field[ 'default_value_type' ] )           $field[ 'default' ] = '{wp:user_id}';
            if( '_user_email' == $field[ 'default_value_type' ] )        $field[ 'default' ] = '{wp:user_email}';
            if( '_user_lastname' == $field[ 'default_value_type' ] )     $field[ 'default' ] = '{wp:user_last_name}';
            if( '_user_firstname' == $field[ 'default_value_type' ] )    $field[ 'default' ] = '{wp:user_first_name}';
            if( '_user_display_name' == $field[ 'default_value_type' ] ) $field[ 'default' ] = '{wp:user_display_name}';

            /* Post Data */
            if( 'post_id' == $field[ 'default_value_type' ] )    $field[ 'default' ] = '{wp:post_id}';
            if( 'post_url' == $field[ 'default_value_type' ] )   $field[ 'default' ] = '{wp:post_url}';
            if( 'post_title' == $field[ 'default_value_type' ] ) $field[ 'default' ] = '{wp:post_title}';

            /* System Data */
            if( 'today' == $field[ 'default_value_type' ] ) $field[ 'default' ] = '{other:date}';

            /* Miscellaneous */
            if( '_custom' == $field[ 'default_value_type' ] && isset( $field[ 'default_value' ] ) ){
                $field[ 'default' ] = $field[ 'default_value' ];
            }
            if( 'querystring' == $field[ 'default_value_type' ] && isset( $field[ 'default_value' ] ) ){
                $field[ 'default' ] = '{querystring:' . $field[ 'default_value' ] . '}';
            }

            unset( $field[ 'default_value' ] );
            unset( $field[ 'default_value_type' ] );
        } else if ( isset ( $field[ 'default_value' ] ) ) {
            $field[ 'default' ] = $field[ 'default_value' ];
        }

        if( 'list' == $field[ 'type' ] ) {

            if ( isset( $field[ 'list_type' ] ) ) {

                if ('dropdown' == $field['list_type']) {
                    $field['type'] = 'listselect';
                }
                if ('radio' == $field['list_type']) {
                    $field['type'] = 'listradio';
                }
                if ('checkbox' == $field['list_type']) {
                    $field['type'] = 'listcheckbox';
                }
                if ('multi' == $field['list_type']) {
                    $field['type'] = 'listmultiselect';
                }
            }

            if( isset( $field[ 'list' ][ 'options' ] ) ) {
                $field[ 'options' ] = array_values( $field[ 'list' ][ 'options' ] );
                unset( $field[ 'list' ][ 'options' ] );
            }

            foreach( $field[ 'options' ] as &$option ){
                if( isset( $option[ 'value' ] ) && $option[ 'value' ] ) continue;
                $option[ 'value' ] = $option[ 'label' ];
            }
        }

        if( 'country' == $field[ 'type' ] ){
            $field[ 'type' ] = 'listcountry';
            $field[ 'options' ] = array();
        }

        // Convert `textbox` to other field types
        foreach( array( 'fist_name', 'last_name', 'user_zip', 'user_city', 'user_phone', 'user_email', 'user_address_1', 'user_address_2', 'datepicker' ) as $item ) {
            if ( isset( $field[ $item ] ) && $field[ $item ] ) {
                $field[ 'type' ] = str_replace( array( '_', 'user', '1', '2', 'picker' ), '', $item );

                unset( $field[ $item ] );
            }
        }

        if( 'timed_submit' == $field[ 'type' ] ) {
            $field[ 'type' ] = 'submit';
        }

        if( 'checkbox' == $field[ 'type' ] ){

            if( isset( $field[ 'calc_value' ] ) ){

                if( isset( $field[ 'calc_value' ][ 'checked' ] ) ){
                    $field[ 'checked_calc_value' ] = $field[ 'calc_value' ][ 'checked' ];
                    unset( $field[ 'calc_value' ][ 'checked' ] );
                }
                if( isset( $field[ 'calc_value' ][ 'unchecked' ] ) ){
                    $field[ 'unchecked_calc_value' ] = $field[ 'calc_value' ][ 'unchecked' ];
                    unset( $field[ 'calc_value' ][ 'unchecked' ] );
                }
            }
        }

        if( 'rating' == $field[ 'type' ] ){
            $field[ 'type' ] = 'starrating';

            if( isset( $field[ 'rating_stars' ] ) ){
                $field[ 'default' ] = $field[ 'rating_stars' ];
                unset( $field[ 'rating_stars' ] );
            }
        }

        if( 'number' == $field[ 'type' ] ){

            if( ! isset( $field[ 'num_min'] ) ) {
                if( ! isset( $field[ 'number_min' ] ) || ! $field[ 'number_min' ] ){
                    $field[ 'num_min' ] = '';
                } else {
                    $field[ 'num_min' ] = $field[ 'number_min' ];
                }
            }

            if( ! isset( $field[ 'num_max'] ) ) {
                if( ! isset( $field[ 'number_max' ] ) || ! $field[ 'number_max' ] ){
                    $field[ 'num_max' ] = '';
                } else {
                    $field[ 'num_max' ] = $field[ 'number_max' ];
                }
            }

            if( ! isset( $field[ 'num_step'] ) ) {
                if( ! isset( $field[ 'number_step' ] ) || ! $field[ 'number_step' ] ){
                    $field[ 'num_step' ] = 1;
                } else {
                    $field[ 'num_step' ] = $field[ 'number_step' ];
                }
            }
        }

        if( 'profile_pass' == $field[ 'type' ] ){
            $field[ 'type' ] = 'password';

            $passwordconfirm = array_merge( $field, array(
                'id' => '',
                'type' => 'passwordconfirm',
                'label' => $field[ 'label' ] . ' ' . esc_html__( 'Confirm', 'ninja-forms' ),
                'confirm_field' => 'password_' . $field[ 'id' ]
            ));
            $field[ 'new_fields' ][] = $passwordconfirm;
        }

        if( 'desc' == $field[ 'type' ] ){
            $field[ 'type' ] = 'html';
        }

        if( 'credit_card' == $field[ 'type' ] ){

            $field[ 'type' ] = 'creditcardnumber';
            $field[ 'label' ] = $field[ 'cc_number_label' ];
            $field[ 'label_pos' ] = 'above';

            if( $field[ 'help_text' ] ){
                $field[ 'help_text' ] = '<p>' . $field[ 'help_text' ] . '</p>';
            }

            $credit_card_fields = array(
                'creditcardcvc'        => $field[ 'cc_cvc_label' ],
                'creditcardfullname'   => $field[ 'cc_name_label' ],
                'creditcardexpiration' => $field[ 'cc_exp_month_label' ] . ' ' . $field[ 'cc_exp_year_label' ],
                'creditcardzip'        => esc_html__( 'Credit Card Zip', 'ninja-forms' ),
            );


            foreach( $credit_card_fields as $new_type => $new_label ){
                $field[ 'new_fields' ][] = array_merge( $field, array(
                    'id' => '',
                    'type' => $new_type,
                    'label' => $new_label,
                    'help_text' => '',
                    'desc_text' => ''
                ));
            }
        }

        /*
         * Convert inside label position over to placeholder
         */
        if ( isset ( $field[ 'label_pos' ] ) && 'inside' == $field[ 'label_pos' ] ) {
            if ( ! isset ( $field[ 'placeholder' ] ) || empty ( $field[ 'placeholder' ] ) ) {
                $field[ 'placeholder' ] = $field[ 'label' ];
            }
            $field[ 'label_pos' ] = 'hidden';
        }

        if( isset( $field[ 'desc_text' ] ) ){
            $field[ 'desc_text' ] = nl2br( $field[ 'desc_text' ] );
        }
        if( isset( $field[ 'help_text' ] ) ){
            $field[ 'help_text' ] = nl2br( $field[ 'help_text' ] );
        }


        return apply_filters( 'ninja_forms_upgrade_field', $field );
    }

} // End NF_Database_Models_Form
