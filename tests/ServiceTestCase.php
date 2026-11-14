<?php

use Grocy\Services\BaseService;
use Grocy\Services\DatabaseService;
use Grocy\Services\LocalizationService;
use Grocy\Services\UsersService;

/**
 * Base de todas las pruebas.
 * Antes de cada prueba cierra las conexiones, copia una base SQLite limpia y deja los servicios sin estado compartido.
 */
abstract class ServiceTestCase extends PHPUnit\Framework\TestCase
{
	protected function setUp(): void{       //el set up automatico se ejecuta antes de cada prueba, no hace falta llamarlo
		parent::setUp();

		// Cerrar conexiones antes de reemplazar la base.
		self::resetServiceState();
		if (!copy(GROCY_DATAPATH . '/grocy_template.db', GROCY_DATAPATH . '/grocy.db')){
			throw new RuntimeException('No se pudo preparar grocy.db para la prueba');
		}
	}

	/**
	 * Limpia conexiones y cachés estáticas de los servicios.
	 */
	public static function resetServiceState(): void
	{
		self::resetStatic(DatabaseService::class, 'DbConnection');
		self::resetStatic(DatabaseService::class, 'DbConnectionRaw');
		self::resetStatic(DatabaseService::class, 'instance');
		self::resetStatic(BaseService::class, 'Instances', []);
		self::resetStatic(UsersService::class, 'UserSettingsCache', []);
		self::resetStatic(LocalizationService::class, 'InstanceMap', []);

		gc_collect_cycles();
	}

	private static function resetStatic(string $class, string $property, mixed $value = null): void
	{
		$reflection = new ReflectionClass($class);
		$reflection->getProperty($property)->setValue(null, $value);
	}
}
