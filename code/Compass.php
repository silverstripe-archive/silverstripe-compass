<?php

/**
 * @package compass
 */
class Compass extends Controller {

	static $url_handlers = array(
		'$Action' => '$Action'
	);

	/** 
	 * @var bool Are compass errors actually errors, or should we just ignore them? 
	 *			True means complain, false means don't, null means don't complain on live servers, do otherwise 
	 */
	static $errors_are_errors = null;
	
	/**
	 * @var bool Set to true to force no automatic rebuilding, even if isDev() is true or flush is passed and the gems are all available
	 */ 
	static $force_no_rebuild = false;

	/**
	 * @var float Which version of sass should we use
	 */
	static $sass_version = '3';

	/** 
	 * @var array map of required gems for each version
	 */
	static $required_gems = array(
		'2' => array(
			'yard' => '', 'maruku' => '', 'haml' => '~> 2.2', 'compass' => '~> 0.8.0', 'compass-colors' => ''
		),
		'3' => array(
			'yard' => '', 'maruku' => '', 'haml' => '~> 3.1', 'compass' => '~> 0.11.5', 'compass-colors' => ''
		),
		'latest' => array(
			'yard' => '', 'maruku' => '', 'haml-edge' => '', 'compass' => '', 'compass-colors' => ''
		)
	);
	
	/** 
	 * @var bool Internal cache variable - is the version of rubygems currently available good enough? 
	 */
	static $check_gems_result = null;
	
	protected function checkGems() {
		if (self::$check_gems_result === null) {
			
			self::$check_gems_result = true;
			
			foreach (self::$required_gems[self::$sass_version] as $gem => $version) {
				if(!$version) $version = ">= 0";
				
				if($error = Rubygems::require_gem($gem, $version)) {
					self::$check_gems_result = $error;
				}
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
		
		// And doesn't have sass  commands in it
		if(is_dir($dir . DIRECTORY_SEPARATOR . 'sass')) {
			if (!@$_GET['force'] && array_search('--force', (array)@$_GET['args']) === false) {
				echo "\nERROR:\n\nPath $dir is already a compass or sass based theme or module.\nUse --force to force overwriting\n\n";
				exit();
			}
		}
		
		self::generate_config($dir);
		
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
				
				$res = Rubygems::run(self::$required_gems[self::$sass_version], 'css2sass', "'$from_' '$to_'", $out, $err);
				
				if ($res != 0) {
					echo "\nError converting ".Director::makeRelative($from_)."\n\nError from css2sass was:\n$err";
				}
			}
		}
	}

	/**
	 * Utility function that returns an array of all themes.
	 * Logic taken from late 2.4 ManifestBuilder - kept here for 2.3 and earlier 2.4 compatibility
	 *
	 * @return array
	 */
	protected function getAllThemes() {
		$baseDir = BASE_PATH . DIRECTORY_SEPARATOR . THEMES_DIR;
		
		$themes = array();
		
		if(is_dir($baseDir)) {
			$dir = dir($baseDir);
		
			while ($file = $dir->read()) {
				$fullPath = $baseDir . DIRECTORY_SEPARATOR . $file;
				if (strpos($file, '.') === false && is_dir($fullPath)) $themes[$file] = $file;
			}
		}
		
		return $themes;
	}

	/**
	 * Utility function that returns an array of all modules.
	 * 
	 * @return array Map of module names to their path
	 */
	protected function getAllModules() {
		$modules = array();
		
		if(class_exists('SS_ClassLoader')) {
			// SilverStripe 3.x
			$modules = SS_ClassLoader::instance()->getManifest()->getModules();
		} else {
			// SilverStripe 2.x
			global $_CLASS_MANIFEST;
			$paths = $_CLASS_MANIFEST;
			foreach ($paths as $path) {
				if (preg_match('#'.preg_quote(BASE_PATH, '#').'/([^/]+)/#', $path, $matches)) {
					$modules[$matches[1]] = BASE_PATH . DIRECTORY_SEPARATOR . $matches[1];
				}
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
			
			foreach ($this->getAllModules() as $name => $path) {
				// If this is in the compass module, skip
				if ($name == 'compass') continue;
				
				if (file_exists($path . DIRECTORY_SEPARATOR . 'config.rb')) {
					if ($verbose) echo "\nRebuilding module: $name\n";
					$this->rebuildDirectory($path);
				}
			}
		}
		
		if ($verbose) echo "\nRebuild succesfull\n";
	}
	
	protected function rebuildDirectory($dir) {
		if (!is_dir($dir)) return self::error("Could not rebuild $dir, as it doesn't exist");
		
		self::generate_config($dir);
		
		$orig = getcwd();
		chdir($dir);
		
		$args = (self::$sass_version > 2) ? "compile -e production ": "";
		if(isset($_REQUEST['flush'])) $args .= "--force";
		
		$code = Rubygems::run(self::$required_gems[self::$sass_version], "compass",  $args, $out, $err);
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
		foreach (self::$required_gems[self::$sass_version] as $gem => $version) {
			if (is_numeric($gem)) { $gem = $version; $version = null; }
			if ($error = Rubygems::require_gem($gem, $version, true)) echo $error;
		}
		
		if ($verbose) echo "\nGem update succesfull\n";
	}
	
	/**
	 * Generate a configuration file for a given directory
	 *
	 * @param string - folder name
	 */
	protected function generate_config($dir) {
		if(!is_file($dir . DIRECTORY_SEPARATOR . 'config.rb')) {
			file_put_contents(
				$dir . DIRECTORY_SEPARATOR . 'config.rb', 
				$this->customise(new ArrayData(array(
					'TmpDir' => Controller::join_links(TEMP_FOLDER, '.sass-cache')
				)))->renderWith('CompassConfig')
			);
		}
	}
}

/**
 * Hook in logic to potentially rebuild css from compass files when a request is received.
 * Works both with {@link LeftAndMain} and {@link ContentController} classes.
 *
 * Rebuild on request happens if sapphire is in dev mode or flush is passed as a GET variable, except when
 * Compass::$force_no_rebuild is true, or we're currently running a test
 *
 * @package compass
 */
class Compass_RebuildDecorator extends DataObjectDecorator {
	
	function init() {
		// Don't auto-rebuild if explicitly disabled
		if (Compass::$force_no_rebuild) return;
		
		// Don't auto-rebuild in test mode
		$runningTest = class_exists('SapphireTest',false) && SapphireTest::is_running_test();
		if ($runningTest) return;

		// If we are in dev mode, or flush called, auto-rebuild
		if (Director::isDev() || @$_GET['flush']) singleton('Compass')->rebuild();
	}
	
	function contentcontrollerInit() {
		return $this->init();
	}
}
