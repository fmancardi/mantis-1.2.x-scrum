<?php

# Copyright (c) 2011 John Reese
# Licensed under the MIT license
#
# 20120705 - fmancardi - TICKET 1 - REQ - Custom fields used to track work - add configuration option
#
class ScrumPlugin extends MantisPlugin
{
	public function register()
	{
		$this->name = plugin_lang_get("title");
		$this->description = plugin_lang_get("description");

		$this->version = "0.1";
		$this->requires = array(
			"MantisCore" => "1.2.6",
		);
		$this->uses = array(
			"Source" => "0.16",
		);

		$this->author = "John Reese";
		$this->contact = "john@noswap.com";
		$this->url = "http://noswap.com";
	}

	public function config()
	{
		$cfg = array();
		$cfg["board_columns"] = array("New" => array(NEW_, FEEDBACK, ACKNOWLEDGED,25),
					      			  "Assigned to Dev" => array(CONFIRMED, ASSIGNED,55),
					      			  "Dev Done" => array(60),
					      			  "Resolved" => array(RESOLVED),
				                      "Closed" => array(CLOSED));

		$cfg["board_severity_colors"] = array(FEATURE => "green", TRIVIAL => "green", TEXT => "green",
											  TWEAK => "green", MINOR => "gray", MAJOR => "gray", CRASH => "orange",
											  BLOCK => "red");

		$cfg["board_resolution_colors"] = array(OPEN => "orange",FIXED => "green",
												REOPENED => "red",UNABLE_TO_DUPLICATE => "gray",
												NOT_FIXABLE => "gray",DUPLICATE => "gray",
												NOT_A_BUG => "gray",SUSPENDED => "gray",
												WONT_FIX => "gray");

		$cfg["sprint_length"] = (21 * 24 * 60 * 60); # 21 days (21 * 24 * 60 * 60)

		$cfg["show_empty_status"] = OFF;
		$cfg["custom_fields"] = array("work" => array("scrum_effort" => "Est.Work",
									  				  "scrum_work_done" => "Act.Work",
									  				  "scrum_tobe_done" => "Rem.Work"));
		/*
		$cfg["custom_fields"] = array(array("work" => array("scrum_effort" => "Scrum Effort (hours)",
									  						"scrum_work_done" => "Scrum Work done (hours)",
									  						"scrum_tobe_done" => "Scrum Work to finish (hours)"));
		
		*/
		return $cfg;
	}


	public function hooks()
	{
		return array(
			"EVENT_MENU_MAIN" => "menu",
		);
	}

	public function menu($event)
	{
		$links = array();
		$links[] = '<a href="' . plugin_page("board") . '">' . plugin_lang_get("board") . '</a>';

		return $links;
	}
}
