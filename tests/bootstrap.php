<?php

define('GROCY_DATAPATH', __DIR__ . '/.tmp');
define('GROCY_USER_ID', 1);

if (!is_dir(GROCY_DATAPATH))
{
	mkdir(GROCY_DATAPATH, 0777, true);
}

require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../config-dist.php';

// LocalizationService usa GROCY_LOCALE, que normalmente define LocaleMiddleware
if (!defined('GROCY_LOCALE'))
{
	define('GROCY_LOCALE', GROCY_DEFAULT_LOCALE);
}
