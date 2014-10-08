<?php

/*
Pods is replacing the whole table in order to update a schema. https://github.com/pods-framework/pods/issues/2167
A solution is to use wp-admin/inludes/upgrade.php->dbDelta() to run only the desired changes.

todo: debug where the query is run in pods (PodsAPI->save_pod->pods_query) and try to fix with dbDelta
        http://codex.wordpress.org/Creating_Tables_with_Plugins

        validação de campos trava se ele ja existe. na vdd precisamos deixar passar tudo para o dbDelta
*/

class PW_Data extends PW_Module
{
    static $migrate_exist = false;

    static function capabilities()
    {
        return array(
            'system_admin' => 'pw_data_migrate',
        );
    }

    static function init_hooks()
    {
        if ( is_admin() 
            && ( is_super_admin( get_current_user_id() ) || current_user_can( 'pw_data_migrate' ))
            && ( isset($_GET['_pw_data_migrate']) || defined('PW_DATA_MIGRATE') && PW_DATA_MIGRATE == true )
            && ( DOING_AUTOSAVE !== true && DOING_AJAX !== true)
        )
        {
            add_action( 'setup_theme', array('PW_Data', 'import_components'), 21 );
        }
    }

    // imports all PW components
    static function import_components()
    {
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        foreach (PWrapper::$components as $component => $options)
        {
            $directory = $options['pwrapper'];

            // component version
            if ($component == 'theme' || $component == 'theme_parent')
                $version = wp_get_theme( basename(dirname($directory)) )->get('Version');
            else
                $version = get_plugin_data(plugin_dir_path($options['plugin']), false)['Version'];

            // data json files (pods exports)
            foreach (glob("$directory/data/*.json") as $file)
            {
                $filename = basename($file);

                // component file versioning
                $key = "_pw_data_$component/$filename";
                $option = get_option($key);

                if ( !$option || $option != $version)
                {
                    PW_Logger::log("New component ($component) file ($filename) version ($option => $version)");

                    self::import($file);

                    update_option($key, $version);
					
					$message[] = "Imported: <i>$filename</i> ($version)";
                }
				else
				{
					$message[] = "Skipped <i>$filename</i> ($option)";
				}

                //PW_Logger::log("Component importing finished ($component)");
            }
			$message = implode('<br/>', $message);
            PW_Notice::add("<b>PW Data:</b> Component \"$component\" finished migrating.<br/>$message", 'info');
        }
    }

    static function export()
    {
        // get the pod
        // export in its format
        // format json to line breakable (allow diffs)
        // deformat file in the import method
    }


    static function import($file, $replace=false)
    {
        set_time_limit(0);
        
        if (file_exists($file))
        {
            $datafile = explode("/", $file);
            $datafile = implode('/', array_slice($datafile, -4));
            PW_Logger::log("Starting import of data file (". $datafile .")", 'header');

            // pod data
            $data = json_decode( File_Helper::file_get_contents_utf_ansi($file), true );

            // call the import procedure
            if (isset($data['pods']))
                $imported = self::pods_import($data, $replace);

            PW_Logger::log([
                "File importing finished (". basename($datafile) .")",
                "Pods imported: ". implode(", ", $imported),
            ]);
        }
    }

    static function pods_import($data, $replace=false)
    {
        if ( !is_array( $data ) ) {
            $json_data = @json_decode( $data, true );
            if ( !is_array( $json_data ) )
                $json_data = @json_decode( pods_unslash( $data ), true );
            $data = $json_data;
        }
        if ( !is_array( $data ) || empty( $data ) )
            return false;

        $api = pods_api();
        $api->display_errors = false;

        pods_data()->display_errors = false;
        PodsData::$display_errors = false;

        global $wpdb;
        $wpdb->show_errors = false;

        // todo: make a function to handle display_error
        //       also hook into pods_error to not allow its printing..

        $migrated = array();

        if ( isset( $data[ 'pods' ] ) && is_array( $data[ 'pods' ] ) )
        {
            foreach ( $data[ 'pods' ] as $pod_data )
            {
                try
                {
                    // upgrade/create the pod
                    $result = self::pods_upgrade_pod($pod_data, $replace);

                    if ( $result == true )
                    {
                        $migrated[] = $pod_data[ 'name' ];
                    }
                    else
                    {
                        PW_Logger::log("Pod import failed ($pod_data[name])");
                        PW_Notice::add("There was a problem processing migration of pod ($pod_data[name]). Please check the logs or call the support team.", 'error');
                    }

                }
                catch (Exception $e)
                {
                    PW_Logger::log([
                        "Error processing pod ($pod_data[name]):",
                        $e->getMessage()
                    ]);
                    PW_Notice::add("There was an error processing migration of pod ($pod_data[name]). Please check the logs or call the support team.", 'error');
                }

            }
        }

        PodsData::$display_errors = true;
        $wpdb->show_errors = true;
        return $migrated;
    }

