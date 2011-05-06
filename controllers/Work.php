<?php

class Work_Controller extends Template_Controller {
	
	public function index() {
		
		// get first project name
		$first = Menu::first_project();
		
		// redirect to first project
		self::redirect('/work/'.$first);
	
	}

	
	public function show($name, $pg = 1) {
		
		$this->template->current_page = $name;
				
		$this->template->content = Project::factory($name)->load()->render();	

	}
	
	public function _all_index() {
		
		$output = '<h1>All Work</h1>';
			
		foreach (Project::all_projects() as $project) {
			$output .= $project->render();
		}

	    $this->template->content = $output;		
	    
	}
		
	public function convert() {
		
		/*
		---
		work_id: ifloorplan
		category: 0
		visible: 1
		index: 2
		menuname: iFloorPlan
		title: iFloorPlan
		launchtext: VISIT iFLOORPLAN.COM
		launchurl: http://ifloorplan.com
		description: |
		  <p>Interactive floorplans allow new home buyers to customize their home before it is built. iFloorPlan lets buyers visualize and manipulate structural options, electrical wiring and furniture placement. Home builders may then use these personalized floorplans as a starting guide for the construction process.</p>
		  <p>iFloorPlan has evolved through years of development into quite a sophisticated and flexible application, and is currently in use by dozens of the nation's top builders. The latest version (4.0) introduces a completely new administration and production backend.</p>

		  <p class="launch"><a title="View Demo" href="http://ifloorplan.com/ifp/plan.php?plan=1:2400OH" rel="external">VIEW DEMO</a></p>
		numpics: 3
		subtitle: Flash/PHP/SQL Development
		extension: .gif
		color: 004D90
		*/
		
		
		// launchtext: VISIT iFLOORPLAN.COM
		// launchurl: http://ifloorplan.com
		// <p class="launch"><a title="View Demo" href="http://ifloorplan.com/ifp/plan.php?plan=1:2400OH" rel="external">VIEW DEMO</a></p>
	  
		$output = '<h1>Converting</h1>';
		
		$works = array();
	
		if ($handle = opendir(APPPATH.'views/work')) {
			while (false !== ($file = readdir($handle))) {
				if ($file != '.' AND $file != '..') { 
				 	$works[] = sfYaml::load(APPPATH.'views/work/'.$file);
				}
			}
			closedir($handle);			
		}
		
		echo '<br/><pre>';
		
		$menu = array("Applications" => array(), "Websites" => array(), "Design" => array());
		$categories = array("Applications", "Websites", "Design");	  	

	    foreach ($works as $work) {

			$cat = $categories[$work['category']];
			
			$menu[$cat][$work['work_id']] = $work['menuname'];
						
	    }
	
		print_r($menu);
		
		$yaml = sfYaml::dump($menu, 2);
		file_put_contents(APPPATH.'views/newwork/menu.yaml', $yaml);	

	    echo '</pre>';

		
	}
	
	public function _action_index($work_id = 'cover')
	{
		
		$this->curr_id = $work_id;

		$this->categories = array("Applications", "Websites", "Design");
		$this->work = array(array(), array(), array());
			
		$works = $this->_load_work_big_yaml();
		
		foreach ($works as $work_array) {
			
			$cat = $work_array['category'];
			
			if ($work_array['visible']) {
			
				$this->work[$cat][] = $work_array;

				if ($work_array['work_id'] == $this->curr_id) {
					$this->curr_work = $work_array; 
					$this->curr_cat = $cat;
					$this->curr_index = count($this->work[$cat])-1;
				}
			}

		}
		
		$this->work_images = array();
		for ($i=1; $i<=$this->curr_work['numpics']; $i++) {
			$this->work_images[] = '/assets/images/'.$this->curr_id.$i.$this->curr_work['extension'];

		}
		
		$t = new View('template');
		$t->categories = $this->categories;
		$t->work = $this->work;
		$t->work_images = $this->work_images;
		
		$t->curr_work = $this->curr_work;
		$t->curr_id = $this->curr_id;
		$t->curr_cat = $this->curr_cat;
		$t->curr_index = $this->curr_index;
		
		$t->getNextProject = $this->getNextProject();
		$t->getPrevProject = $this->getPrevProject();
		$t->drawProjects = $this->drawProjects();
				
		$this->response->body($t);
		
		
	}
	
	public function getNextProject() {

		if ($this->curr_cat == count($this->categories)-1 && $this->curr_index == count($this->work[$this->curr_cat])-1) { // last category, last project
			return '/work/'.$this->work[0][0]['work_id'];
		} else if ($this->curr_index == count($this->work[$this->curr_cat])-1) { //last project in category
			return '/work/'.$this->work[($this->curr_cat+1)][0]['work_id'];
		} else {
			return '/work/'.$this->work[$this->curr_cat][($this->curr_index+1)]['work_id'];
		}
	} 
	public function getPrevProject() {
		
		if ($this->curr_cat == 0 && $this->curr_index == 0) { // first category, first project
			return '/work/'.$this->work[2][(count($this->work[2])-1)]['work_id'];
		} else if ($this->curr_index == 0) { //first project in category
			$prev_cat = $this->curr_cat-1;
			$prev_cat_last = count($this->work[$prev_cat])-1;
			return '/work/'.$this->work[$prev_cat][$prev_cat_last]['work_id'];
		} else {
			return '/work/'.$this->work[$this->curr_cat][($this->curr_index-1)]['work_id'];
		}
	}

	public function drawProjects() {

		$m = '<ul class="folio">'."\n";
		for ($c=0; $c<count($this->categories); $c++) { 
			$m .= "\t\t".'<li class="category">'.$this->categories[$c]."\n";
			$m .= "\t\t\t".'<ul>'."\n";
			for ($i=0; $i<count($this->work[$c]); $i++) { 
				$mname = $this->work[$c][$i]['menuname'];
				if ($this->work[$c][$i]['work_id'] == $this->curr_id) { 
					$w = '<li class="folio_xlight" id="current_project">&bull; '.$mname."</li>\n"; 
				} else { 
					$w = '<li><a title="'.$mname.'" href="/work/'.$this->work[$c][$i]['work_id'].'">'.$mname."</a></li>\n"; 
				}
				if ($mname != "Cover") { $m .= "\t\t\t\t".$w; }
			}  
			$m .= "\t\t\t</ul></li>\n";
		}
		$m .= "\t\t</ul>\n";

		return $m;

	}
	

	
}