<?php
global $ma_cache_that;

define('PREG_FIND_RECURSIVE', 1);
define('PREG_FIND_DIRMATCH', 2);
define('PREG_FIND_FULLPATH', 4);
define('PREG_FIND_NEGATE', 8);
define('PREG_FIND_DIRONLY', 16);
define('PREG_FIND_RETURNASSOC', 32);
define('PREG_FIND_SORTDESC', 64);
define('PREG_FIND_SORTKEYS', 128); 
define('PREG_FIND_SORTBASENAME', 256);   # requires PREG_FIND_RETURNASSOC
define('PREG_FIND_SORTMODIFIED', 512);   # requires PREG_FIND_RETURNASSOC
define('PREG_FIND_SORTFILESIZE', 1024);  # requires PREG_FIND_RETURNASSOC
define('PREG_FIND_SORTDISKUSAGE', 2048); # requires PREG_FIND_RETURNASSOC
define('PREG_FIND_SORTEXTENSION', 4096); # requires PREG_FIND_RETURNASSOC
define('PREG_FIND_FOLLOWSYMLINKS', 8192);


class HH_cache {
	public static $cacheFolder = '';

	//Read cache data.
	//$name = Key for cache value to return.
	//$expires = Time in seconds cache should live for.
	public static function read($name, $expires){
		$waitMax = 25; //max seconds to wait for tmp to clear
		$waitCurrent = 0; //Amount of time has been waiting
	
		//Make sure there isn't another process trying to create the cache with a tmp file.
		//If there is, wait for it to finish creating the file.
		$tmpMissing = false;
		while ($tmpMissing == false) {
			//Find the pattern name.digits.cache
			//The /D is for the trailing $ modifier (i think?) http://php.net/manual/en/reference.pcre.pattern.modifiers.php
			$files = HH_cache::preg_find('/^' . $name . '\.\d*\.tmp/D', HH_cache::$cacheFolder, PREG_FIND_SORTMODIFIED|PREG_FIND_RETURNASSOC|PREG_FIND_SORTDESC);
			
			if (count($files) == 0){
				$tmpMissing = true;
			}else{
				usleep(50000);
				$waitCurrent += 50000;
				if ($waitCurrent >= ($waitMax * 1000000)){
					//we've waited too long for the tmp file to clear.
					return array('success' => false, 'error' => 'Wait time of ' . $waitMax . ' second(s) exceeded.');
				}
			}
		}
		
		//$filename = $name . '-' . str_replace('.', '', gettimeofday(true));
		
		//Find the pattern name.digits.cache
		//The /D is for the trailing $ modifier (i think?) http://php.net/manual/en/reference.pcre.pattern.modifiers.php
		$files = HH_cache::preg_find('/^' . $name . '\.\d*\.cache$/D', HH_cache::$cacheFolder, PREG_FIND_SORTMODIFIED|PREG_FIND_SORTBASENAME|PREG_FIND_RETURNASSOC);
		
		if (count($files) == 0){
			return array('success' => false, 'error' => 'Cache not found.', 'value' => null);
		}else{
			//The list of files returns as oldest first.
			//Run through the backwards list of files and try to return the cache while also cleaning up.
			//Keep overwriting the return array until there's a success.
			foreach ($files as $fileFoundName => $fileFoundStats) {
				//Make sure the cache hasn't expired
				//subtract current time from file mtime to figure out how many seconds ago the file was modified.
				$fileFoundModifiedAgo = ( time() - $fileFoundStats['stat']['mtime'] );
				
				if ($expires > $fileFoundModifiedAgo) {
					//cache is still current

					//read the file contents
					$fileContents = file_get_contents($fileFoundName);
					
					$returnValue = array('success' => true, 'value' => $fileContents, 'expires' => ($expires - $fileFoundModifiedAgo), 'file' => $fileFoundName, 'modified' => $fileFoundStats['stat']['mtime']);
				}else{

					//cache is no longer valid.

					//read the file contents just in case it's needed anyway.
					$fileContents = file_get_contents($fileFoundName);

					//try to delete the file but only if it's over 30 seconds past expiration.
					//in case another process is trying to read it just as it expired.
					if (($fileFoundModifiedAgo - $expires) > 30){
						unlink($fileFoundName);
					}

					$returnValue = array('success' => false, 'error' => 'Cache expired ' . ($fileFoundModifiedAgo - $expires) . ' seconds ago.', 'value' => $fileContents, 'file' => $fileFoundName, 'modified' => $fileFoundStats['stat']['mtime']);
				}
			}
		
			return $returnValue;
		
		}
		
		return array('success' => false, 'error' => 'Unknown path to end of function reached.');
	}
	
