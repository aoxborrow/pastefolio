<?php

class Index_Controller extends Template_Controller {

	public function index() {

		// mustache not really needed for these static pages
		$this->template->content = file_get_contents(APPPATH.'views/index.mustache');

	}
	
}