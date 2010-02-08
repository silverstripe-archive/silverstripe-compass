<?php

class Compass extends Controller {

	static $url_handlers = array(
		'$Action' => '$Action'
	);

	/** Are compass errors actually errors, or should we just ignore them? 
	 *  True means complain, false means don't, null means don't complain on live servers, do otherwise 
	 */
	static $errors_are_errors = null;
	
	/** What gems are required for compass to work? */
	static $required_gems = array('yard', 'maruku', 'haml', 'compass', 'compass-colors');
	
	/** Internal cache variable - is the version of rubygems currently available good enough? */
	static $check_gems_result = null;
	
	protected function checkGems() {
		if (self::$check_gems_result === null) {
			
			self::$check_gems_result = true;
			
			foreach (self::$required_gems as $gem) {
				if ($error = Rubygems::require_gem($gem)) self::$check_gems_result = $error;
			}
		}
		
		return self::$check_gems_result;
	}

	static function error($message) {
		// If errors_are_errors is null, work out if it should be true or false based on server mode
		if (self::$errors_are_errors === null) {
			$runningTest = class_exists('SapphireTest',false) && SapphireTest::is_running_test();
			self::$errors_are_errors = Director::isDev() && !$runningTest;
		}

		// Then raise the actual error (if errors are errors)
		if (self::$errors_are_errors) user_error('Compass Error:<br />' . preg_replace('/[\r\n]+/', '<br />', $message), E_USER_ERROR);
		return false;
	}
	
