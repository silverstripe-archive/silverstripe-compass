# Getting Started

Sass has two syntaxes. The new main syntax (as of Sass 3) is known as "SCSS" (for "Sassy CSS"), and is a superset of 
CSS3's syntax. This means that every valid CSS3 stylesheet is valid SCSS as well. SCSS files use the extension .scss.

The second, older syntax is known as the indented syntax (or just "Sass"). Inspired by Haml’s terseness, it’s intended 
for people who prefer conciseness over similarity to CSS. Instead of brackets and semicolons, it uses the indentation 
of lines to specify blocks. Although no longer the primary syntax, the indented syntax will continue to be supported. 
Files in the indented syntax use the extension .sass.

The module provides support for both but no matter what variation you wish to use the folder should be called `sass`.

If you wish to use scss syntax make sure your file extension is `.scss`. If you wish to use sass use the file
extension `.sass` on your files.

## How Compass Works

The module watches for any changes in your theme or modules `sass/` directory and if it detects changes it recompiles
your `.sass` or `.scss` file into a valid `.css` file.

Take for instance the following module called `cms`. You need to create a directory `sass`. This is where you will 
write your sass files.

	cms/
		sass/
			styles.scss
			reset.scss
		css/
			
You should make all your changes in the `sass` directory then the next time you visit your website Compass will
generate the actual css files for you.

	cms/
		sass/
			styles.scss
			reset.scss
		css/
			styles.css
			reset.css
			
You can then link to the paths of the CSS file generated.


## Rebuild Compass Files Manually

The task `dev/compass/rebuild` rebuilds all Compass themes and modules. You can pass --theme=themename or 
--module=modulename to just rebuild a specific theme or module. In devmode this is automatic.

Rebuilding a theme via sake:

	sake dev/compass/rebuild --theme=blackcandy

Rebuilding a module via sake:
	
	sake dev/compass/rebuild --module=cms
 
## Convert your theme to sass

	sapphire/sake dev/compass/convert --theme=blackcandy


In dev mode, whenever flush is passed as a get variable, or when you call sapphire/sake dev/compass/rebuild called, 
Compass is called to rebuild your css

By default Compass will only update css for sass files that have changed. If flush is passed as a get variable, 
we force the recompile of all css files.

The original css files are still available at css/.backup

**WARNING**

Once you've converted a theme to Compass, the css files in the css directory will be automatically overwritten by 
the compiled sass. It is not recommended to mix sass-compiled and non-compiled css in the same directory

## Commands and configuration variables

### Convert a theme or module to use Compass

The task dev/compass/convert creates the required config.rb file to mark a theme or module as being compass based, 
and converts any existing css files into sass files

Converting a theme:

	sake dev/compass/convert --theme=blackcandy

Converting a module:
	
	sake dev/compass/convert --module=cms

By default, if there are any sass files already present, the conversion is aborted. You can pass --force to convert 
anyway, overwriting any existing sass files.


### Change error handling

The static variable Compass::$errors_are_errors determines whether a failure to rebuild the css files should cause
an error or not. By default it is true in dev mode, and false in live mode (so that using flush=all, which would 
normally trigger a rebuild, won't break on live servers without Compass' prerequisites).

You can override in _config to either force errors everywhere, or supress errors on dev machines of developers 
who will not be doing sass edits.

### Un-compassing a theme or module

The Compass module uses the presence of a `config.rb` file to detect the location of Compass-based themes and modules. 
Remove the `config.rb` file to stop that theme or module from being automatically rebuilt.

### Base rewriting

As a bonus, the module includes an optional base rewriter for css. Generally, SilverStripe uses relative links to 
images, which mean that the css works regardless of site base. However in some situations (specifically IE filters, 
such as the alpha png loader) an absolute path is needed.

In this situation you can use BASE to take the place of the actual base path in the css. The base rewriter will 
create a new version of the css file for a specific site base, replacing the BASE string with that specific base.

A quick filter example, using a theme name of blackcandy and the underscore hack to target IE:

	background: url(../images/background.png)
	_background: none
	_filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src=BASEthemes/blackcandy/images/background.png,sizingMethod=image);

You enable the base rewriter by adding this code to your _config.php

	// Make base tags in css get rewritten
	Requirements::set_backend(new CSSAbsolutePathRewriter());

You'll also need to add some lines to the end of your .htaccess file, to make the rewritten css files (which are 
put into the assets folder) appear to be in the original css folder

	RewriteCond %{REQUEST_URI} \.css$ 
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule /([^/]*)$ assets/$1 [L]
