<?php

// page model
class Page {

	// content file extension
	public static $ext = '.html';

	// path to page content
	public $path;

	// TODO: allow setting this in section variable
	// default mustache template, relative to TEMPLATEPATH
	public $template;

	// page name and link id
	public $name;

	// page title, used in menu
	public $title;

	// page description, optional
	public $description;

	// page content
	public $content;

	// redirect URL for creating aliases
	public $redirect;

	// thumbnail for gallery display
	public $thumb;

	// section
	public $section;

	// parent section
	public $parent;

	// page is a section index
	public $is_section = FALSE;

	// visible in menu
	public $is_visible = TRUE;

	// child pages, populated by Menu
	public $children = array();

	// TODO: consider allowing creation of empty page objects for use in controllers, in order to utilize root method, etc.
	// constructor loads data
	public function __construct($name, $path, $section, $parent, $parent_parent = NULL) {

		// set project name
		$this->name = $name;
		$this->path = rtrim($path, '/');

		// index files are created as the section parent
		if ($name == 'index') {

			$this->is_section = TRUE;

			// if deeper than root section
			if ($section !== NULL) {

				// TODO: consider changing structure to leave index files as is, create is_section files that don't have content, only vars
				// name changed from index to section name
				$this->name = $section;
				$this->section = $parent;
				$this->parent = $parent_parent;

			}
		} else {

			// assign section name
			$this->section = $section;
			$this->parent = $parent;
			$this->parent_parent = $parent_parent;
		}

		// load content data
		$this->load();

	}

	// filter and return pages by properties
	public static function find_all($terms) {

		$pages = array();

		foreach (Pastefolio::$pages as $page) {

			$matched = TRUE;

			foreach ($terms as $property => $value) {

				if ($page->$property !== $value) {

					$matched = FALSE;

				}
			}

			if ($matched)
				$pages[] = $page;

		}

		return $pages;

	}

	// retrieve single page by properties
	public static function find($terms) {

		$pages = self::find_all($terms);

		return (empty($pages)) ? FALSE : $pages[0];

	}

	// display root section
	public function root() {

		$menu = array();

		// get root content pages
		foreach (Page::find_all(array('section' => NULL)) as $page) {

			if ($page->is_visible) {

				// add child pages if parent is section
				$menu[] = ($page->is_section) ? $this->recursive_pages($page) : $page;

			}
		}

		return $menu;

	}

	// display child pages
	public function children() {

		$menu = array();

		// get section child pages
		foreach (self::find_all(array('section' => $this->name)) as $page) {

			if ($page->is_visible) {

				// add child pages if parent is section
				$menu[] = ($page->is_section) ? self::recursive_pages($page) : $page;

			}
		}

		return $menu;

	}

	// recursively build section
	private static function recursive_pages($parent) {

		// add children recursively
		foreach (self::find_all(array('section' => $parent->name)) as $page) {

			$parent->children[] = self::recursive_pages($page);

		}

		return $parent;

	}

	// TODO: allow inifinite section depth
	// TODO: caching $pages data
	// recursively load sections of content, relative to CONTENTPATH
	public static function load_path($path, $section = NULL, $parent = NULL, $parent_parent = NULL) {

		$path = rtrim($path, '/');

		$pages = array();

		foreach (self::list_dir($path) as $file => $name) {

			// check if it's a sub directory
			if (is_dir($path.'/'.$file)) {

				$pages = array_merge($pages, self::load_path($path.'/'.$file, $name, $section, $parent));

			} else {

				$page = new Page($name, $path.'/'.$file, $section, $parent, $parent_parent);

				$pages[] = $page;

			}
		}

		return $pages;

	}

	// return content directory list
	public static function list_dir($path) {

		$files = array();

		if (($handle = opendir($path)) === FALSE)
			return $files;

		while (($file = readdir($handle)) !== FALSE) {

			// ignore dot dirs and paths prefixed with an underscore or period
			if ($file != '.' AND $file != '..' AND $file[0] !== '_' AND $file[0] !== '.') {

				// file name without content extension
				$name = basename($file, Page::$ext);

				// split filename by initial period, limited to two parts
				$parts = explode('.', $name, 2);

				// page name is everything after intial period if one exists
				$name = (count($parts) > 1) ? $parts[1] : $parts[0];

				// use filename as sort key
				$files[$file] = $name;

			}
		}

		closedir($handle);

		// sort files via natural text comparison, similar to OSX Finder
		uksort($files, 'strnatcasecmp');

		// return sorted array (filenames => basenames)
		return $files;

	}