	function init() {
		parent::init();
		
		// We allow access to this controller regardless of live-status or ADMIN permission only if on CLI. 
		// Access to this controller is always allowed in "dev-mode", or of the user is ADMIN.
		$canAccess = (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"));
		if (!$canAccess) return Security::permissionFailure($this);
	}
	
	/**
	 * Convert a css based theme to a sass based one
	 * 
	 * Designed to be called as a sake command (sapphire/sake dev/compass/convert --theme=blackcandy)
	 * 
	 * Set $verbose to anything positive to output status (calling as a controller passed HTTPRequest, which is good enough)
	 */
	function convert($verbose = false) {
		$dir = null;
		
		if (@$_GET['theme']) $dir = THEMES_PATH . DIRECTORY_SEPARATOR . $_GET['theme'];
		if (@$_GET['module']) $dir = BASE_PATH . DIRECTORY_SEPARATOR . $_GET['module'];

		// Check for no arguments
		if (!$dir) {
			echo "\n";
			echo "Usage: convert --module=module | convert --theme=theme\n";
			echo "Alternatively, use convertTheme or convertModule\n\n";
			exit();
		}
		
		// Check the directory exists
		if (!is_dir($dir)) {
			echo "\nERROR:\n\nPath $dir doesn't exist.\n\n";
			exit();
		}
		
		// And has css in it
		if (!is_dir($dir . DIRECTORY_SEPARATOR . 'css')) {
			echo "\nERROR:\n\nPath $dir doesn't contain any css\n\n";
			exit();
		}
		
		// And doesn't have sass or compass commands in it
		if (is_file($dir . DIRECTORY_SEPARATOR . 'config.rb') || is_dir($dir . DIRECTORY_SEPARATOR . 'sass')) {
			if (!@$_GET['force'] && array_search('--force', (array)@$_GET['args']) === false) {
				echo "\nERROR:\n\nPath $dir is already a compass or sass based theme or module.\nUse --force to force overwriting\n\n";
				exit();
			}
		}
		
		// Create the config.rb file to configure compass
		// @todo Allow tweaking this definition via command line arguments
		file_put_contents($dir . DIRECTORY_SEPARATOR . 'config.rb', '
# Require any additional compass plugins here.
require "compass-colors"

project_type = :stand_alone
# Set this to the root of your project when deployed:
http_path = "/"
css_dir = "css"
sass_dir = "sass"
images_dir = "images"
javascripts_dir = "javascript"
output_style = :compact
# To enable relative paths to assets via compass helper functions. Uncomment:
relative_assets = true
');
		
		// Make sure the gems we need are available
		if (($error = $this->checkGems()) !== true) return self::error($error);

		$this->recursivelyConvert($dir.DIRECTORY_SEPARATOR.'css', $dir.DIRECTORY_SEPARATOR.'sass');
		
		if ($verbose) echo "\nConversion succesfull\n";
	}
	
	protected function recursivelyConvert($from, $to) {
		
		$dir = dir($from);
		if (!is_dir($to)) mkdir($to);
		
		while ($entry = $dir->read()) {
			
			$from_ = $from.DIRECTORY_SEPARATOR.$entry;
			
			if (fnmatch('.*', $entry)) continue;
			
			if (is_dir($from_)) {
				$this->recursivelyConvert($from_, $to.DIRECTORY_SEPARATOR.$entry);
			}
			
			else if (fnmatch('*.css', $entry) && is_file($from_)) {
				
				$to_ = $to.DIRECTORY_SEPARATOR.preg_replace('/.css$/', '.sass', $entry);
				
				$res = Rubygems::run('haml', 'css2sass', null, "'$from_' '$to_'", $out, $err);
				
				if ($res != 0) {
					echo "\nError converting ".Director::makeRelative($from_)."\n\nError from css2sass was:\n$err";
				}
			}
		}
	}

	/**
	 * Utility function that returns an array of all themes.
	 * Logic taken from late 2.4 ManifestBuilder - kept here for 2.3 and earlier 2.4 compatibility
	 */
	protected function getAllThemes() {
		$baseDir = BASE_PATH . DIRECTORY_SEPARATOR . THEMES_DIR;
		
		$themes = array();
		$dir = dir($baseDir);
		
		while ($file = $dir->read()) {
			$fullPath = $baseDir . DIRECTORY_SEPARATOR . $file;
			if (strpos($file, '.') === false && is_dir($fullPath)) $themes[$file] = $file;
		}
		
		return $themes;
	}

	/**
	 * Utility function that returns an array of all modules.
	 * Logic taken from sapphire/dev/ModelViewer.php
	 */
	protected function getAllModules() {
		$modules = array();

		global $_CLASS_MANIFEST;
		foreach ($_CLASS_MANIFEST as $path) {
			if (preg_match('#'.preg_quote(BASE_PATH, '#').'/([^/]+)/#', $path, $matches)) {
				$modules[$matches[1]] = $matches[1];
			}
		}
		
		return $modules;
	}
	
	/**
	 * Convert the sass files to css files
	 * 
	 * Called automatically on dev machines, and when flush=all. Can also be called as a sake command (sapphire/sake dev/compass/rebuild)
	 * 
	 * Set $verbose to anything positive to output status (calling as a controller passed HTTPRequest, which is good enough)
	 * Note that errors get output independent of this argument - use errors_are_errors = false to suppress them.
	 */
	function rebuild($verbose = false) {

		// Make sure the gems we need are available
		if (($error = $this->checkGems()) !== true) return self::error($error);
		
		$dir = null;
		if (@$_GET['theme']) $dir = THEMES_PATH . DIRECTORY_SEPARATOR . $_GET['theme'];
		if (@$_GET['module']) $dir = BASE_PATH . DIRECTORY_SEPARATOR . $_GET['module'];

		if ($dir) {
			$this->rebuildDirectory($dir);
		}
		else {
			if ($verbose) echo "\nRebuilding all\n";
			
			foreach ($this->getAllThemes() as $theme) {
				$dir = THEMES_PATH . DIRECTORY_SEPARATOR . $theme;
				
				if (file_exists($dir . DIRECTORY_SEPARATOR . 'config.rb')) {
					if ($verbose) echo "\nRebuilding theme: $theme\n";
					$this->rebuildDirectory($dir);
				}
			}
			
			foreach ($this->getAllModules() as $module) {
				// If this is in the compass module, skip
				if ($module == 'compass') continue;
				$dir = BASE_PATH . DIRECTORY_SEPARATOR . $module;
				
				if (file_exists($dir . DIRECTORY_SEPARATOR . 'config.rb')) {
					if ($verbose) echo "\nRebuilding module: $module\n";
					$this->rebuildDirectory($dir);
				}
			}
		}
		
		if ($verbose) echo "\nRebuild succesfull\n";
	}
	
	protected function rebuildDirectory($dir) {
		if (!is_dir($dir)) return self::error("Could not rebuild $dir, as it doesn't exist");
		
		$orig = getcwd();
		
		chdir($dir);
		$code = Rubygems::run("compass", "compass", null, @$_GET['flush'] ? " --force" : "", $out, $err);
		chdir($orig);
		
		if ($code !== 0) return self::error($err);	
	}

	/**
	 * Make sure the compass and haml gems are up to date
	 *
	 * If the gems are not present, the system will install them automatically, but won't update them after that for speeds sake.
	 * Call this from sake to ensure you've got the most up-to-date version 
	 * 
	 * Designed to be called as a sake command (sapphire/sake dev/compass/updategems)
	 * 
	 * Set $verbose to anything positive to output status (calling as a controller passed HTTPRequest, which is good enough)
	 * Note that errors get output independent of this argument - use errors_are_errors = false to suppress them.
	 */
	function updategems($verbose = false) {
		foreach (self::$required_gems as $gem) {
			if ($error = Rubygems::require_gem($gem, true)) echo $error;
		}
		
		if ($verbose) echo "\nGem update succesfull\n";
	}
	
}

// If we are in dev mode, or flush is called, we use compass to rebuild the css from the sass. We do this on ContentControllers only, to avoid some issues with tests, etc.
class Compass_RebuildDecorator extends DataObjectDecorator {
	function contentcontrollerInit($controller) {
		if (Director::isDev() || @$_GET['flush']) singleton('Compass')->rebuild();
	}
}
