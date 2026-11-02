<?php

use Grocy\Services\BaseService;
use Grocy\Services\DatabaseService;
use Grocy\Services\LocalizationService;
use Grocy\Services\UsersService;

/**
 * Base de todas las pruebas.
 *
 * Antes de cada prueba copia una base SQLite limpia y borra las instancias
 * que Grocy guarda en variables estáticas.
 */
abstract class ServiceTestCase extends PHPUnit\Framework\TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		copy(GROCY_DATAPATH . '/grocy_template.db', GROCY_DATAPATH . '/grocy.db');

		$this->resetStatic(DatabaseService::class, 'DbConnection');
		$this->resetStatic(DatabaseService::class, 'DbConnectionRaw');
		$this->resetStatic(DatabaseService::class, 'instance');
		$this->resetStatic(BaseService::class, 'Instances', []);
		$this->resetStatic(UsersService::class, 'UserSettingsCache', []);
		$this->resetStatic(LocalizationService::class, 'InstanceMap', []);
	}

	private function resetStatic(string $class, string $property, mixed $value = null): void
	{
		$reflection = new ReflectionClass($class);
		$reflection->getProperty($property)->setValue(null, $value);
	}
}