    static function pods_upgrade_pod($pod_data, $replace=false)
    {
        if ( isset( $pod_data[ 'id' ] ) )
            unset( $pod_data[ 'id' ] );

        $fields = $pod_data['fields'];

        $api = pods_api();
        $api->display_errors = false;

        $pod = $api->load_pod( array( 'name' => $pod_data[ 'name' ] ), false );

        if ($pod == false)
        {
            PW_Logger::log("Creating new pod ($pod_data[name])");

            //todo: checar se é possível salvar também os fields neste passo
            //      deve estar mal formatado (os arrays)

            // create new pod
            $api->save_pod($pod_data);
            
            return self::pods_upgrade_pod($pod_data, $replace);
        }
        else
        {
            PW_Logger::log("Upgrading pod ($pod_data[name])");

            // merge fields to be upgraded
            $fields = array_merge( $pod['fields'], $fields );
        }

        foreach ( $fields as $k => $field )
        {
            // prepare field for saving
            unset($field['id']);
            $field['pod_id'] = $pod['id'];
            $field['pod']    = $pod['name'];

            try
            {
                // save field from $pod_data
                if (isset( $pod_data['fields'][$k] ))
                {
                    PW_Logger::log("Updating field ($k)");

                    // merge w/ current db field
                    if ( isset($pod['fields'][$k]) )
                        $field = array_merge($pod['fields'][$k], $field);

                    $field['options'] = array_merge($pod['fields'][$k]['options'], $field, $field['options']);

                    // update/insert the field
                    $api->save_field($field);
                }
                elseif ($replace)
                {
                    PW_Logger::log("Deleting field ($k)");

                    // remove non existent field
                    $api->delete_field($field);
                }                
            }
            catch (Exception $e)
            {
                    PW_Logger::log([
                        "Error processing field ($k):",
                        $e->getMessage()
                    ]);
            }
        }

        return true;
    }

}

// possibly usefull methods for incrementing the pods_import method
class PW_PodsMigrate {

