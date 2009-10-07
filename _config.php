<?php

// Trigger rebuild in many situations
if (Director::isDev() || @$_GET['flush']) CompassTheme::rebuild();

// Make base tags in css get rewritten
Requirements::set_backend(new CSSAbsolutePathRewriter());