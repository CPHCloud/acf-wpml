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
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function acf_lang_update_value($value, $post_id, $field){

		global $sitepress;
		if(!$sitepress)
			return $value;

		if($field['translateable'])
			return $value;

		global $post;

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

		return $value;

	}


	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function acf_lang_render_field_settings($field){

		acf_render_field_setting( $field, array(
			'label'			=> 'Translateable?',
			'instructions'	=> 'Make this field translateable by WPML',
			'type'			=> 'true_false',
			'name'			=> 'translateable'
		));

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