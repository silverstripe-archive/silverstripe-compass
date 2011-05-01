<?php

// This triggers compass rebuilding when a ContentController is accessed
if(class_exists('SiteTree')) Object::add_extension('SiteTree', 'Compass_RebuildDecorator');
if(class_exists('LeftAndMain')) Object::add_extension('LeftAndMain', 'Compass_RebuildDecorator');

// Add the dev/compass tools into the URL map
Director::addRules(20, array('dev/compass' => 'Compass'));