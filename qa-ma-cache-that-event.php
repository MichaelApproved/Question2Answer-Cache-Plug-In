<?php

class qa_ma_cache_that_event {

	var $directory;
	var $urltoroot;

	//this runs before the module is used.
	function load_module($directory, $urltoroot)
	{
		//file system path to the plugin directory
		$this->directory=$directory;

		//url path to the plugin relative to the current page request.
		$this->urltoroot=$urltoroot;
	}

	function process_event($event, $userid, $handle, $cookieid, $params)
	{
		//is this an event that has to do with a question?
		if (strpos($event, 'q_') === 0 ||
				strpos($event, 'a_') === 0 ||
				strpos($event, 'c_') === 0) {
			
			global $ma_cache_that;
			
			//yes. So delete the page cache for this question.
			require_once($ma_cache_that['root_folder'] . '/class-cache.php');

			//set the file path that the cache uses use
			HH_cache::$cacheFolder = $ma_cache_that['cache_storage_folder'];

			//delete anything that starts with "page-question-" and "questionId-".
			//the part of the filename that's after the question is the compression type which doesn't matter for the delete process.
			HH_cache::deleteStartsWith("page-question-" . $params['questionid'] . "-");
			
			//As a temporary patch to keeping everything up to date, delete all pages that are cached.
			//This will be cleaned up as the caching gets better and we can cache individual areas of a page instead of the entire page.
			//At that point, we can just deleted the expired area instead of the entire page.
			HH_cache::deleteStartsWith("page-");
		}
	}


};
    

/*
    Omit PHP closing tag to help avoid accidental output
*/