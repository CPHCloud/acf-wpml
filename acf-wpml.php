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

		add_filter('acf/load_field', array($this, 'allow_edits_in_default_language_only'), 100, 1);
	}


	/**
	 * TO DO: Documentation
	 *
	 * @return void
	 **/
	function allow_edits_in_default_language_only($field){
		
		global $sitepress;
		if(!$sitepress)
			return $field;

		$field_name = $field['_name'];

		if(is_admin() and is_array($field['edit_in_languages']) and !in_array($sitepress->get_current_language(), $field['edit_in_languages'])){
			unset($field['instructions']);
			$field['type'] 		= 'message';
			$field['message'] 	= 'This field is unavailable for editing in this language.';
		}
		return $field;
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
			
			$trid 			= $sitepress->get_element_trid($post->ID, 'post_' . $post->post_type);
			$translations 	= $sitepress->get_element_translations($trid, 'post_' . $post->post_type);
			unset($translations[$sitepress->get_current_language()]);

			if($translations){
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
		$langs = icl_get_languages();

		foreach ($langs as &$lang) {
			$lang_choices[$lang['language_code']] = $lang['translated_name'];
		}
		$lang_keys = array_keys($lang_choices);

		if(!isset($field['edit_in_languages']))
			$field['edit_in_languages'] = $lang_keys;

		acf_render_field_setting( $field, array(
			'label'			=> 'Translateable?',
			'instructions'	=> 'Make this field translateable by WPML',
			'type'			=> 'true_false',
			'name'			=> 'translateable'
		));

		acf_render_field_setting( $field, array(
			'label'			=> 'Edit in languages',
			'instructions'	=> 'Removing a language from this list will prevent editing when on that language.',
			'type'			=> 'checkbox',
			'layout'		=> 'vertical',
			'choices'		=> $lang_choices,
			'default' 		=> $lang_keys,
			'name'			=> 'edit_in_languages'
		));

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