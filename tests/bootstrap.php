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

// Crear una base limpia con todas las migraciones del proyecto.
@unlink($template);
@unlink($database);

Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();

// Cerrar conexiones antes de copiar: Windows bloquea archivos SQLite abiertos.
ServiceTestCase::resetServiceState();

if (!copy($database, $template))
{
	throw new RuntimeException('No se pudo crear grocy_template.db');
}

@unlink($database);
