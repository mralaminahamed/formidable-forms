<?php

class FrmDb{
    var $fields;
    var $forms;
    var $entries;
    var $entry_metas;

    public function __construct(){
        if ( ! defined('ABSPATH') ) {
            die('You are not allowed to call this page directly.');
        }

        global $wpdb;
        $this->fields         = $wpdb->prefix . 'frm_fields';
        $this->forms          = $wpdb->prefix . 'frm_forms';
        $this->entries        = $wpdb->prefix . 'frm_items';
        $this->entry_metas    = $wpdb->prefix . 'frm_item_metas';
    }

    public function upgrade( $old_db_version = false ) {
        global $wpdb;
        //$frm_db_version is the version of the database we're moving to
        $frm_db_version = FrmAppHelper::$db_version;
        $old_db_version = (float) $old_db_version;
        if ( ! $old_db_version ) {
            $old_db_version = get_option('frm_db_version');
        }

        if ( $frm_db_version != $old_db_version ) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $this->create_tables();
            $this->migrate_data($frm_db_version, $old_db_version);

            /**** ADD/UPDATE DEFAULT TEMPLATES ****/
            FrmXMLController::add_default_templates();

            /***** SAVE DB VERSION *****/
            update_option('frm_db_version', $frm_db_version);
        }

        do_action('frm_after_install');