	public static function write($name, $content){
		//build the file with a timestamp built in.
		$filename = $name . '.' . str_replace('.', '', gettimeofday(true));
		
		//write the contents to a temp file.
		$fp = fopen(HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . $filename . '.tmp', 'wb');
		fwrite($fp, $content);
		fclose($fp);

		//rename the temp file to the proper cache name.
		return rename(HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . $filename . '.tmp', HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . $filename . '.cache');
		
	}
	

	//delete any cache files that start with $name
	public static function deleteStartsWith($name) {
		//Find the pattern name(any non period char).digits.cache
		//The /D is for the trailing $ modifier (i think?) http://php.net/manual/en/reference.pcre.pattern.modifiers.php
		$files = HH_cache::preg_find('/^' . $name . '[^\.]*\.\d*\.cache$/D', HH_cache::$cacheFolder, PREG_FIND_SORTMODIFIED|PREG_FIND_SORTBASENAME|PREG_FIND_RETURNASSOC);
	
		//The list of files and delete them
		foreach ($files as $fileFoundName => $fileFoundStats) {
			unlink($fileFoundName);
		}
	}
	
	
	//saves the setting to disk so it can be read without needing a db connection
	public static function enabledSave($enabled) {
		if ($enabled) {
			$settingCurrent = 'disabled';
			$settingNew = 'enabled';
		}else{
			$settingCurrent = 'enabled';
			$settingNew = 'disabled';
		}

		//we must save the setting as a filename because the cache shouldn't need to open a database connection 
		//to get the settings. The setting is as a filename so the file doesn't even need to be opened.
		$fp = fopen(HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . "cache-$settingNew.setting", 'wb');
		fwrite($fp, '');
		fclose($fp);

		//delete the current setting
		@unlink(HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . "cache-$settingCurrent.setting");
	}

	//check if the cache is enabled
	public static function enabled() {
		return file_exists(HH_cache::$cacheFolder . DIRECTORY_SEPARATOR . "cache-enabled.setting");
	}
	
	
	/*
	}
	 * Find files in a directory matching a pattern
	 *
	 *
	 * Paul Gregg <pgregg@pgregg.com>
	 * 20 March 2004,  Updated 20 April 2004
	 * Updated 18 April 2007 to add the ability to sort the result set
	 * Updated 9 June 2007 to prevent multiple calls to sort during recursion
	 * Updated 12 June 2009 to allow for sorting by extension and prevent following
	 * symlinks by default
	 * Version: 2.3
	 * This function is backwards capatible with any code written for a
	 * previous version of preg_find()
	 *
	 * Open Source Code:   If you use this code on your site for public
	 * access (i.e. on the Internet) then you must attribute the author and
	 * source web site: http://www.pgregg.com/projects/php/preg_find/preg_find.phps
	 * Working examples: http://www.pgregg.com/projects/php/preg_find/
	 *
	 */
	 /* Michael Khalili edit 7/20/2011 - changed '/' to DIRECTORY_SEPARATOR */

	// PREG_FIND_RECURSIVE   - go into subdirectorys looking for more files
	// PREG_FIND_DIRMATCH    - return directorys that match the pattern also
	// PREG_FIND_DIRONLY     - return only directorys that match the pattern (no files)
	// PREG_FIND_FULLPATH    - search for the pattern in the full path (dir+file)
	// PREG_FIND_NEGATE      - return files that don't match the pattern
	// PREG_FIND_RETURNASSOC - Instead of just returning a plain array of matches,
	//                         return an associative array with file stats
	// PREG_FIND_FOLLOWSYMLINKS - Recursive searches (from v2.3) will no longer
	//                            traverse symlinks to directories, unless you
	//                            specify this flag. This is to prevent nasty
	//                            endless loops.
	//
	// You can also request to have the results sorted based on various criteria
	// By default if any sorting is done, it will be sorted in ascending order.
	// You can reverse this via use of:
	// PREG_FIND_SORTDESC    - Reverse order of sort
	// PREG_FILE_SORTKEYS    - Sort on the keyvalues or non-assoc array results
	// The following sorts *require* PREG_FIND_RETURNASSOC to be used as they are
	// sorting on values stored in the constructed associative array
	// PREG_FIND_SORTBASENAME - Sort the results in alphabetical order on filename
	// PREG_FIND_SORTMODIFIED - Sort the results in last modified timestamp order
	// PREG_FIND_SORTFILESIZE  - Sort the results based on filesize
	// PREG_FILE_SORTDISKUSAGE - Sort based on the amount of disk space taken
	// PREG_FIND_SORTEXTENSION - Sort based on the filename extension
	// to use more than one simply seperate them with a | character



