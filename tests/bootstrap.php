<?php

define('GROCY_DATAPATH', __DIR__ . '/.tmp');
define('GROCY_USER_ID', 1);

if (!is_dir(GROCY_DATAPATH))
{
	mkdir(GROCY_DATAPATH, 0777, true);
}

require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../config-dist.php';
require __DIR__ . '/ServiceTestCase.php';

$template = GROCY_DATAPATH . '/grocy_template.db';
$database = GROCY_DATAPATH . '/grocy.db';

// Base limpia con todas las migraciones del proyecto.
@unlink($template);
@unlink($database);
Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();
rename($database, $template);