        /**** update the styling settings ****/
        $frm_style = new FrmStyle();
        $frm_style->update( 'default' );
    }

    public function collation() {
        global $wpdb;
        if ( ! $wpdb->has_cap( 'collation' ) ) {
            return '';
        }

        $charset_collate = '';
        if ( ! empty($wpdb->charset) ) {
            $charset_collate = ' DEFAULT CHARACTER SET '. $wpdb->charset;
        }

        if ( ! empty($wpdb->collate) ) {
            $charset_collate .= ' COLLATE '. $wpdb->collate;
        }

        return $charset_collate;
    }

    private function create_tables() {
        $charset_collate = $this->collation();
        $sql = array();

        /* Create/Upgrade Fields Table */
        $sql[] = 'CREATE TABLE '. $this->fields .' (
                id int(11) NOT NULL auto_increment,
                field_key varchar(255) default NULL,
                name text default NULL,
                description text default NULL,
                type text default NULL,
                default_value longtext default NULL,
                options longtext default NULL,
                field_order int(11) default 0,
                required int(1) default NULL,
                field_options longtext default NULL,
                form_id int(11) default NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY form_id (form_id),
                UNIQUE KEY field_key (field_key)
        )';

        /* Create/Upgrade Forms Table */
        $sql[] = 'CREATE TABLE '. $this->forms .' (
                id int(11) NOT NULL auto_increment,
                form_key varchar(255) default NULL,
                name varchar(255) default NULL,
                description text default NULL,
                parent_form_id int(11) default NULL,
                logged_in tinyint(1) default NULL,
                editable tinyint(1) default NULL,
                is_template tinyint(1) default 0,
                default_template tinyint(1) default 0,
                status varchar(255) default NULL,
                options longtext default NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY form_key (form_key)
        )';

        /* Create/Upgrade Items Table */
        $sql[] = 'CREATE TABLE '. $this->entries .' (
                id int(11) NOT NULL auto_increment,
                item_key varchar(255) default NULL,
                name varchar(255) default NULL,
                description text default NULL,
                ip text default NULL,
                form_id int(11) default NULL,
                post_id int(11) default NULL,
                user_id int(11) default NULL,
                parent_item_id int(11) default NULL,
                is_draft tinyint(1) default 0,
                updated_by int(11) default NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY form_id (form_id),
                KEY post_id (post_id),
                KEY user_id (user_id),
                KEY parent_item_id (parent_item_id),
                UNIQUE KEY item_key (item_key)
        )';

        /* Create/Upgrade Meta Table */
        $sql[] = 'CREATE TABLE '. $this->entry_metas .' (
                id int(11) NOT NULL auto_increment,
                meta_value longtext default NULL,
                field_id int(11) NOT NULL,
                item_id int(11) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY field_id (field_id),
                KEY item_id (item_id)
        )';

        foreach ( $sql as $q ) {
            dbDelta($q . $charset_collate .';');
            unset($q);
        }
    }

    /**
     * @param integer $frm_db_version
     */
    private function migrate_data($frm_db_version, $old_db_version) {
        $migrations = array(4, 6, 11, 16, 17);
        foreach ( $migrations as $migration ) {
            if ( $frm_db_version >= $migration && $old_db_version < $migration ) {
                $function_name = 'migrate_to_'. $migration;
                $this->$function_name();
            }
        }
    }

    /**
     * Change array into format $wpdb->prepare can use
     */
    public static function get_where_clause_and_values( &$args ) {
        if ( empty($args) ) {
            // if there are no arguments, add one to prevent prepare from failing
            $args = array( 1 => 1 );
        }

        $where = '';
        $values = array();
        if ( ! is_array( $args ) ) {
            $args = compact('where', 'values');
            return;
        }

        foreach ( $args as $key => $value ) {
            $where .= empty($where) ? ' WHERE' : ' AND';
            $where .= ' '. $key;
            if ( is_array($value) ) {
                $where .= ' in ('. FrmAppHelper::prepare_array_values( $value, '%s' ) .')';
                $values = array_merge( $values, $value );
            } else {
                $where .= '=';
                $where .= is_numeric($value) ? ( strpos($value, '.') !== false ? '%f' : '%d' ) : '%s';
                $values[] = $value;
            }
        }

        $args = compact('where', 'values');
    }

    public static function get_count( $table, $where = array(), $args = array() ) {
        return self::get_var( $table, $where, 'COUNT(*)', $args );
    }

    public static function get_var( $table, $where = array(), $field = 'id', $args = array(), $limit = '', $type = 'var' ) {
        $group = '';
        self::get_group_and_table_name( $table, $group );
        self::get_where_clause_and_values( $where );
        self::convert_options_to_array( $args, '', $limit );

        global $wpdb;
        $query = $wpdb->prepare('SELECT '. $field .' FROM '. $table . $where['where'] .' '. implode(' ', $args), $where['values']);

        $cache_key = implode('_', FrmAppHelper::array_flatten( $where ) ) . str_replace( array(' ', ','), '_', implode('_', $args) ) . $field .'_'. $type;
        $results = FrmAppHelper::check_cache( $cache_key, $group, $query, 'get_'. $type );
        return $results;
    }

    /**
     * @param string $table
     * @param array $where
     */
    public static function get_col( $table, $where = array(), $field = 'id', $args = array(), $limit = '' ) {
        return self::get_var( $table, $where, $field, $args, $limit, 'col' );
    }

    /**
     * @since 2.0
     */
    public static function get_row( $table, $where = array(), $fields = '*', $args = array() ) {
        $args['limit'] = 1;
        return self::get_var( $table, $where, $fields, $args, '', 'row' );
    }

    /**
     * @param string $table
     */
    public static function get_one_record( $table, $args = array(), $fields = '*', $order_by = '' ) {
        _deprecated_function( __FUNCTION__, '2.0', 'FrmDb::get_row' );
        return self::get_var( $table, $args, $fields, array('order_by' => $order_by, 'limit' => 1), '', 'row' );
    }

    public static function get_records( $table, $args = array(), $order_by = '', $limit = '', $fields = '*' ) {
        _deprecated_function( __FUNCTION__, '2.0', 'FrmDb::get_results' );
        return self::get_results( $table, $args, $fields, compact('order_by', 'limit') );
    }

    /**
     * Prepare a key/value array before DB call
     * @since 2.0
     * @param string $table
     */
    public static function get_results( $table, $where = array(), $fields = '*', $args = array() ) {
        return self::get_var( $table, $where, $fields, $args, '', 'results' );
    }

    /*
    * Get 'frm_forms' from wp_frm_forms or a longer table param that includes a join
    * Also add the wpdb->prefix to the table if it's missing
    */
    private static function get_group_and_table_name( &$table, &$group ) {
        global $wpdb;

        $table_parts = explode(' ', $table);
        $group = reset($table_parts);
        $group = str_replace( $wpdb->prefix, '', $group );

        if ( $group == $table ) {
            $table = $wpdb->prefix . $table;
        }
    }

    private static function convert_options_to_array( &$args, $order_by = '', $limit = '' ) {
        if ( ! is_array($args) ) {
            $args = array('order_by' => $args);
        }

        if ( ! empty( $order_by ) ) {
            $args['order_by'] = $order_by;
        }

        if ( ! empty( $limit ) ) {
            $args['limit'] = $limit;
        }

        $temp_args = $args;
        foreach ( $temp_args as $k => $v ) {
            if ( $k == 'limit' ) {
                 $args[$k] = FrmAppHelper::esc_limit( $v );
            }
            $db_name = strtoupper( str_replace( '_', ' ', $k ) );
            if ( strpos( $v, $db_name ) === false ) {
                $args[$k] = $db_name .' '. $v;
            }
        }
    }

    public function uninstall(){
        if ( !current_user_can('administrator') ) {
            $frm_settings = FrmAppHelper::get_settings();
            wp_die($frm_settings->admin_permission);
        }

        global $wpdb, $wp_roles;

        $wpdb->query( 'DROP TABLE IF EXISTS '. $this->fields );
        $wpdb->query( 'DROP TABLE IF EXISTS '. $this->forms );
        $wpdb->query( 'DROP TABLE IF EXISTS '. $this->entries );
        $wpdb->query( 'DROP TABLE IF EXISTS '. $this->entry_metas );

        delete_option('frm_options');
        delete_option('frm_db_version');

        //delete roles
        $frm_roles = FrmAppHelper::frm_capabilities();
        $roles = get_editable_roles();
        foreach ( $frm_roles as $frm_role => $frm_role_description ) {
            foreach ( $roles as $role => $details ) {
                $wp_roles->remove_cap( $role, $frm_role );
                unset($role, $details);
    		}
    		unset($frm_role, $frm_role_description);
		}
		unset($roles, $frm_roles);

        do_action('frm_after_uninstall');
        return true;
    }

    /*
    * Change field size from character to pixel -- Multiply by 7.08
    */
    private function migrate_to_17() {
        global $wpdb;

        // Get query arguments
        $query_args = $field_types = array('textarea', 'text', 'number', 'email', 'url', 'rte', 'date', 'phone', 'password', 'image', 'tag', 'file');
        $query_args = array_merge( $query_args, array( '%'. FrmAppHelper::esc_like('s:4:"size";') .'%', '%'. FrmAppHelper::esc_like('s:4:"size";s:0:') .'%' ) );

        // Prepare query
        $query = $wpdb->prepare( 'SELECT id, field_options FROM ' . $this->fields . ' WHERE type IN (' . FrmAppHelper::prepare_array_values( $field_types, '%s' ) . ') AND field_options LIKE %s AND field_options NOT LIKE %s', $query_args );

        // Get results
        $fields = $wpdb->get_results( $query );

        $updated = 0;
        foreach ( $fields as $f ) {
            $f->field_options = maybe_unserialize($f->field_options);
            if ( empty($f->field_options['size']) || ! is_numeric($f->field_options['size']) ) {
                continue;
            }

            $f->field_options['size'] = round(7.08 * (int) $f->field_options['size']);
            $f->field_options['size'] .= 'px';
            $u = FrmField::update( $f->id, array( 'field_options' => $f->field_options ) );
            if ( $u ) {
                $updated++;
            }
            unset($f);
        }

        // Change the characters in widgets to pixels
        $widgets = get_option('widget_frm_show_form');
        if ( empty($widgets) ) {
            return;
        }

        $widgets = maybe_unserialize($widgets);
        foreach ( $widgets as $k => $widget ) {
            if ( ! is_array($widget) || ! isset($widget['size']) ) {
                continue;
            }
            $size = round(7.08 * (int) $widget['size']);
            $size .= 'px';
            $widgets[$k]['size'] = $size;
        }
        update_option('widget_frm_show_form', $widgets);
    }

    /*
    * Migrate post and email notification settings into actions
    */
    private function migrate_to_16() {
        global $wpdb;

        $forms = FrmDb::get_results( $this->forms, array(), 'id, options' );

        /* Old email settings format:
        * email_to: Email or field id
        * also_email_to: array of fields ids
        * reply_to: Email, field id, 'custom'
        * cust_reply_to: string
        * reply_to_name: field id, 'custom'
        * cust_reply_to_name: string
        * plain_text: 0|1
        * email_message: string or ''
        * email_subject: string or ''
        * inc_user_info: 0|1
        * update_email: 0, 1, 2
        *
        * Old autoresponder settings format:
        * auto_responder: 0|1
        * ar_email_message: string or ''
        * ar_email_to: field id
        * ar_plain_text: 0|1
        * ar_reply_to_name: string
        * ar_reply_to: string
        * ar_email_subject: string
        * ar_update_email: 0, 1, 2
        *
        * New email settings:
        * post_content: json settings
        * post_title: form id
        * post_excerpt: message
        *
        */

        $post_type = FrmFormActionsController::$action_post_type;
        foreach ( $forms as $form ) {
            $form->options = maybe_unserialize($form->options);

            self::migrate_to_16_post_to_action($form, $post_type);

            if ( ! isset($form->options['notification']) && isset($form->options['email_to']) && ! empty($form->options['email_to']) ) {
                // add old settings into notification array
                $form->options['notification'] = array(0 => $form->options);
            } else if ( isset($form->options['notification']['email_to']) ) {
                // make sure it's in the correct format
                $form->options['notification'] = array(0 => $form->options['notification']);
            }

            $notifications = array();
            if ( isset($form->options['notification']) && is_array($form->options['notification']) ) {
                foreach ( $form->options['notification'] as $email_key => $notification ) {

                    // format the email recipient data
                    if ( isset($notification['email_to']) ) {
                        $email_to = preg_split( "/ (,|;) /", $notification['email_to']);
                    } else {
                        $email_to = array();
                    }

                    if ( isset($notification['also_email_to']) ) {
                        $email_fields = (array) $notification['also_email_to'];
                        $email_to = array_merge($email_fields, $email_to);
                        unset($email_fields);
                    }

                    foreach ( $email_to as $key => $email_field ) {

                        if ( is_numeric($email_field) ) {
                            $email_to[$key] = '['. $email_field .']';
                        }

                        if ( strpos( $email_field, '|') ) {
                            $email_opt = explode('|', $email_field);
                            if ( isset($email_opt[0]) ) {
                                $email_to[$key] = '['. $email_opt[0] .' show='. $email_opt[1] .']';
                            }
                            unset($email_opt);
                        }
                    }
                    $email_to = implode(', ', $email_to);

                    // format the reply to email
                    if ( isset($notification['reply_to']) ) {
                        $reply_to = $notification['reply_to'];
                        if ( 'custom' == $notification['reply_to'] ) {
                            $reply_to = $notification['cust_reply_to'];
                        } else if ( is_numeric($reply_to) && ! empty($reply_to) ) {
                            $reply_to = '['. $reply_to .']';
                        }
                    }

                    // format the reply to name
                    if ( isset($notification['reply_to_name']) ) {
                        $reply_to_name = $notification['reply_to_name'];
                        if ( 'custom' == $notification['reply_to_name'] ) {
                            $reply_to_name = $notification['cust_reply_to_name'];
                        } else if ( ! is_numeric( $reply_to_name ) && ! empty( $reply_to_name ) ) {
                            $reply_to_name = '['. $reply_to_name .']';
                        }
                    }

                    $event = array('create');
                    if ( isset($notification['update_email']) && 1 == $notification['update_email'] ) {
                        $event[] = 'update';
                    } else if ( isset($notification['update_email']) && 2 == $notification['update_email'] ) {
                        $event = array('update');
                    }

                    $new_notification = array(
                        'post_content'  => array(
                            'email_message' => isset($notification['email_message']) ? $notification['email_message'] : '',
                            'email_subject' => isset($notification['email_subject']) ? $notification['email_subject'] : '',
                            'email_to'      => $email_to,
                            'plain_text'    => isset($notification['plain_text']) ? $notification['plain_text'] : 0,
                            'inc_user_info' => isset($notification['inc_user_info']) ? $notification['inc_user_info'] : 0,
                            'event'         => $event,
                            'conditions'    => isset($notification['conditions']) ? $notification['conditions'] : '',
                        ),
                        'post_name'         => $form->id .'_email_'. $email_key,
                    );

                    if ( isset($notification['twilio']) && $notification['twilio'] ) {
                        $new_notification['post_content'] = $notification['twilio'];
                    }

                    if ( ! empty( $reply_to ) ) {
                       $new_notification['post_content']['reply_to'] = $reply_to;
                    }

                    if ( ! empty( $reply_to ) || ! empty( $reply_to_name ) ) {
                        $new_notification['post_content']['from'] = ( empty($reply_to_name) ? '[sitename]' : $reply_to_name ) .' <'. ( empty($reply_to) ? '[admin_email]' : $reply_to ) .'>';
                    }

                    $notifications[] = $new_notification;
                }
            }

            if ( isset($form->options['auto_responder']) && $form->options['auto_responder'] && isset($form->options['ar_email_message']) && $form->options['ar_email_message'] ) {
                // migrate autoresponder

                $email_field = isset($form->options['ar_email_to']) ? $form->options['ar_email_to'] : 0;
                if ( strpos($email_field, '|') ) {
                    // data from entries field
                    $email_field = explode('|', $email_field);
                    if ( isset($email_field[1]) ) {
                        $email_field = $email_field[1];
                    }
                }
                if ( is_numeric($email_field) && ! empty($email_field) ) {
                    $email_field = '['. $email_field .']';
                }

                $notification = $form->options;
                $new_notification2 = array(
                    'post_content'  => array(
                        'email_message' => $notification['ar_email_message'],
                        'email_subject' => isset($notification['ar_email_subject']) ? $notification['ar_email_subject'] : '',
                        'email_to'      => $email_field,
                        'plain_text'    => isset($notification['ar_plain_text']) ? $notification['ar_plain_text'] : 0,
                        'inc_user_info' => 0,
                    ),
                    'post_name'     => $form->id .'_email_'. (isset($new_notification) ? '1' : '0'),
                );


                $reply_to = isset($notification['ar_reply_to']) ? $notification['ar_reply_to'] : '';
                $reply_to_name = isset($notification['ar_reply_to_name']) ? $notification['ar_reply_to_name'] : '';

                if ( ! empty( $reply_to ) ) {
                   $new_notification2['post_content']['reply_to'] = $reply_to;
                }

                if ( ! empty( $reply_to ) || ! empty( $reply_to_name ) ) {
                    $new_notification2['post_content']['from'] = ( empty($reply_to_name) ? '[sitename]' : $reply_to_name ) .' <'. ( empty($reply_to) ? '[admin_email]' : $reply_to ) .'>';
                }

                $notifications[] = $new_notification2;
                unset($new_notification2);
            }

            if (  empty($notifications) ) {
                continue;
            }

            foreach ( $notifications as $new_notification ) {
                $new_notification['post_type']      = $post_type;
                $new_notification['post_excerpt']   = 'email';
                $new_notification['post_title']     = __( 'Email Notification', 'formidable' );
                $new_notification['menu_order']     = $form->id;
                $new_notification['post_status']    = 'publish';
                $new_notification['post_content']   = FrmAppHelper::prepare_and_encode( $new_notification['post_content'] );

                $exists = get_posts( array(
                    'name'          => $new_notification['post_name'],
                    'post_type'     => $new_notification['post_type'],
                    'post_status'   => $new_notification['post_status'],
                    'numberposts'   => 1,
                ) );

                if ( empty($exists) ) {
                    wp_insert_post($new_notification);
                }
                unset($new_notification);
            }

            unset($form, $new_notification2);
        }
    }

    /*
    * Migrate post settings to form action
    */

    /**
     * @param string $post_type
     */
    private function migrate_to_16_post_to_action( $form, $post_type ) {
        if ( ! isset($form->options['create_post']) || ! $form->options['create_post'] ) {
            return;
        }

        $new_action = array(
            'post_type'     => $post_type,
            'post_excerpt'  => 'wppost',
            'post_title'    => __( 'Create Posts', 'formidable' ),
            'menu_order'    => $form->id,
            'post_status'   => 'publish',
            'post_content'  => array(),
            'post_name'     => $form->id .'_wppost_1',
        );

        $post_settings = array(
            'post_type', 'post_category', 'post_content',
            'post_excerpt', 'post_title', 'post_name', 'post_date',
            'post_status', 'post_custom_fields', 'post_password'
        );

        foreach ( $post_settings as $post_setting ) {
            if ( isset($form->options[$post_setting]) ) {
                $new_action['post_content'][$post_setting] = $form->options[$post_setting];
            }
            unset($post_setting);
        }

        $new_action['event'] = array('create', 'update');
        $new_action['post_content'] = json_encode($new_action['post_content']);

        $exists = get_posts( array(
            'name'          => $new_action['post_name'],
            'post_type'     => $new_action['post_type'],
            'post_status'   => $new_action['post_status'],
            'numberposts'   => 1,
        ) );

        if ( ! $exists ) {
            wp_insert_post($new_action);
        }
    }

    private function migrate_to_11() {
        global $wpdb;

        $forms = FrmDb::get_results( $this->forms, array(), 'id, options');

        $sending = __( 'Sending', 'formidable' );
        $img = FrmAppHelper::plugin_url() .'/images/ajax_loader.gif';
        $old_default_html = <<<DEFAULT_HTML
<div class="frm_submit">
[if back_button]<input type="submit" value="[back_label]" name="frm_prev_page" formnovalidate="formnovalidate" [back_hook] />[/if back_button]
<input type="submit" value="[button_label]" [button_action] />
<img class="frm_ajax_loading" src="$img" alt="$sending" style="visibility:hidden;" />
</div>
DEFAULT_HTML;
        unset($sending, $img);

        $new_default_html = FrmFormsHelper::get_default_html('submit');
        $draft_link = FrmFormsHelper::get_draft_link();
        foreach($forms as $form){
            $form->options = maybe_unserialize($form->options);
            if ( ! isset($form->options['submit_html']) || empty($form->options['submit_html']) ) {
                continue;
            }

            if ( $form->options['submit_html'] != $new_default_html && $form->options['submit_html'] == $old_default_html ) {
                $form->options['submit_html'] = $new_default_html;
                $wpdb->update($this->forms, array('options' => serialize($form->options)), array( 'id' => $form->id ));
            }else if(!strpos($form->options['submit_html'], 'save_draft')){
                $form->options['submit_html'] = preg_replace('~\<\/div\>(?!.*\<\/div\>)~', $draft_link ."\r\n</div>", $form->options['submit_html']);
                $wpdb->update($this->forms, array('options' => serialize($form->options)), array( 'id' => $form->id ));
            }
            unset($form);
        }
        unset($forms);
    }

    private function migrate_to_6() {
        global $wpdb;

        $no_save = array_merge( FrmFieldsHelper::no_save_fields(), array( 'form', 'hidden', 'user_id' ) );
        $fields = FrmDb::get_results( $this->fields, array('type NOT' => $no_save), 'id, field_options' );

        $default_html = <<<DEFAULT_HTML
<div id="frm_field_[id]_container" class="form-field [required_class] [error_class]">
    <label class="frm_pos_[label_position]">[field_name]
        <span class="frm_required">[required_label]</span>
    </label>
    [input]
    [if description]<div class="frm_description">[description]</div>[/if description]
</div>
DEFAULT_HTML;

        $old_default_html = <<<DEFAULT_HTML
<div id="frm_field_[id]_container" class="form-field [required_class] [error_class]">
    <label class="frm_pos_[label_position]">[field_name]
        <span class="frm_required">[required_label]</span>
    </label>
    [input]
    [if description]<p class="frm_description">[description]</p>[/if description]
</div>
DEFAULT_HTML;

        $new_default_html = FrmFieldsHelper::get_default_html('text');
        foreach ( $fields as $field ) {
            $field->field_options = maybe_unserialize($field->field_options);
            if ( ! isset( $field->field_options['custom_html'] ) || empty( $field->field_options['custom_html'] ) || $field->field_options['custom_html'] == $default_html || $field->field_options['custom_html'] == $old_default_html ) {
                $field->field_options['custom_html'] = $new_default_html;
                $wpdb->update($this->fields, array('field_options' => maybe_serialize($field->field_options)), array( 'id' => $field->id ));
            }
            unset($field);
        }
        unset($default_html, $old_default_html, $fields);
    }

    private function migrate_to_4() {
        global $wpdb;
        $user_ids = FrmEntryMeta::getAll(array('fi.type' => 'user_id'));
        foreach ( $user_ids as $user_id ) {
            $wpdb->update( $this->entries, array('user_id' => $user_id->meta_value), array('id' => $user_id->item_id) );
        }
    }
}