    static function backwards_compatibility($pod_data, $data)
    {
        // Backwards compatibility
        if ( version_compare( $data[ 'meta' ][ 'version' ], '2.0', '<' ) ) {
            $core_fields = array(
                array(
                    'name' => 'created',
                    'label' => 'Date Created',
                    'type' => 'datetime',
                    'options' => array(
                        'datetime_format' => 'ymd_slash',
                        'datetime_time_type' => '12',
                        'datetime_time_format' => 'h_mm_ss_A'
                    ),
                    'weight' => 1
                ),
                array(
                    'name' => 'modified',
                    'label' => 'Date Modified',
                    'type' => 'datetime',
                    'options' => array(
                        'datetime_format' => 'ymd_slash',
                        'datetime_time_type' => '12',
                        'datetime_time_format' => 'h_mm_ss_A'
                    ),
                    'weight' => 2
                ),
                array(
                    'name' => 'author',
                    'label' => 'Author',
                    'type' => 'pick',
                    'pick_object' => 'user',
                    'options' => array(
                        'pick_format_type' => 'single',
                        'pick_format_single' => 'autocomplete',
                        'default_value' => '{@user.ID}'
                    ),
                    'weight' => 3
                )
            );

            $found_fields = array();

            if ( !empty( $pod_data[ 'fields' ] ) ) {
                foreach ( $pod_data[ 'fields' ] as $k => $field ) {
                    $field_type = $field[ 'coltype' ];

                    if ( 'txt' == $field_type )
                        $field_type = 'text';
                    elseif ( 'desc' == $field_type )
                        $field_type = 'wysiwyg';
                    elseif ( 'code' == $field_type )
                        $field_type = 'paragraph';
                    elseif ( 'bool' == $field_type )
                        $field_type = 'boolean';
                    elseif ( 'num' == $field_type )
                        $field_type = 'number';
                    elseif ( 'date' == $field_type )
                        $field_type = 'datetime';

                    $multiple = min( max( (int) $field[ 'multiple' ], 0 ), 1 );

                    $new_field = array(
                        'name' => trim( $field[ 'name' ] ),
                        'label' => trim( $field[ 'label' ] ),
                        'description' => trim( $field[ 'comment' ] ),
                        'type' => $field_type,
                        'weight' => (int) $field[ 'weight' ],
                        'options' => array(
                            'required' => min( max( (int) $field[ 'required' ], 0 ), 1 ),
                            'unique' => min( max( (int) $field[ 'unique' ], 0 ), 1 ),
                            'input_helper' => $field[ 'input_helper' ]
                        )
                    );

                    if ( in_array( $new_field[ 'name' ], $found_fields ) ) {
                        unset( $pod_data[ 'fields' ][ $k ] );

                        continue;
                    }

                    $found_fields[] = $new_field[ 'name' ];

                    if ( 'pick' == $field_type ) {
                        $new_field[ 'pick_object' ] = 'pod';
                        $new_field[ 'pick_val' ] = $field[ 'pickval' ];

                        if ( 'wp_user' == $field[ 'pickval' ] )
                            $new_field[ 'pick_object' ] = 'user';
                        elseif ( 'wp_post' == $field[ 'pickval' ] )
                            $new_field[ 'pick_object' ] = 'post_type-post';
                        elseif ( 'wp_page' == $field[ 'pickval' ] )
                            $new_field[ 'pick_object' ] = 'post_type-page';
                        elseif ( 'wp_taxonomy' == $field[ 'pickval' ] )
                            $new_field[ 'pick_object' ] = 'taxonomy-category';

                        // This won't work if the field doesn't exist
                        // $new_field[ 'sister_id' ] = $field[ 'sister_field_id' ];

                        $new_field[ 'options' ][ 'pick_filter' ] = $field[ 'pick_filter' ];
                        $new_field[ 'options' ][ 'pick_orderby' ] = $field[ 'pick_orderby' ];
                        $new_field[ 'options' ][ 'pick_display' ] = '';
                        $new_field[ 'options' ][ 'pick_size' ] = 'medium';

                        if ( 1 == $multiple ) {
                            $new_field[ 'options' ][ 'pick_format_type' ] = 'multi';
                            $new_field[ 'options' ][ 'pick_format_multi' ] = 'checkbox';
                            $new_field[ 'options' ][ 'pick_limit' ] = 0;
                        }
                        else {
                            $new_field[ 'options' ][ 'pick_format_type' ] = 'single';
                            $new_field[ 'options' ][ 'pick_format_single' ] = 'dropdown';
                            $new_field[ 'options' ][ 'pick_limit' ] = 1;
                        }
                    }
                    elseif ( 'file' == $field_type ) {
                        $new_field[ 'options' ][ 'file_format_type' ] = 'multi';
                        $new_field[ 'options' ][ 'file_type' ] = 'any';
                    }
                    elseif ( 'number' == $field_type )
                        $new_field[ 'options' ][ 'number_decimals' ] = 2;
                    elseif ( 'desc' == $field[ 'coltype' ] )
                        $new_field[ 'options' ][ 'wysiwyg_editor' ] = 'tinymce';
                    elseif ( 'text' == $field_type )
                        $new_field[ 'options' ][ 'text_max_length' ] = 128;

                    if ( isset( $pod[ 'fields' ][ $new_field[ 'name' ] ] ) )
                        $new_field = array_merge( $pod[ 'fields' ][ $new_field[ 'name' ] ], $new_field );

                    $pod_data[ 'fields' ][ $k ] = $new_field;
                }
            }

            if ( pods_var( 'id', $pod, 0 ) < 1 )
                $pod_data[ 'fields' ] = array_merge( $core_fields, $pod_data[ 'fields' ] );

            if ( empty( $pod_data[ 'label' ] ) )
                $pod_data[ 'label' ] = ucwords( str_replace( '_', ' ', $pod_data[ 'name' ] ) );

            if ( isset( $pod_data[ 'is_toplevel' ] ) ) {
                $pod_data[ 'show_in_menu' ] = ( 1 == $pod_data[ 'is_toplevel' ] ? 1 : 0 );

                unset( $pod_data[ 'is_toplevel' ] );
            }

            if ( isset( $pod_data[ 'detail_page' ] ) ) {
                $pod_data[ 'detail_url' ] = $pod_data[ 'detail_page' ];

                unset( $pod_data[ 'detail_page' ] );
            }

            if ( isset( $pod_data[ 'before_helpers' ] ) ) {
                $pod_data[ 'pre_save_helpers' ] = $pod_data[ 'before_helpers' ];

                unset( $pod_data[ 'before_helpers' ] );
            }

            if ( isset( $pod_data[ 'after_helpers' ] ) ) {
                $pod_data[ 'post_save_helpers' ] = $pod_data[ 'after_helpers' ];

                unset( $pod_data[ 'after_helpers' ] );
            }

            if ( isset( $pod_data[ 'pre_drop_helpers' ] ) ) {
                $pod_data[ 'pre_delete_helpers' ] = $pod_data[ 'pre_drop_helpers' ];

                unset( $pod_data[ 'pre_drop_helpers' ] );
            }

            if ( isset( $pod_data[ 'post_drop_helpers' ] ) ) {
                $pod_data[ 'post_delete_helpers' ] = $pod_data[ 'post_drop_helpers' ];

                unset( $pod_data[ 'post_drop_helpers' ] );
            }

            $pod_data[ 'name' ] = pods_clean_name( $pod_data[ 'name' ] );

            $pod_data = array(
                'name' => $pod_data[ 'name' ],
                'label' => $pod_data[ 'label' ],
                'type' => 'pod',
                'storage' => 'table',
                'fields' => $pod_data[ 'fields' ],
                'options' => array(
                    'pre_save_helpers' => pods_var_raw( 'pre_save_helpers', $pod_data ),
                    'post_save_helpers' => pods_var_raw( 'post_save_helpers', $pod_data ),
                    'pre_delete_helpers' => pods_var_raw( 'pre_delete_helpers', $pod_data ),
                    'post_delete_helpers' => pods_var_raw( 'post_delete_helpers', $pod_data ),
                    'show_in_menu' => ( 1 == pods_var_raw( 'show_in_menu', $pod_data, 0 ) ? 1 : 0 ),
                    'detail_url' => pods_var_raw( 'detail_url', $pod_data ),
                    'pod_index' => 'name'
                ),
            );
        }
        
    }



