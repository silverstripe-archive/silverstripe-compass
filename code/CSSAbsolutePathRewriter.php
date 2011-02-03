<?php

/**
 * Rewrites CSS files to replace BASE with the current base tag
 *
 * @package compass
 */
class CSSAbsolutePathRewriter extends Requirements_Backend {
	
	function css($file, $media = null) {
		$isurl = (bool)preg_match('{^\w+://}', $file);
		return parent::css($isurl ? $file : $this->baseRewrite($file), $media);
	}

	function customCSS($script, $uniquenessID = null) {
		return parent::customCSS($this->baseReplace($script), $uniquenessID);
	}
	
	function baseReplace($css) {
		return str_replace('BASE', Director::baseURL(), $css);
	}
	
	function baseRewrite($file) {
		if (preg_match('/^(jsparty|sapphire|cms)/', $file)) return $file;
		
		$name = basename($file, '.css');
		$dir  = dirname($file);
		$path = BASE_PATH.'/'.$file;
		
		$hash = sha1(Director::baseURL());
		$tstamp = filemtime($path);
		
		$out = str_replace('/', '_', $dir)."_{$name}_{$hash}_{$tstamp}.css"; 
		
		$lnkname = "{$dir}/{$out}";
		$outname = 'assets/'.$out;
		$outpath = BASE_PATH.'/'.$outname;
		
		if (!file_exists($outpath)) {
			$css = file_get_contents($path);
			if (strpos($css, 'BASE') === FALSE) return $file;
			
			file_put_contents($outpath, $this->baseReplace($css));
		}
		
		return $lnkname;
	}
	
	/**
	 * Default includeInHTML strips out CSS if the file doesn't exist. We need to add it back in if it's a redirected rewrite
	 * @see sapphire/core/Requirements_Backend#includeInHTML($templateFile, $content)
	 */
	function includeInHTML($templateFile, $content) {
		$content = parent::includeInHTML($templateFile, $content);
		$requirements = '';
		
		foreach(array_diff_key($this->css,$this->blocked) as $file => $params) {
			$path = self::path_for_file($file);
			if (!$path && ($path = self::path_for_file('assets/'.basename($file)))) {
				$media = (isset($params['media']) && !empty($params['media'])) ? " media=\"{$params['media']}\"" : "";
				$requirements .= "<link rel=\"stylesheet\" type=\"text/css\"{$media} href=\"$file\" />\n";
			}
		}
		
		return preg_replace("/(<\/head[^>]*>)/i", $requirements . "\\1", $content);
	}
}