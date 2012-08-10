<?php

// This triggers compass rebuilding when a ContentController is accessed
if(class_exists('SiteTree')) {
	Object::add_extension('SiteTree', 'Compass_RebuildDecorator');
}

if(class_exists('LeftAndMain')) {
	Object::add_extension('LeftAndMain', 'Compass_RebuildDecorator');
}