	// TODO: strip any commments after # or //
	// load individual content page
	public function load() {

		if (($html = @file_get_contents(realpath($this->path))) !== FALSE) {

			// credit to Ben Blank: http://stackoverflow.com/questions/441404/regular-expression-to-find-and-replace-the-content-of-html-comment-tags/441462#441462
			$regexp = '/<!--((?:[^-]+|-(?!->))*)-->/Ui';
			preg_match_all($regexp, $html, $comments);

			// split comments on newline
			$lines = array();
			foreach ($comments[1] as $comment) {
				$var_lines = explode("\n", trim($comment));
				$lines = array_merge($lines, $var_lines);
			}

			// split lines on colon and assign to key/value
			$vars = array();
			foreach ($lines as $line) {
				$parts = explode(":", $line, 2);
				if (count($parts) == 2) {
					$vars[trim($parts[0])] = trim($parts[1]);
				}
			}

			foreach ($vars as $key => $value) {
				if (strtolower($value) === "false" OR $value === '0') {
					$value = FALSE;
				} elseif (strtolower($value) === "true" OR $value === '1') {
					$value = TRUE;
				}
				$this->$key = $value;
			}

			// set title to name if not set otherwise
			$this->title = (empty($this->title)) ? ucwords(str_replace('_', ' ', $this->name)) : $this->title;
			$this->content = $html;
			// debug page variables
			// $this->content .= "<pre>".htmlentities(print_r($vars, TRUE)).'</pre>';

		}
	}

	// check if current page or section
	public function current() {

		// get current page and section from controller
		$current_page = Pastefolio::instance()->current_page;
		$current_section = Pastefolio::instance()->current_section;

		// return (($this->name == $current_page AND $this->section == $current_section) OR ($this->is_section AND $this->name == $current_section));
		return ($this->name == $current_page AND $this->section == $current_section);

	}


	// returns section pages as index => name
	public static function flat_section($section) {

		$pages = array();

		foreach (self::find_all(array('section' => $section)) as $page) {

			$pages[] = $page->name;

		}

		return $pages;

	}

	public function next_url() {

		// start with current section if exists
		$url = (empty($this->section)) ? '/' : '/'.$this->section.'/';

		// get next page in section
		$next = $this->relative_page(1);

		// cycle to first page if last in section
		$url .= ($next === FALSE) ? $this->first_page() : $next;

		return $url;

	}

	public function prev_url() {

		// start with current section if exists
		$url = (empty($this->section)) ? '/' : '/'.$this->section.'/';

		// get previous page in section
		$prev = $this->relative_page(-1);

		// cycle to last page if first in section
		$url .= ($prev === FALSE) ? $this->last_page() : $prev;

		return $url;

	}



	// returns project name relative to specified project
	public function relative_page($offset = 0) {

		// create page map from current section
		$section = self::flat_section($this->section);

		// find current key
		$current_page_index = array_search($this->name, $section);

		// return desired offset, if in array
		if (isset($section[$current_page_index + $offset])) {
			return $section[$current_page_index + $offset];
		}

		// otherwise return false
		return FALSE;
	}


	public function first_page() {

		// create page map from current section
		$section = self::flat_section($this->section);

		// get first item of current section
		return array_shift($section);

	}

	public function last_page() {

		// create page map from current section
		$section = self::flat_section($this->section);

		// get last item of current section
		return array_pop($section);

	}

	/*



	// convert page object to array, moving methods to properties
	// deprecated, Mustache does this fine
	public function as_array() {

		$page_array = array();

		foreach (get_class_methods(__CLASS__) as $method) {

			// ignore methods defined in exclude and those with an underscore prefix
			if (! in_array($method, $this->_exclude) AND $method[0] !== '_') {

				// convert methods to properties
				$page_array[$method] = $this->$method();

			}
		}

		foreach (get_object_vars($this) as $property => $value) {

			// ignore properties defined in exclude and those with an underscore prefix
			if (! in_array($property, $this->_exclude) AND $property[0] !== '_') {

				$page_array[$property] = $value;

			}

		}

		return $page_array;

	}


	// TODO: move sorting to configurable functin in Menu, we don't care about sorting here
	// return sorted content list
	public static function old_sorted_list_dir($path = '/') {

		// path is relative to content path
		$path = realpath(CONTENTPATH.$path).'/';

		if (FALSE === ($handle = opendir($path)))
			return array();

		$files = array(
			'numeric' => array(),
			'alpha' => array(),
		);

		while (FALSE !== ($file = readdir($handle))) {

			// ignore dot dirs and paths prefixed with an underscore or period
			if ($file != '.' AND $file != '..' AND $file[0] !== '_' AND $file[0] !== '.') {

				// file name without extension
				$name = basename($file, self::$ext);

				// split filename by initial period, limited to two parts
				$parts = explode('.', $name, 2);

				// page name is everything after intial period if one exists
				$name = (count($parts) > 1) ? $parts[1] : $parts[0];

				// keep numeric and alpha files separate for sorting
				$type = (is_numeric($file[0])) ? 'numeric' : 'alpha';

				// use filename as sort key
				$files[$type][$file] = $name;

			}
		}

		closedir($handle);

		// sort files via natural text comparison, similar to OSX Finder
		uksort($files['alpha'], 'strnatcasecmp');
		uksort($files['numeric'], 'strnatcasecmp');

		// flip numeric keys to descending order, put alpha files last
		$files = array_reverse($files['numeric']) + $files['alpha'];

		// return sorted array (filenames => basenames)
		return $files;

	}
	// returns array of all projects
	public static function all_projects() {

		$projects = array();

		foreach (Content::list_dir(self::$_project_path) as $name) {
			$projects[] = Project::factory($name)->load();
		}

		return $projects;
	}
	*/

	public function __get($property) {

		// avoid undefined property errors
		return '';

	}


}