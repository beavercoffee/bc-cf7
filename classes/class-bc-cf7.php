<?php

if(!class_exists('BC_CF7')){
    final class BC_CF7 {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public static function get_instance($file = ''){
            if(null !== self::$instance){
                return self::$instance;
            }
            if('' === $file){
                wp_die(__('File doesn&#8217;t exist?'));
            }
            if(!is_file($file)){
                wp_die(sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'), $file));
            }
            self::$instance = new self($file);
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $additional_data = [], $file = '', $posted_data = [];

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('bc_functions_loaded', [$this, 'bc_functions_loaded']);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function free_text_value($name = ''){
            $name .= '_free_text';
            return $this->get_posted_data($name);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function setup_posted_data(){
            $posted_data = array_filter((array) $_POST, function($key){
    			return '_' !== substr($key, 0, 1);
    		}, ARRAY_FILTER_USE_KEY);
            $this->posted_data = $this->sanitize_posted_data($posted_data);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_functions_loaded(){
            if(!bc_is_plugin_active('contact-form-7/wp-contact-form-7.php')){
                add_action('admin_notices', function(){
                    echo bc_admin_notice(printf(__('No plugins found for: %s.'),'<strong>Contact Form 7</strong>'));
                });
        		return;
        	}
            if(!version_compare(WPCF7_VERSION, '5.4', '>=')){
                add_action('admin_notices', function(){
                    echo bc_admin_notice(bc_first_p(printf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.'),'<strong>Contact Form 7</strong>', '', '', '')));
                });
        		return;
            }
            add_action('init', [$this, 'init']);
            add_action('wpcf7_enqueue_scripts', [$this, 'wpcf7_enqueue_scripts']);
            add_filter('pre_delete_post', [$this, 'pre_delete_post'], 10, 3);
            add_filter('wpcf7_autop_or_not', [$this, 'wpcf7_autop_or_not']);
            add_filter('wpcf7_form_elements', 'do_shortcode');
            add_filter('wpcf7_form_tag_data_option', [$this, 'wpcf7_form_tag_data_option'], 10, 3);
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data']);
            add_filter('wpcf7_posted_data_checkbox', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_checkbox*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
                add_filter('wpcf7_remote_ip_addr', [$this, 'wpcf7_remote_ip_addr']);
            }
            if(isset($_SERVER['HTTP_CF_IPCOUNTRY'])){
                add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            }
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7', $this->file, 'bc-cf7');
            do_action('bc_cf7_loaded');
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_if($atts, $content = ''){
            $atts = shortcode_atts([
                'compare' => '=',
        		'key' => '',
        		'type' => 'CHAR',
        		'value' => '',
            ], $atts, 'bc_if');
        	extract($atts);
            if(!in_array($compare, ['!=', '<', '<=', '=', '>', '>=', 'EXISTS', 'LIKE', 'NOT EXISTS', 'NOT LIKE', 'NOT REGEXP', 'REGEXP'])){
                return '';
            }
            if(!in_array($type, ['CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'NUMERIC', 'TIME']){
                return '';
            }
            $content = array_filter(explode('[bc_else]', $content, 2));
            $content_false = isset($content[1]) ? $content[1] : '';
            $content_true = $content[0];
            $posted_data = $this->get_posted_data($key);
            if('' === $posted_data){
                switch($compare){
                    case 'EXISTS':
                        if('' !== $posted_data){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case 'NOT EXISTS':
                        if('' === $posted_data){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    default:
                        return '';
                }
            } else {
                switch($type){
                    case 'DATE':
                        $posted_data = strtotime(date_i18n('Y-m-d', strtotime($posted_data)) . ' 00:00:00');
                        $value = strtotime(date_i18n('Y-m-d', strtotime($value)) . ' 00:00:00');
                        break;
                    case 'DATETIME':
                        $posted_data = strtotime($posted_data);
                        $value = strtotime($value);
                        break;
                    case 'DECIMAL':
                        $posted_data = (float) $posted_data;
                        $value = (float) $value;
                        break;
                    case 'NUMERIC':
                        $posted_data = (int) $posted_data;
                        $value = (int) $value;
                        break;
                    case 'TIME':
                        $posted_data = strtotime('1970-01-01 ' . date_i18n('H:i:s', strtotime($posted_data)));
                        $value = strtotime('1970-01-01 ' . date_i18n('H:i:s', strtotime($value)));
                        break;
                    default:
                        $posted_data = (string) $posted_data;
                        $value = (string) $value;
                }
                switch($compare){
                    case '!=':
                        if($posted_data !== $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case '<':
                        if($posted_data < $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case '<=':
                        if($posted_data <= $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case '=':
                        if($posted_data === $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case '>':
                        if($posted_data > $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case '>=':
                        if($posted_data >= $value){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case 'LIKE':
                        if(false !== strpos($posted_data, $value)){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case 'NOT LIKE':
                        if(false === strpos($posted_data, $value)){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case 'NOT REGEXP':
                        if(0 === preg_match($value, $posted_data)){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    case 'REGEXP':
                        if(1 === preg_match($value, $posted_data)){
                            return $content_true;
                        } else {
                            return $content_false;
                        }
                        break;
                    default:
                        return '';
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function get_posted_data($name = ''){
            if(!$this->posted_data){
                $this->setup_posted_data();
            }
            if('' === $name){
                return $this->posted_data;
            }
            if(!isset($this->posted_data[$name])){
                return '';
            }
            return $this->posted_data[$name];
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function init(){
            add_shortcode('bc_if', [$this, 'bc_if']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function is_true($name = '', $contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return false;
            }
            return $contact_form->is_true($name);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function is_type($type = '', $contact_form = null){
            return $type === $this->type($contact_form);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function mail($contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return false;
            }
            $skip_mail = $this->skip_mail($contact_form);
            if($skip_mail){
            	return true;
            }
            $result = WPCF7_Mail::send($contact_form->prop('mail'), 'mail');
            if(!$result){
                return false;
            }
            $additional_mail = [];
        	if($mail_2 = $contact_form->prop('mail_2') and $mail_2['active']){
        		$additional_mail['mail_2'] = $mail_2;
        	}
        	$additional_mail = apply_filters('wpcf7_additional_mail', $additional_mail, $contact_form);
        	foreach($additional_mail as $name => $template){
        		WPCF7_Mail::send($template, $name);
        	}
        	return true;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function pre_delete_post($delete, $post, $force_delete){
            if('wpcf7_contact_form' !== $post->post_type){
                return $delete;
            }
            return false;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function pref($name = '', $contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return '';
            }
            $setting = $contact_form->pref($name);
            if(null === $setting){
                return '';
            }
            return $setting;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function sanitize_posted_data($value){
            if(is_array($value)){
    			$value = array_map([$this, 'sanitize_posted_data'], $value);
    		} elseif(is_string($value)){
    			$value = wp_check_invalid_utf8($value);
    			$value = wp_kses_no_null($value);
    		}
    		return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            $out['bc_cf_ipcountry'] = $_SERVER['HTTP_CF_IPCOUNTRY'];
            return $out;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function skip_mail($contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return false;
            }
            $skip_mail = ($contact_form->in_demo_mode() or $contact_form->is_true('skip_mail') or !empty($contact_form->skip_mail));
            $skip_mail = apply_filters('wpcf7_skip_mail', $skip_mail, $contact_form);
            return $skip_mail;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function type($contact_form = null){
            return $this->pref('bc_type', $contact_form);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function update($contact_form = null, $submission = null, $meta_type = '', $object_id = 0){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return false;
            }
            if(null === $submission){
                $submission = WPCF7_Submission::get_instance();
            }
            if(null === $submission){
                return false;
            }
            if(!in_array($meta_type, ['post', 'user'])){
                return false;
            }
            if(0 === $object_id){
                return false;
            }
            if('post' === $meta_type){
                $the_post = wp_is_post_revision($object_id);
                if($the_post){
                    $object_id = $the_post; // Make sure meta is added to the post, not a revision.
                }
            }
            $meta_data = [
                'bc_contact_form_id' => $contact_form->id(),
                'bc_contact_form_locale' => $contact_form->locale(),
                'bc_contact_form_name' => $contact_form->name(),
                'bc_contact_form_title' => $contact_form->title(),
                'bc_submission_container_post_id' => $submission->get_meta('container_post_id'),
                'bc_submission_current_user_id' => $submission->get_meta('current_user_id'),
                'bc_submission_remote_ip' => $submission->get_meta('remote_ip'),
                'bc_submission_remote_port' => $submission->get_meta('remote_port'),
                'bc_submission_response' => $submission->get_response(),
                'bc_submission_status' => $submission->get_status(),
                'bc_submission_timestamp' => $submission->get_meta('timestamp'),
                'bc_submission_unit_tag' => $submission->get_meta('unit_tag'),
                'bc_submission_url' => $submission->get_meta('url'),
                'bc_submission_user_agent' => $submission->get_meta('user_agent'),
            ];
            $meta_data = apply_filters('bc_cf7_meta_data', $meta_data, $meta_type, $object_id);
            if($meta_data){
                foreach($meta_data as $key => $value){
                    add_metadata($meta_type, $object_id, $key, $value, true);
                }
            }
            $posted_data = $submission->get_posted_data();
            $posted_data = apply_filters('bc_cf7_posted_data', $posted_data, $meta_type, $object_id);
            if($posted_data){
                foreach($posted_data as $key => $value){
                    if(is_array($value)){
                        delete_metadata($meta_type, $object_id, $key);
                        foreach($value as $single){
                            add_metadata($meta_type, $object_id, $key, $single);
                        }
                    } else {
                        update_metadata($meta_type, $object_id, $key, $value);
                    }
                }
            }
            $uploaded_files = $submission->uploaded_files();
            $uploaded_files = apply_filters('bc_cf7_uploaded_files', $uploaded_files, $meta_type, $object_id);
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    delete_metadata($meta_type, $object_id, $key . '_id');
                    delete_metadata($meta_type, $object_id, $key . '_filename');
                    foreach((array) $value as $single){
                        if('post' === $meta_type){
                            $attachment_id = bc_upload_file($single, $object_id);
                        } else {
                            $attachment_id = bc_upload_file($single);
                        }
                        if(is_wp_error($attachment_id)){
                            add_metadata($meta_type, $object_id, $key . '_id', 0);
                            add_metadata($meta_type, $object_id, $key . '_filename', $attachment_id->get_error_message());
                            do_action('bc_cf7_attachment_error', $attachment_id, $meta_type, $object_id);
                        } else {
                            add_metadata($meta_type, $object_id, $key . '_id', $attachment_id);
                            add_metadata($meta_type, $object_id, $key . '_filename', wp_basename($single));
                            do_action('bc_cf7_add_attachment', $attachment_id, $meta_type, $object_id);
                        }
                    }
                }
            }
            do_action('bc_cf7_updated', $meta_type, $object_id);
            return true;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_autop_or_not($autop){
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $autop;
            }
            if($contact_form->is_true('bc_autop')){
                return $autop;
            }
            return false;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_form_tag_data_option($data, $options, $args){
            $data = (array) $data;
            foreach($options as $option){
                if(strpos($option, 'bc.') !== 0){
                    continue;
                }
                $option = array_filter(explode('.', $option));
				if(isset($option[1]){
                    $data = apply_filters("bc_data_option_{$option[1]}", $data);
                    $data = (array) $data;
				}
			}
			return $data;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_enqueue_scripts(){
            $src = plugin_dir_url($this->file) . 'assets/bc-cf7.js';
            $ver = filemtime(plugin_dir_path($this->file) . 'assets/bc-cf7.js');
            wp_enqueue_script('bc-cf7', $src, ['contact-form-7'], $ver, true);
        	if(isset($_SERVER['HTTP_USER_AGENT']) and false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile')){
        		wp_add_inline_script('bc-cf7', 'bc_cf7.mobile();');
        	}
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data($posted_data){
            if($this->additional_data){
                $posted_data = array_merge($posted_data, $this->additional_data);
            }
            return $posted_data;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data_type($value, $value_orig, $tag){
			$name = $tag->name;
            $pipes = $tag->pipes;
            $type = $tag->type;
			if(wpcf7_form_tag_supports($type, 'selectable-values')){
                $value = (array) $value;
                $value_orig = (array) $value_orig;
				if($tag->has_option('free_text')){
        			$last_val = array_pop($value);
					list($tied_item) = array_slice(WPCF7_USE_PIPE ? $tag->pipes->collect_afters() : $tag->values, -1, 1);
					$tied_item = html_entity_decode($tied_item, ENT_QUOTES, 'UTF-8');
					if(strpos($last_val, $tied_item) === 0){
						$value[] = $tied_item;
                        $this->additional_data[$name . '_free_text'] = $this->free_text_value($name);
                        $this->additional_data[$name . '_raw'] = $last_val;
					} else {
						$value[] = $last_val;
						$this->additional_data[$name . '_free_text'] = '';
                        $this->additional_data[$name . '_raw'] = '';
					}
                }
            }
			if(WPCF7_USE_PIPE and $pipes instanceof WPCF7_Pipes and !$pipes->zero()){
				$this->additional_data[$name . '_value'] = $value;
				$value = $value_orig;
            }
            return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_remote_ip_addr($ip_addr){
            $ip_addr = $_SERVER['HTTP_CF_CONNECTING_IP'];
            return $ip_addr;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('bc_cf7')){
    function bc_cf7(){
        return BC_CF7::get_instance();
    }
}
