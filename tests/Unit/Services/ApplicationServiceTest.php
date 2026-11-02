<?php

use Grocy\Services\ApplicationService;

/**
 * Prueba humo: confirma que la base de pruebas y las migraciones funcionan.
 */
final class ApplicationServiceTest extends ServiceTestCase
{
	public function testLeeLaBaseMigrada(): void
	{
		$systemInfo = (new ApplicationService())->GetSystemInfo();

		$this->assertSame(phpversion(), $systemInfo['php_version']);
		$this->assertGreaterThan(0, $systemInfo['db_version']);
	}
}
