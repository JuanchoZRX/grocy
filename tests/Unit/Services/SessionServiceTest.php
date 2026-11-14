<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\SessionService;
use PHPUnit\Framework\TestCase;

// Para simular las consultas
interface SessionsQueryStub
{
    public function where($condicion, ...$valores);
    public function fetch();
    public function delete();
    public function createRow($datos);
}

// Para simular la base de datos
interface SessionsDatabaseStub
{
    public function sessions();
    public function users($userId = null);
}

// Simula un registro de sesión para comprobar que se usa save()
interface SessionRowStub
{
    public function save();
}

// Simula la consulta para obtener el usuario predeterminado
interface DefaultUserQueryStub
{
    public function orderBy($campo);
    public function limit($cantidad);
    public function fetch();
}

class SessionServiceTest extends TestCase
{
    // Crea el servicio con la BD simulada
    private function crearServicioConDB(object $db): SessionService
    {
        $service = (new \ReflectionClass(SessionService::class))->newInstanceWithoutConstructor();

        $propiedad = new \ReflectionProperty(SessionService::class, 'DB');
        $propiedad->setValue($service, $db);

        return $service;
    }

    // Prueba que ValidateToken devuelve false para un token vacio
    public function test_RechazaTokenVacio(): void
    {
        // Arrange
        $service = (new \ReflectionClass(SessionService::class))->newInstanceWithoutConstructor();

        // Act
        $resultado = $service->ValidateToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, '');

        // Assert
        $this->assertFalse($resultado);
    }

    // Prueba que ValidateToken devuelve false para un token inexistente
    public function test_RechazaTokenInexistente(): void
    {
        // Arrange: consulta sin resultados.
        $consulta = $this->createStub(SessionsQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->ValidateToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 'token-inexistente');

        // Assert
        $this->assertFalse($resultado);
    }

    // Prueba que GetUserByToken devuelve el usuario asociado a una sesión existente
    public function test_ObtieneUsuarioDeTokenExistente(): void
    {
        // Arrange: simular una sesión existente.
        $registro = (object) ['user_id' => 7];

        $usuario = (object) ['id' => 7, 'username' => 'admin'];

        $consulta = $this->createStub(SessionsQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($registro);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturn($consulta);
        $db->method('users')->willReturn($usuario);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetUserByToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 'token-valido');

        // Assert
        $this->assertSame($usuario, $resultado);
    }

    // Prueba de GetUserByToken para devolver null con un token inexistente
    public function test_ObtieneUsuarioDeTokenInexistente(): void
    {
        // Arrange: simular que no existe una sesión.
        $consulta = $this->createStub(SessionsQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetUserByToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 'token-inexistente');

        // Assert
        $this->assertNull($resultado);
    }

    // Prueba de GetDefaultUser para devolver el usuario predeterminado
    public function test_ObtieneUsuarioPredeterminado(): void
    {
        // Arrange
        $usuario = (object) ['id' => 1, 'username' => 'admin'];

        $consulta = $this->createStub(DefaultUserQueryStub::class);
        $consulta->method('orderBy')->willReturn($consulta);
        $consulta->method('limit')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($usuario);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('users')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->GetDefaultUser();

        // Assert
        $this->assertSame($usuario, $resultado);
    }

    // Prueba que DeleteToken solicita eliminar el token indicado
    public function test_EliminaTokenExistente(): void
    {
        // Arrange: simula la consulta del token.
        $consultaToken = $this->createMock(SessionsQueryStub::class);

        // Expect: verificar que se solicite eliminar el token.
        $consultaToken->expects($this->once())->method('delete');
        $consultaToken->method('where')->willReturn($consultaToken);

        // Simula la consulta para eliminar tokens expirados.
        $consultaExpirados = $this->createStub(SessionsQueryStub::class);
        $consultaExpirados->method('where')->willReturn($consultaExpirados);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturnOnConsecutiveCalls($consultaToken,$consultaExpirados);
        $service = $this->crearServicioConDB($db);

        // Act
        $service->DeleteToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 'token-de-prueba');
    }

    // Prueba de DeleteToken para eliminar tokens expirados
    public function test_EliminaTokensExpirados(): void
    {
        // Arrange
        $consultaToken = $this->createStub(SessionsQueryStub::class);
        $consultaToken->method('where')->willReturn($consultaToken);

        $consultaExpirados = $this->createMock(SessionsQueryStub::class);
        $consultaExpirados->method('where')->willReturn($consultaExpirados);
        $consultaExpirados->expects($this->once())->method('delete');

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturnOnConsecutiveCalls($consultaToken, $consultaExpirados);
        $service = $this->crearServicioConDB($db);

        // Act
        $service->DeleteToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 'token-de-prueba');
    }

    // Prueba que CreateToken genera un token con el formato correcto
    public function test_GeneraTokenConFormatoCorrecto(): void
    {
        // Arrange
        $fila = $this->createStub(SessionRowStub::class);

        $consulta = $this->createStub(SessionsQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);
        $consulta->method('where')->willReturn($consulta);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->CreateToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 7, 'Microsoft Edge');

        // Assert
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/',$resultado);
    }

    // Prueba que CreateToken solicita guardar el registro de sesión generado
    public function test_GuardaTokenGenerado(): void
    {
        // Arrange
        $fila = $this->createMock(SessionRowStub::class);

        // Expect
        $fila->expects($this->once())->method('save');

        $consulta = $this->createStub(SessionsQueryStub::class);
        $consulta->method('createRow')->willReturn($fila);
        $consulta->method('where')->willReturn($consulta);

        $db = $this->createStub(SessionsDatabaseStub::class);
        $db->method('sessions')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $service->CreateToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, 7, 'Microsoft Edge');
    }
}