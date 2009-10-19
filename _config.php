<?php

// This triggers compass rebuilding when a ContentController is accessed
Object::add_extension('SiteTree', 'CompassTheme_RebuildDecorator');

// Make base tags in css get rewritten
Requirements::set_backend(new CSSAbsolutePathRewriter());
