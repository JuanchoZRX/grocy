<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\ApiKeyService;
use PHPUnit\Framework\TestCase;

// Para simular las consultas de claves API
interface ApiKeysQueryStub
{
    public function where($campo, ...$valor);
    public function fetch();
    public function delete();
    public function createRow($datos);
}

// Para simular el acceso a la base de datos.
interface ApiKeysDatabaseStub
{
    public function api_keys();
    public function users($userId);
}

// Para simular el guardado de un registro de clave API.
interface ApiKeyRowStub
{
    public function save();
}

class ApiKeyServiceTest extends TestCase
{
    // Crea el servicio con la BD simulada.
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

    // Prueba que GetOrCreateApiKey devuelve null para el tipo de clave "default".
    public function test_NoPermiteObtenerOCrearClaveDefault(): void
    {
        // Arrange
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        // Act
        $resultado = $service->GetOrCreateApiKey(
            ApiKeyService::API_KEY_TYPE_DEFAULT
        );

        // Assert
        $this->assertNull($resultado);
    }

    // Prueba que IsValidApiKey devuelve false para una clave nula.
    public function test_RechazaClaveNula(): void
    {
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        $resultado = $service->IsValidApiKey(null);
        
        $this->assertFalse($resultado);
    }

    // Prueba que IsValidApiKey devuelve false para una clave vacía.
    public function test_RechazaClaveVacia(): void
    {
        $service = (new \ReflectionClass(ApiKeyService::class))->newInstanceWithoutConstructor();

        $resultado = $service->IsValidApiKey('');

        $this->assertFalse($resultado);
    }

    // Prueba que IsValidApiKey devuelve false para una clave inexistente.
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

    // Prueba que GetApiKeyId devuelve el ID correcto para una clave existente.
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

    // Prueba de GetUserByApiKey para devolver un usuario con una clave existente.
    public function test_ObtieneUsuarioDeApiKeyExistente(): void
    {
        // Arrange: preparamos los datos simulados.
        $registro = (object) ['user_id' => 7];
        $usuario = (object) [
            'id' => 7,
            'username' => 'admin'
        ];

        // Simulamos la consulta de la API key.
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($registro);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);
        $db->method('users')->willReturn($usuario);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetUserByApiKey('abc123');

        // Assert
        $this->assertSame($usuario, $resultado);
    }

    // Prueba de GetUserByApiKey para devolver null con una clave inexistente.
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

    // Prueba que RemoveApiKey solicita eliminar la clave API indicada.
    public function test_EliminaApiKey(): void
    {
        // Arrange
        $consulta = $this->createMock(ApiKeysQueryStub::class);
        $consulta->method('where')->willReturn($consulta);

        // Expect
        $consulta->expects($this->once())->method('delete');

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $service->RemoveApiKey('abc123');
    }

    // Prueba de GetOrCreateApiKey para reutilizar una clave existente.
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

    // Prueba que CreateApiKey genera una clave hexadecimal de 48 caracteres.
    public function test_GeneraApiKeyConFormatoCorrecto(): void
    {
        // Arrange: simulamos el registro que se va a crear.
        $fila = $this->createStub(ApiKeyRowStub::class);
        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->CreateApiKey();

        // Assert: formato hexadecimal de 48 caracteres.
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{48}$/',
            $resultado
        );
    }

    // Prueba que CreateApiKey solicita guardar el registro de la clave generada.
    public function test_GuardaApiKeyGenerada(): void
    {
        // Arrange
        $fila = $this->createMock(ApiKeyRowStub::class);

        // Expect
        $fila->expects($this->once())->method('save');

        $consulta = $this->createStub(ApiKeysQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);

        $db = $this->createStub(ApiKeysDatabaseStub::class);
        $db->method('api_keys')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $service->CreateApiKey();
    }
} 