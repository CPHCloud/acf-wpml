<?php

/*
Plugin Name: ACF WPML
Description: ACF and WPML sitting in a tree
Author: @supertroels
*/


class acf_wpml {
	
	function __construct(){
		$this->hooks();
	}

	/**
	 * Registers all the main hooks for this plugin
	 *
	 * @return void
	 **/
	function hooks(){		
		/* Per field language support for ACF */
		add_filter('acf/render_field_settings', array($this, 'acf_lang_render_field_settings'), 10);
		
		add_filter('acf/update_value', array($this, 'acf_lang_update_value'), 50, 3);
		add_filter('acf/load_value', array($this, 'acf_lang_load_value'), 50, 3);

		add_filter('acf/load_field', array($this, 'mark_field'), 1, 1);

		add_filter('acf/fields/post_object/query', array($this, 'acf_set_forced_lang'), 1, 3);
		add_filter('acf/fields/relationship/query', array($this, 'acf_set_forced_lang'), 1, 3);

		//add_filter('acf/fields/relationship/query', array($this, 'acf/fields/relationship/query'), 50, 3);

	}


	function is_acf(){
		
		$array = array('acf-field-group', 'acf-field');
		if(in_array(get_post_type(), $array))
			return true;

		return false;

	}


	/**
	 * TO DO: Documentation
	 *
	 * @return void
	 **/
	function acf_set_forced_lang($args, $field, $post){

		global $sitepress;
		
		if($lang = $field['result_lang'] and $lang != 'current' and $sitepress and $sitepress->get_current_language() != $lang){

			/*
			**************
			IMPORTANT NOTE
			**************
			This assumes that there is at least
			one post of any post type with the language
			set to that which is requested.
			*/

			$this->switch_to_language($lang);
			global $wpdb;

			$query = "SELECT wp_posts.ID
			FROM wp_posts
			LEFT JOIN wp_icl_translations as t
			ON wp_posts.ID = t.element_id
			WHERE t.language_code = '$lang'
			LIMIT 1";

			if($result = $wpdb->get_results($query))
					$_REQUEST[ 'post_id' ] = $result[0]->ID;

		}

		return $args;

	}


	/**
	 * TO DO: Documentation
	 *
	 * @return void
	 **/
	function mark_field($field){
		

		global $sitepress;
		if(!$sitepress)
			return $field;

		if($field['translateable'])
			return $field;
		
		if($true_lang = $_GET['lang']){
			$sitepress->switch_lang($true_lang);
		}

		if(is_admin() and !$this->is_acf() and $sitepress->get_current_language() != $sitepress->get_default_language()){

			if(!$field['wrapper']['class'])
				$field['wrapper']['class'] = '';
			$field['wrapper']['class'] .= ' shared_field';

			$field = apply_filters('acf_wpml/field_unavailable', $field);

		}

		return $field;

	}


	/**
	 * TO DO: Documentation
	 *
	 * @return void
	 **/
	function acf_lang_load_value($value, $post_id, $field){

		global $sitepress;
		
		if(!$sitepress)
			return $value;

		if($field['translateable'])
			return $value;

		$post = get_post($post_id);

		if($trans_id = icl_object_id($post->ID, $post->post_type, false, $sitepress->get_default_language())){
			$value = acf_get_value($trans_id, $field, true);
		}

		return $value;
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function acf_lang_update_value($value, $post_id, $field){

		global $sitepress, $post;
		if(!$sitepress)
			return $value;

		if($field['translateable'])
			return $value;

		if($post_id == 'options' or $post_id == 'options_'.$sitepress->get_current_language()){

			$languages = $this->get_languages();
			unset($langs[$sitepress->get_current_language()]);

			foreach($languages as $lang){

				$options_id = 'options';
				if($lang != $sitepress->get_default_language()){
					$options_id .= '_'.$lang;
				}

				remove_filter('acf/update_value', array($this, 'acf_lang_update_value'), 50, 3);
				$this->switch_to_language($lang);
				acf_update_value($value, $options_id, $field);
				$this->switch_language_back();				
				add_filter('acf/update_value', array($this, 'acf_lang_update_value'), 50, 3);

			}

		}
		else{
			
			/*
			We have already checked for translations and none
			were found. Let's return the $value.
			*/
			if($this->has_checked and !$this->translations)
				return $value;


			/*
			Check for translations
			*/
			if(!$translations = $this->translations){
				
				/* No translations were found. Let's check for any. */
				$trid 			= $sitepress->get_element_trid($post->ID, 'post_' . $post->post_type);
				$translations 	= $sitepress->get_element_translations($trid, 'post_' . $post->post_type);
				unset($translations[$sitepress->get_current_language()]);
				
				/* Do this for caching */
				$this->has_checked = true;
				if($translations){
					$this->translations = $translations;
				}

			}


			/* Are there translations */
			if($translations){


				/* Yes there is! Loop through them and update their values */
				$i = 0;
				foreach($translations as $translation){
		
					remove_filter('acf/update_value', array($this, 'acf_lang_update_value'), 50, 3);
					$this->switch_to_language($translation->language_code);
					acf_update_value($value, $translation->element_id, $field);
					$this->switch_language_back();
					add_filter('acf/update_value', array($this, 'acf_lang_update_value'), 50, 3);

				}
			}

		}

		return $value;

	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function acf_lang_render_field_settings($field){

		global $sitepress;

		acf_render_field_setting( $field, array(
			'label'			=> 'Translateable?',
			'instructions'	=> 'Make this field translateable by WPML',
			'type'			=> 'true_false',
			'name'			=> 'translateable'
		));


		$langfields = array('post_object', 'relationship', 'page_link');
		if(in_array($field['type'], $langfields)){

			$languages = $this->get_languages(false);

			$choices = array(
				'current' => 'Active language'
				);

			foreach($languages as $lang){
				$choices[$lang['tag']] = $lang['native_name'];
			}

			acf_render_field_setting( $field, array(
				'label'			=> 'Result language',
				'instructions'	=> 'Force results from this language',
				'type'			=> 'select',
				'name'			=> 'result_lang',
				'choices' 		=> $choices
			));

		}


	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function get_languages($keys_only = true){
		if($langs = icl_get_languages()){

			/* Keys only? */
			if($keys_only)
				$langs = array_keys($langs);
		}
		else{
			/* No langs. Make an empty array tp return */
			$langs = array();
		}

		return $langs;	
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function switch_to_default_language(){
		global $sitepress;
		$def_lang 			= $sitepress->get_default_language();
		$this->curr_lang 	= $sitepress->get_current_language();
		$sitepress->switch_lang($def_lang);

	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function switch_to_language($lang){
		global $sitepress;
		$this->curr_lang 	= $sitepress->get_current_language();
		$sitepress->switch_lang($lang);

	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function switch_language_back(){
		global $sitepress;
		$sitepress->switch_lang($this->curr_lang);
	}


}

/* Instance function */
function acf_wpml(){
	if(!$GLOBALS['__acf_wpml'])
		$GLOBALS['__acf_wpml'] = new acf_wpml();
	return $GLOBALS['__acf_wpml'];
}

acf_wpml();


?>