	// Search for files matching $pattern in $start_dir.
	// if args contains PREG_FIND_RECURSIVE then do a recursive search
	// return value is an associative array, the key of which is the path/file
	// and the value is the stat of the file.
	public static function preg_find($pattern, $start_dir='.', $args=NULL) {

	  static $depth = -1;
	  ++$depth;

	  $files_matched = array();

	  $fh = opendir($start_dir);

	  while (($file = readdir($fh)) !== false) {
		if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;
		$filepath = $start_dir . DIRECTORY_SEPARATOR . $file;
		if (preg_match($pattern,
					   ($args & PREG_FIND_FULLPATH) ? $filepath : $file)) {
		  $doadd =    is_file($filepath)
				   || (is_dir($filepath) && ($args & PREG_FIND_DIRMATCH))
				   || (is_dir($filepath) && ($args & PREG_FIND_DIRONLY));
		  if ($args & PREG_FIND_DIRONLY && $doadd && !is_dir($filepath)) $doadd = false;
		  if ($args & PREG_FIND_NEGATE) $doadd = !$doadd;
		  if ($doadd) {
			if ($args & PREG_FIND_RETURNASSOC) { // return more than just the filenames
			  $fileres = array();
			  if (function_exists('stat')) {
				$fileres['stat'] = stat($filepath);
				$fileres['du'] = $fileres['stat']['blocks'] * 512;
			  }
			  if (function_exists('fileowner')) $fileres['uid'] = fileowner($filepath);
			  if (function_exists('filegroup')) $fileres['gid'] = filegroup($filepath);
			  if (function_exists('filetype')) $fileres['filetype'] = filetype($filepath);
			  if (function_exists('mime_content_type')) $fileres['mimetype'] = mime_content_type($filepath);
			  if (function_exists('dirname')) $fileres['dirname'] = dirname($filepath);
			  if (function_exists('basename')) $fileres['basename'] = basename($filepath);
			  if (($i=strrpos($fileres['basename'], '.'))!==false) $fileres['ext'] = substr($fileres['basename'], $i+1); else $fileres['ext'] = '';
			  if (isset($fileres['uid']) && function_exists('posix_getpwuid')) $fileres['owner'] = posix_getpwuid ($fileres['uid']);
			  $files_matched[$filepath] = $fileres;
			} else
			  array_push($files_matched, $filepath);
		  }
		}
		if ( is_dir($filepath) && ($args & PREG_FIND_RECURSIVE) ) {
		  if (!is_link($filepath) || ($args & PREG_FIND_FOLLOWSYMLINKS))
			$files_matched = array_merge($files_matched,
									   preg_find($pattern, $filepath, $args));
		}
	  }

	  closedir($fh); 

	  // Before returning check if we need to sort the results.
	  if (($depth==0) && ($args & (PREG_FIND_SORTKEYS|PREG_FIND_SORTBASENAME|PREG_FIND_SORTMODIFIED|PREG_FIND_SORTFILESIZE|PREG_FIND_SORTDISKUSAGE)) ) {
		$order = ($args & PREG_FIND_SORTDESC) ? 1 : -1;
		$sortby = '';
		if ($args & PREG_FIND_RETURNASSOC) {
		  if ($args & PREG_FIND_SORTMODIFIED)  $sortby = "['stat']['mtime']";
		  if ($args & PREG_FIND_SORTBASENAME)  $sortby = "['basename']";
		  if ($args & PREG_FIND_SORTFILESIZE)  $sortby = "['stat']['size']";
		  if ($args & PREG_FIND_SORTDISKUSAGE) $sortby = "['du']";
		  if ($args & PREG_FIND_SORTEXTENSION) $sortby = "['ext']";
		}
		$filesort = create_function('$a,$b', "\$a1=\$a$sortby;\$b1=\$b$sortby; if (\$a1==\$b1) return 0; else return (\$a1<\$b1) ? $order : 0- $order;");
		uasort($files_matched, $filesort);
	  }
	  --$depth;
	  return $files_matched;

	}

	
}



?>