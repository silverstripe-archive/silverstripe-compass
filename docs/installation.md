# Installing

Compass is a collection of ruby gems and dependencies. By default the module installed Compass into the sites temporary 
directory. This means that each site will have it's own copy of the gems, and that any temp clearing will remove the gems.

## Share the Compass gem amongst several websites

If you are running several websites with Compass and are using the same version of the gem it is better to install
the gems to a common path and link each site to this path. Set the constant `SS_GEM_PATH` to define your common path in
either your `_ss_environment.php` or `_config.php`.

	define('SS_GEM_PATH', '/usr/bin/');
	
For the module to install the gems you need to ensure that path is writable by the webserver

## Installing the gems manually

When the webserver cannot write to your `SS_GEM_PATH` you may need to install this manually. In a terminal run the
following commands

	gem install compass compass-colors maruku yard haml
	

### Update your gems to the latest version

When rebuild is triggered, the module will download the latest version of Compass if it is not present. Once it has 
downloaded Compass it will not re-check to see if a new version is available (for speed).

To force updating Compass to the lastest version, use the updategems action either via sake or the web browser

sake:
	
	sake dev/compass/updategems
	
web:
	
	http://yoursite.com/dev/compass/updategems
