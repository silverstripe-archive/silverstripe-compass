<?php

// This triggers compass rebuilding when a ContentController is accessed
Object::add_extension('SiteTree', 'Compass_RebuildDecorator');

// Add the dev/compass tools into the URL map
Director::addRules(20, array('dev/compass' => 'Compass'));

