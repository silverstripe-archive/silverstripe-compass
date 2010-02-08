<?php

class Rubygems extends Object {
	
	/** Internal cache variable - is ruby available? */
	static $ruby_ok = null;

	/** Internal cache variable - is the version of rubygems currently available good enough? */
	static $gem_version_ok = null;
	
	/**
	 * Get the path that gems live in, creating it if it doesn't exist 
	 */
	static function gem_path() {
		$path = TEMP_FOLDER . '/gems';
		if (defined('SS_GEM_PATH')) $path = SS_GEM_PATH;
		
		if (!file_exists($path)) mkdir($path, 0770);
		return $path;	
	}

	/**
	 * Internal helper function that calls an external executable - can't just use backticks, as we want stderr and stdout as seperate variables
	 * 
	 * Also sets this modules gem path into the environment of the external executable
	 *
	 * @param $cmd string - the command to run
	 * @param $stdout reference to string - the resultant stdout
	 * @param $stderr reference to string - the resultant stderr
	 * @return int - process exit code, or -1 if the process couldn't be executed
	 */
	static protected function _run($cmd, &$stdout, &$stderr) {
		$descriptorspec = array(
			0 => array("pipe", "r"), // stdin is a pipe that the child will read from
			1 => array("pipe", "w"), // stdout is a pipe that the child will write to
			2 => array("pipe", "w")  // stderr is a file to write to
		);
		 
		$gempath = self::gem_path();
		$process = proc_open("HOME='$gempath' GEM_HOME='$gempath' " . (@$_GET['flush'] ? "FLUSH={$_GET['flush']} " : '') . $cmd, $descriptorspec, $pipes);
		
		$stdout = "";
		$stderr = "";
		 
		if (!is_resource($process)) return -1;
		 
		fclose($pipes[0]); // close child's input immediately
		stream_set_blocking($pipes[1],false);
		stream_set_blocking($pipes[2],false);
		 
		while (true) {
			$read = array();
			if (!feof($pipes[1])) $read[]= $pipes[1];
			if (!feof($pipes[2])) $read[]= $pipes[2];
			 
			if (!$read) break;
			if (!stream_select($read, $w=null, $e=null, 120)) break;
			 
			foreach ($read as $r) {
				$s = fread($r,1024);
				if ($r == $pipes[1]) $stdout .= $s; else $stderr .= $s;
			}
		}
		 
		fclose($pipes[1]);
		fclose($pipes[2]);
		 
		return proc_close($process);
	}
	
	/**
	 * Make sure a gem is available
	 * @param $gem string - the name of the gem to install
	 * @param $tryupdating bool - if the gem is present, check for update? (hits the internet, so slow)
	 * @return null | string - an error string on error, nothing on success
	 */
	static function require_gem($gem, $tryupdating = false) {
		// Check that ruby exists
		if (self::$ruby_ok === null) self::$ruby_ok = (bool)`which ruby`;
		
		if (!self::$ruby_ok) return 'Ruby isn\'t present. The "ruby" command needs to be in the webserver\'s path';
		
		// Check that rubygems exists and is a good enough version
		if (self::$gem_version_ok === null) {
			$code = self::_run('gem environment version', $ver, $err);
			if ($code !== 0) return 'Ruby is present, but there was a problem accessing the current rubygems version - is rubygems available? The "gem" command needs to be in the webserver\'s path.';
			
			$vers = explode('.', $ver);
			self::$gem_version_ok = ($vers[0] >= 1 && $vers[1] >= 2);
		}

		if (!self::$gem_version_ok) return "Rubygems is too old. You have version $ver, but we need at least version 1.2. Please upgrade.";
		
		// See if the gem exists. If not, try adding it
		self::_run("gem list -i $gem", $out, $err);
		
		if (trim($out) != 'true') {
			$code = self::_run("gem install $gem --no-rdoc --no-ri", $out, $err);
			if ($code !== 0) return "Could not install required gem $gem. Either manually install, or repair error. Error message was: $err";
		}
		else if ($tryupdating) {
			$code = self::_run("gem update $gem --no-rdoc --no-ri", $out, $err);
			if ($code !== 0) return "Could not update gem $gem. Error message was: $err";
		}
	}
	
	/**
	 * Execute a command provided by a gem
	 * @param $gem string - the name of the gem providing the command
	 * @param $command string - the name of the command
	 * @param $ver string - the version requirement for the gem, or null for any version
	 * @param $args string - arguments to pass to the command
	 * @param $out reference to string - stdout result of the command
	 * @param $err reference to string - stderr result of the command
	 * @return int - process exit code, or -1 if the process couldn't be executed
	 */
	static function run($gem, $command, $ver=null, $args="", &$out, &$err) {
		if (!$ver) $ver = '>= 0';
		return self::_run("ruby -rubygems -e 'gem \"$gem\", \"$ver\"' -e 'load \"$command\"' -- $args", $out, $err);
	}
}
