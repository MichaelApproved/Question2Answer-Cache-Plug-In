<?php

/*
    (c) 2011, Michael Khalili

    http://www.michaelapproved.com/

    
    File: qa-plugin/ma-cache-that/qa-plugin.php
    Version: 1.0.0
    Date: 2011-10-08 00:00:00 GMT
    Description: Cache system


    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    More about this license: http://www.question2answer.org/license.php
*/

/*
    Plugin Name: Cache That
    Plugin URI: http://www.michaelapproved.com/
    Plugin Description: Cache system
    Plugin Version: 1.0
    Plugin Date: 2011-10-08
    Plugin Author: Michael Khalili
    Plugin Author URI: http://www.michaelapproved.com/
    Plugin License: GPLv2
    Plugin Minimum Question2Answer Version: 1.4
*/


    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../../');
        exit;
    }

//error_reporting(E_ALL);
//ini_set('display_errors', 'On');

//todo: find out if we should be caching at all. since this is happening so early, I'll probably have to write a file like on.txt and off.txt and check if the file exists


ma_cache_that_init();

//pull the global var array that's used for the caching engine
global $ma_cache_that;

////requre the file that decides if this page should be cached
require_once($ma_cache_that['root_folder'] . '/qa-ma-cache-that-page.php');

//require the cache class
require_once($ma_cache_that['root_folder'] . '/class-cache.php');

//set the file path to use
HH_cache::$cacheFolder = $ma_cache_that['cache_storage_folder'];

//register the admin module
qa_register_plugin_module('module', 'qa-ma-cache-that-admin.php', 'qa_ma_cache_that_admin', 'Cache That');

//only do this if the cache is enabled.
if (HH_cache::enabled()) {
	//execute the full page cache decide function. 
	//Best to do this as early as possible because if this returns a cached file, the code below it won't be executed.
	ma_cache_that_page::decide();

	//register the event module that deletes the cache files if a change happens.
	qa_register_plugin_module('event', 'qa-ma-cache-that-event.php', 'qa_ma_cache_that_event', 'Cache That Event Handler');
}


//qa_register_plugin_layer('qa-ma-cache-that-layer.php', 'Cache That Layer');

function ma_cache_that_init() {
	//define our global cache object that'll pass information around.
	global $ma_cache_that;

	//set the start time so we could check how long it took to build the page.
	$ma_cache_that['response_start'] = microtime(true);

	//define the folders being used
	$ma_cache_that['root_folder'] = dirname(__FILE__);
	$ma_cache_that['cache_storage_folder'] = $ma_cache_that['root_folder'] . '/cache-storage';

	//make sure the needed folders have been created
	if (!is_dir($ma_cache_that['cache_storage_folder']))
		{mkdir($ma_cache_that['cache_storage_folder']);}

	
	//set the start time so we could check how long it took to build the page.
	$ma_cache_that['response_start'] = microtime(true);


	//since we're executing before most of the base processes, we're going to have to figure out some stuff on our own.

	//get the unique path and handle the different types of URL methods
	if (isset($_GET['qa'])) {
		$ma_cache_that['uri_path'] = $_GET['qa'];
	}elseif (strpos($_SERVER['REQUEST_URI'], "/index.php/") === 0) {
		//ignore the leading /index.php/
		$ma_cache_that['uri_path'] = substr($_SERVER["REQUEST_URI"], 11);
	}else{
		//ignore the leading forward slash
		$ma_cache_that['uri_path'] = substr($_SERVER["REQUEST_URI"],1);
	}

	//remove the query string, if exists
	if (strpos($ma_cache_that['uri_path'], '?') !== false) {
		$ma_cache_that['uri_path'] = substr($ma_cache_that['uri_path'], 0, strpos($ma_cache_that['uri_path'], '?'));
	}


	$ma_cache_that['uri_path_lc'] = strtolower($ma_cache_that['uri_path']);

	//the first part of the URL tells a lot about the request, so lets break this up and focus on the first indexed item.
	$ma_cache_that['uri_path_parts'] = explode('/', $ma_cache_that['uri_path_lc']);

	//define the page template based on the first segment of the path uri
	if ($ma_cache_that['uri_path_parts'][0] == '') {
		$ma_cache_that['template'] = 'home';
	}elseif (is_numeric($ma_cache_that['uri_path_parts'][0])) {
		$ma_cache_that['template'] = 'question';
		$ma_cache_that['questionId'] = $ma_cache_that['uri_path_parts'][0];
	}else {
		$ma_cache_that['template'] = $ma_cache_that['uri_path_parts'][0];
	}
	
	
}