<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\ApiKeyService;
use PHPUnit\Framework\TestCase;

// Mock de la consulta
interface ApiKeysQueryStub
{
    public function where($campo, ...$valor);
    public function fetch();
    public function delete();
    public function createRow($datos);
}

// Mock de acceso a la DB
interface ApiKeysDatabaseStub
{
    public function api_keys();
    public function users($userId);
}

// Mock para guardar el registro
interface ApiKeyRowStub
{
    public function save();
}

class ApiKeyServiceTest extends TestCase
{
    // Función auxiliar para crear una instancia de ApiKeyService con un objeto de base de datos simulado.
    private function crearServicioConDB(object $db): ApiKeyService
    {
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();
        $propiedad = new \ReflectionProperty(
            ApiKeyService::class,
            'DB'
        );
        $propiedad->setValue($service, $db);
        
        return $service;
    }

    public function test_NoPermiteObtenerOCrearClaveDefault(): void
    {
        // Arrange: Instanciamos el servicio sin inicializar la base de datos.
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        // Act: Ejecutamos el método que queremos probar.
        $resultado = $service->GetOrCreateApiKey(
            ApiKeyService::API_KEY_TYPE_DEFAULT
        );

        // Assert: Verificamos el resultado esperado (null).
        $this->assertNull($resultado);
    }

    public function test_RechazaClaveNula(): void
    {
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        $resultado = $service->IsValidApiKey(null);
        
        $this->assertFalse($resultado);
    }

    public function test_RechazaClaveVacia(): void
    {
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        $resultado = $service->IsValidApiKey('');

        $this->assertFalse($resultado);
    }

    public function test_RechazaApiKeyInexistente(): void
    {
        // Arrange: simulamos una consulta sin resultados.
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->IsValidApiKey('clave-inexistente');

        // Assert
        $this->assertFalse($resultado);
    }

    public function test_ObtieneIdDeApiKey(): void
    {
        // Arrange
        $registro = (object) ['id' => 15];
        $consulta = $this->createStub(ApiKeysQueryStub::class);

        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($registro);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetApiKeyId('abc123');

        // Assert
        $this->assertSame(15, $resultado);
    }


    public function test_ObtieneUsuarioDeApiKeyExistente(): void
    {
        // Arrange: prepara los datos simulados.
        $registro = (object) ['user_id' => 7];
        $usuario = (object) [
            'id' => 7,
            'username' => 'admin'
        ];

        // Simulamos la consulta de la API key.
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($registro);

        // Simula la base de datos.
        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);
        $db->method('users')->willReturn($usuario);

        // Crea el servicio sin abrir la BD real.
        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetUserByApiKey('abc123');

        // Assert
        $this->assertSame($usuario, $resultado);
    }

    public function test_ObtieneUsuarioDeApiKeyInexistente(): void
    {
        // Arrange: simulamos una consulta sin resultados.
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetUserByApiKey('clave-inexistente');

        // Assert
        $this->assertNull($resultado);
    }

    public function test_EliminaApiKey(): void
    {
        // Arrange: preparamos la consulta simulada.
        $consulta = $this->createMock(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);

        // Expect: verifica que delete() se invoque una vez.
        $consulta->expects($this->once())->method('delete');

        // Simula el acceso a la BD.
        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act: ejecutar el método real.
        $service->RemoveApiKey('abc123');
    }

    public function test_ReutilizaApiKeyExistente(): void
    {
        // Arrange: simulamos una clave existente.
        $registro = (object) [
            'api_key' => 'clave-existente-123'
        ];

        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($registro);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetOrCreateApiKey(
            ApiKeyService::API_KEY_TYPE_SPECIAL_PURPOSE_CALENDAR_ICAL
        );

        // Assert
        $this->assertSame('clave-existente-123', $resultado);
    }

    public function test_GeneraApiKeyConFormatoCorrecto(): void
    {
        // Arrange: simulamos el registro y su guardado.
        $fila = $this->createStub(ApiKeyRowStub::class);
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->CreateApiKey();

        // Assert: comprobamos el formato hexadecimal de 48 caracteres.
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{48}$/',
            $resultado
        );
    }

    public function test_GuardaApiKeyGenerada(): void
    {
        // Arrange: creamos un mock del registro.
        $fila = $this->createMock(ApiKeyRowStub::class);

        // Verifica que save() se invoque una vez.
        $fila->expects($this->once())->method('save');

        // Creación del registro simulada.
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDb($db);

        // Act: genera una clave API.
        $service->CreateApiKey();
    }
} 