<?php

class ma_cache_that_page {
	
	public static function decide() {
		//pull the global var array that's used for the caching engine
		global $ma_cache_that;

		//check if the user is logged in and abort the full page cache if so.
		require_once QA_INCLUDE_DIR.'qa-app-users.php';
		
		if (qa_get_logged_in_userid()) {
			header('X-Cache-User: Logged in');
			return;
		}else{
			header('X-Cache-User: Logged out');
		}

		
		//build the array that holds the encoding type and include a default of none in the list (but we'll make sure it's selected last later).
		$ma_cache_that['accept_encoding_array'][] = 'none';
		if ( $_SERVER['HTTP_ACCEPT_ENCODING'] != '' ) {
			//combine the $ma_cache_that['accept_encoding_array'] array with the default of none
			//it's important that the encoding is first so the foreach loop will try them first
			//before ending at the 'none' default
			$ma_cache_that['accept_encoding_array'] = array_merge(explode(',', $_SERVER['HTTP_ACCEPT_ENCODING']), $ma_cache_that['accept_encoding_array']);
		}
		
		//Should we be responding with a whole page cache response?
		//To-do: add option for admin to select the pages to cache.
		if ($_SERVER['REQUEST_METHOD'] == "GET" && 1==1 && (
			($ma_cache_that['template'] == 'question' && $_GET['state'] == '') ||
				$ma_cache_that['template'] == 'tags' ||
				$ma_cache_that['template'] == 'tag' ||
				$ma_cache_that['template'] == 'users' ||
				$ma_cache_that['template'] == 'user' ||
				$ma_cache_that['template'] == 'home' ||
				$ma_cache_that['template'] == 'unanswered' ||
				$ma_cache_that['template'] == 'questions') )  {


			//full page cache filenames are "type of object-object name-unique value"
			//what's helpful in this method is because the reqeust is used as a unique value and the questionId is first in the url
			//we can do a wildcard search when deleting the cache for a question with just the ID.
			//for example, the page cache of a question with an ID of 1 can be deleted with 
			//HH_cache::deleteStartsWith("page-question-1-");
			//we don't need to know the rest of the url which is used for the unique value.
			if ($ma_cache_that['template'] == 'question') {
				$ma_cache_that['cache_filename'] = 'page-' . $ma_cache_that['template'] . '-' . $ma_cache_that['questionId'];
			}else{
				$ma_cache_that['cache_filename'] = 'page-' . $ma_cache_that['template'] . '-' . ma_cache_that_page::convertToFilename($ma_cache_that['uri_path_lc']);
			}

			//try to read the cache file if it exists. We loop through the encoding types so we pull up the correct 
			//cache file with the encoding already done.
			foreach ($ma_cache_that['accept_encoding_array'] as $encoding) {
				$cacheReadResult = HH_cache::read($ma_cache_that['cache_filename'] . '-' . trim($encoding), 600);
				if ($cacheReadResult['success']) {
					break;
				}
			}


			if ($cacheReadResult['success']) {
				//remove any previously added content and replace it with the cached information.
				ob_clean();

				//split the cache value into the headers and content.
				//the headers and content are split by a double line break.
				$headers = substr($cacheReadResult['value'], 0, strpos($cacheReadResult['value'], "*ma_cache_that_headers_end*"));
				$content = substr($cacheReadResult['value'], strpos($cacheReadResult['value'], "*ma_cache_that_headers_end*") + 27);

				$headersList = explode("\n", $headers);
				foreach($headersList as $header) {
					header($header);
				}

				//add the cache header, send the data and stop processing
				header('X-Cache-That: ' . date('c', $cacheReadResult['modified']));
				header('X-Cache-That-Execution-Time: ' . (microtime(true) - $ma_cache_that['response_start']));
				echo $content;
				die();
			}else{
				//If we have an expired value, write it back out so other requests have 
				//something to work with while the new page cache is being built.
				if ($cacheReadResult['value'] != null){
					HH_cache::write($ma_cache_that['cache_filename'], $cacheReadResult['value']);
				}

				//set this header to Live so we know what's going on from the client side of things.
				header('X-Cache-That: Live');

				//mark the cacheResponse as true so the buffer output will know to save it for next time.
				$ma_cache_that['response_cache'] = true;

				//start the buffer so we can capture the response and save it in our cache folder.
				ob_start(array("ma_cache_that_page", "bufferCallback"));
			}

		}

	}
	
	//convert string into something that can be used for a filename
	public static function convertToFilename($mixedString) {
		$mixedString = strtolower($mixedString);
		$mixedString = str_replace('_', ' ', $mixedString);
		$mixedString = str_replace('-', ' ', $mixedString);
		$mixedString = str_replace('.', ' ', $mixedString);
		$mixedString = str_replace('_', ' ', $mixedString);
		$mixedString = str_replace('/', ' ', $mixedString);
		$mixedString = preg_replace('/[^a-z0-9\s]/i', '', $mixedString);
		$mixedString = preg_replace('/\s+/', ' ', $mixedString);
		$mixedString = str_replace(' ', '-', $mixedString);
		return $mixedString;
	}

	
	public static function bufferCallback($buffer)
	{

		//find out how the content is being encoded
		//default to 'none'
		$contentEncoding = 'none';

		//get the url to be cached
		global $ma_cache_that;

		header('X-Cache-That-First-Execution-Time: ' . (microtime(true) - $ma_cache_that['response_start']));
		header('X-Cache-That-Execution-Time: ' . (microtime(true) - $ma_cache_that['response_start']));
		header("X-Cache-That-Debug: " . $ma_cache_that['cache_filename']);

		foreach(headers_list() as $header) {
			$header = strtolower($header);

			$headers .= $header . "\n";

			if (strpos($header, "content-encoding:") === 0) {
				$contentEncoding = trim(substr($header, 17));
			}

		}

		HH_cache::write($ma_cache_that['cache_filename'] . "-" . $contentEncoding, $headers . "*ma_cache_that_headers_end*" . $buffer);

		//return $response;
		return ( $buffer );

	}

	
}