    static function sync_rename($params, $pod, $pod_data)
    {
        global $wpdb;

        if ( null !== $old_name && $old_name != $params->name && $db ) {
            // Rename items in the DB pointed at the old WP Object names
            if ( 'post_type' == $pod[ 'type' ] && empty( $pod[ 'object' ] ) ) {
                $this->rename_wp_object_type( 'post', $old_name, $params->name );
            }
            elseif ( 'taxonomy' == $pod[ 'type' ] && empty( $pod[ 'object' ] ) ) {
                $this->rename_wp_object_type( 'taxonomy', $old_name, $params->name );
            }
            elseif ( 'comment' == $pod[ 'type' ] && empty( $pod[ 'object' ] ) ) {
                $this->rename_wp_object_type( 'comment', $old_name, $params->name );
            }
            elseif ( 'settings' == $pod[ 'type' ] ) {
                $this->rename_wp_object_type( 'settings', $old_name, $params->name );
            }

            // Sync any related fields if the name has changed
            $fields = pods_query( "
                SELECT `p`.`ID`
                FROM `{$wpdb->posts}` AS `p`
                LEFT JOIN `{$wpdb->postmeta}` AS `pm` ON `pm`.`post_id` = `p`.`ID`
                LEFT JOIN `{$wpdb->postmeta}` AS `pm2` ON `pm2`.`post_id` = `p`.`ID`
                WHERE
                    `p`.`post_type` = '_pods_field'
                    AND `pm`.`meta_key` = 'pick_object'
                    AND (
                        `pm`.`meta_value` = 'pod'
                        OR `pm`.`meta_value` = '" . $pod[ 'type' ] . "'
                    )
                    AND `pm2`.`meta_key` = 'pick_val'
                    AND `pm2`.`meta_value` = '{$old_name}'
            " );

            if ( !empty( $fields ) ) {
                foreach ( $fields as $field ) {
                    update_post_meta( $field->ID, 'pick_object', $pod[ 'type' ] );
                    update_post_meta( $field->ID, 'pick_val', $params->name );
                }
            }

            $fields = pods_query( "
                SELECT `p`.`ID`
                FROM `{$wpdb->posts}` AS `p`
                LEFT JOIN `{$wpdb->postmeta}` AS `pm` ON `pm`.`post_id` = `p`.`ID`
                WHERE
                    `p`.`post_type` = '_pods_field'
                    AND `pm`.`meta_key` = 'pick_object'
                    AND (
                        `pm`.`meta_value` = 'pod-{$old_name}'
                        OR `pm`.`meta_value` = '" . $pod[ 'type' ] . "-{$old_name}'
                    )
            " );

            if ( !empty( $fields ) ) {
                foreach ( $fields as $field ) {
                    update_post_meta( $field->ID, 'pick_object', $pod[ 'type' ] );
                    update_post_meta( $field->ID, 'pick_val', $params->name );
                }
            }
        }
    }
}