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

        private $additional_data = [], $file = '';

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
            if(!isset($_POST[$name])){
                return '';
            }
            $value = wp_unslash($_POST[$name]);
    		$value = $this->sanitize_posted_data($value);
            return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_functions_loaded(){
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7', $this->file, 'bc-cf7');
            if(!bc_is_plugin_active('contact-form-7/wp-contact-form-7.php')){
                add_action('admin_notices', function(){
                    echo bc_admin_notice(printf(__('No plugins found for: %s.'),'<strong>Contact Form 7</strong>'));
                });
        		return;
        	}
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data']);
            add_filter('wpcf7_posted_data_checkbox', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_checkbox*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            do_action('bc_cf7_loaded');
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
            if(0 === $object_id){
                return false;
            }
            if(!in_array($meta_type, ['post', 'user'])){
                return false;
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
                    update_metadata($meta_type, $object_id, $key, $value);
                }
                do_action('bc_cf7_meta_data_updated', $meta_data, $meta_type, $object_id);
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
                do_action('bc_cf7_posted_data_updated', $posted_data, $meta_type, $object_id);
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
                do_action('bc_cf7_uploaded_files_updated', $uploaded_files, $meta_type, $object_id);
            }
            do_action('bc_cf7_updated', $meta_type, $object_id);
            return true;
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

    }
}
if(!function_exists('bc_cf7')){
    function bc_cf7(){
        return BC_CF7::get_instance();
    }
}
