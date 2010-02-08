# Compass Module

The Compass module for SilverStripe seamlessly integrates Sass and Compass in SilverStripe modules and themes. You write your styles in sass, and the module automatically compiles these sass files to css.

## Maintainer Contact

[Hamish Friedlander](mailto:hamish (at) silverstripe (dot) com)

## Compass

<cite>
Compass is a stylesheet authoring tool that uses the Sass stylesheet language to make your stylesheets smaller and your web site easier to maintain. Compass provides ports of the best of breed css frameworks that you can use without forcing you to use their presentational class names. It’s a new way of thinking about stylesheets that must be seen in action!

[From the compass homepage](http://wiki.github.com/chriseppstein/compass/)
</cite>

## Prerequisites

You need ruby 1.8.6 or better and rubygems 1.2 or better installed on your development system. The module will download & install all required gems automatically

Normal usage is to commit the built css files to your version control repository during development. This means there are no extra software requirements on the live servers.

## Getting Started

###### Convert your theme to sass

<code><pre>
sapphire/sake dev/compass/convert --theme=blackcandy
</pre></code>

###### That's it

In dev mode, whenever flush is passed as a get variable, or when you call sapphire/sake dev/compass/rebuilt called, Compass is called to rebuild your css

By default Compass will only update css for sass files that have changed. If flush is passed as a get variable, we force the recompile of all css files.

###### If you change your mind

The original css files are still available at css/.backup

## WARNING

Once you've converted a theme to Compass, the css files in the css directory will be automatically overwritten by the compiled sass. It is not recommended to mix sass-compiled and non-compiled css in the same directory

## Commands and configuration variables

###### Convert a theme or module to use Compass

The task dev/compass/convert creates the required config.rb file to mark a theme or module as being compass based, and converts any existing css files into sass files

You need to pass --theme=themename or --module=modulename to specify which theme or module to convert

By default, if there are any sass files already present, the conversion is aborted. You can pass --force to convert anyway, overwriting any existing sass files.

###### Rebuild one or all themes & modules from the command line

The task dev/compass/rebuild rebuilds all Compass themes and modules. You can pass --theme=themename or --module=modulename to just rebuild a specific theme or module

###### Update your gems to the latest version

When rebuild is triggered, the module will download the latest version of Compass if it is not present. Once it has downloaded Compass it will not re-check to see if a new version is available (for speed).

To force updating Compass to the lastest version, use the updategems action

sapphire/sake dev/compass/updategems

###### Share the Compass gem amongst several websites

By default the module installed Compass into the sites temporary directory. This means that each site will have it's own copy of the gems, and that any temp clearing will remove the gems. You can set the constant `SS_GEM_PATH` to a common path.

###### Change error handling

The static variable Compass::$errors_are_errors determines whether a failure to rebuild the css files should cause an error or not. By default it is true in dev mode, and false in live mode (so that using flush=all, which would normally trigger a rebuild, won't break on live servers without Compass' prerequisites).

You can override in _config to either force errors everywhere, or supress errors on dev machines of developers who will not be doing sass edits.

###### Un-compassing a theme or module

The Compass module uses the presence of a config.rb file to detect the location of Compass-based themes and modules. Remove the config.rb file to stop that theme or module from being automatically rebuilt.

###### Base rewriting

As a bonus, the module includes an optional base rewriter for css. Generally, SilverStripe uses relative links to images, which mean that the css works regardless of site base. However in some situations (specifically IE filters, such as the alpha png loader) an absolute path is needed.

In this situation you can use BASE to take the place of the actual base path in the css. The base rewriter will create a new version of the css file for a specific site base, replacing the BASE string with that specific base.

A quick filter example, using a theme name of blackcandy and the underscore hack to target IE:

<code><pre>
	background: url(../images/background.png)
	_background: none
	_filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src=BASEthemes/blackcandy/images/background.png,sizingMethod=image);
</pre></code>

You enable the base rewriter by adding this code to your _config.php

<code><pre>
// Make base tags in css get rewritten
Requirements::set_backend(new CSSAbsolutePathRewriter());
</pre></code>

You'll also need to add some lines to the end of your .htaccess file, to make the rewritten css files (which are put into the assets folder) appear to be in the original css folder

<code><pre>
RewriteCond %{REQUEST_URI} \.css$ 
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule /([^/]*)$ assets/$1 [L]
</pre></code>
