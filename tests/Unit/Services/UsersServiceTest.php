<?php

// Paulo Herrera
// 11 pruebas unitarias para UsersService.php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\UsersService;
use LessQL\Result;
use PHPUnit\Framework\TestCase;

// Simula las consultas
interface UsersQueryStub
{
    public function where($campo, ...$valores);
    public function fetch();
    public function fetchAll();
    public function createRow($datos);
    public function insert($datos);
    public function delete();
}

// Simula la base de datos
interface UsersDatabaseStub
{
    public function users($id = null);
    public function permission_hierarchy();
    public function user_permissions();
    public function user_settings();
    public function users_dto();
}

// Simula una fila
interface UsersRowStub
{
    public function save();
    public function update($datos);
}

final class UsersServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->limpiarCache();
    }

    protected function tearDown(): void
    {
        $this->limpiarCache();
        parent::tearDown();
    }

    private function limpiarCache(): void
    {
        $propiedad = new \ReflectionProperty(UsersService::class, 'UserSettingsCache');
        $propiedad->setValue(null, []);
    }

    private function crearServicioConDB(object $db): UsersService
    {
        // Crea una instancia de UsersService sin llamar al constructor 
        $service = (new \ReflectionClass(UsersService::class))->newInstanceWithoutConstructor();

        $propiedad = new \ReflectionProperty(BaseService::class, 'DB');
        $propiedad->setValue($service, $db);

        return $service;
    }

    // Prepara una consulta con el resultado que necesita cada prueba
    private function crearConsulta($resultado): object
    {
        $consulta = $this->createStub(UsersQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($resultado);

        return $consulta;
    }

    // 1. CreateUser
    // Prueba de CreateUser para crear un nuevo usuario
    public function test_CreaUsuario(): void
    {
        $usuario = (object) ['id' => 15];
        $fila = $this->createMock(UsersRowStub::class);
        $fila->expects($this->once())->method('save')->willReturn($usuario);

        $consulta = $this->createMock(UsersQueryStub::class);
        $consulta->expects($this->once())->method('createRow')
            ->with($this->callback(function ($datos) {
                return $datos['username'] === 'paulo'
                    && $datos['first_name'] === 'Paulo'
                    && $datos['last_name'] === 'Herrera'
                    && password_verify('Clave123!', $datos['password']);
            }))->willReturn($fila);

        $jerarquia = $this->createStub(UsersQueryStub::class);
        $jerarquia->method('where')->willReturn($jerarquia);
        $jerarquia->method('fetchAll')->willReturn([(object) ['id' => 3]]);

        $permisos = $this->createMock(UsersQueryStub::class);
        $permisos->expects($this->once())->method('insert')
            ->with([['user_id' => 15, 'permission_id' => 3]]);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('users')->willReturn($consulta);
        $db->method('permission_hierarchy')->willReturn($jerarquia);
        $db->method('user_permissions')->willReturn($permisos);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->CreateUser('paulo', 'Paulo', 'Herrera', 'Clave123!');

        $this->assertSame($usuario, $resultado);
    }

    // 2. DeleteUser
    // Prueba de DeleteUser para eliminar un usuario existente
    public function test_EliminaUsuario(): void
    {
        $consulta = $this->createMock(UsersQueryStub::class);
        $consulta->expects($this->once())->method('delete');

        $db = $this->createMock(UsersDatabaseStub::class);
        $db->expects($this->once())->method('users')->with(15)->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $service->DeleteUser(15);
    }

    // 3. EditUser
    // Prueba de EditUser para actualizar un usuario existente
    public function test_EditaUsuarioConNuevaContrasena(): void
    {
        $consulta = $this->crearConsulta((object) ['id' => 15]);

        $fila = $this->createMock(UsersRowStub::class);
        $fila->expects($this->once())->method('update')
            ->with($this->callback(function ($datos) {
                return $datos['username'] === 'ana'
                    && $datos['first_name'] === 'Ana'
                    && $datos['last_name'] === 'Lopez'
                    && password_verify('Nueva123!', $datos['password']);
            }));

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('users')->willReturnCallback(
            fn($id = null) => $id === null ? $consulta : $fila
        );

        $service = $this->crearServicioConDB($db);
        $service->EditUser(15, 'ana', 'Ana', 'Lopez', 'Nueva123!');
    }

    // 4. GetUserSetting
    // Prueba de GetUserSetting para obtener la preferencia de un usuario
    public function test_ObtienePreferencia(): void
    {
        $consulta = $this->crearConsulta((object) ['value' => 'oscuro']);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('user_settings')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetUserSetting(15, 'qa_tema');

        $this->assertSame('oscuro', $resultado);
    }

    // 5. GetUserSettings
    // Prueba de GetUserSettings para obtener todas las preferencias de un usuario
    public function test_ObtieneTodasLasPreferencias(): void
    {
        $consulta = $this->createStub(UsersQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetchAll')->willReturn([
            (object) ['key' => 'qa_tema', 'value' => 'oscuro'],
            (object) ['key' => 'qa_idioma', 'value' => 'es']
        ]);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('user_settings')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetUserSettings(15);

        $this->assertSame('oscuro', $resultado['qa_tema']);
        $this->assertSame('es', $resultado['qa_idioma']);
    }

    // 6. GetUsersAsDto
    // Prueba de GetUsersAsDto para obtener los usuarios como un DTO
    public function test_ObtieneUsuariosComoDto(): void
    {
        $dto = $this->createStub(Result::class);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('users_dto')->willReturn($dto);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetUsersAsDto();

        $this->assertSame($dto, $resultado);
    }

    // 7. SetUserSetting
    // Prueba de SetUserSetting para guardar una nueva preferencia de usuario
    public function test_GuardaPreferenciaNueva(): void
    {
        $fila = $this->createMock(UsersRowStub::class);
        $fila->expects($this->once())->method('save');

        $consulta = $this->createMock(UsersQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);
        $consulta->expects($this->once())->method('createRow')
            ->with([
                'user_id' => 15,
                'key' => 'qa_tema',
                'value' => 'oscuro'
            ])->willReturn($fila);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('user_settings')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $service->SetUserSetting(15, 'qa_tema', 'oscuro');
    }

    // 8. DeleteUserSetting
    // Prueba de DeleteUserSetting para eliminar una preferencia de usuario
    public function test_EliminaPreferencia(): void
    {
        $consulta = $this->createMock(UsersQueryStub::class);
        $consulta->expects($this->once())->method('where')->with('user_id = :1 AND key = :2', 15, 'qa_tema')->willReturn($consulta);
        $consulta->expects($this->once())->method('delete');

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('user_settings')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $service->DeleteUserSetting(15, 'qa_tema');
    }

    // 9. Editar sin cambiar la contrasena
    // Prueba de EditUser para actualizar un usuario sin cambiar la contrasena
    public function test_EditaUsuarioSinCambiarContrasena(): void
    {
        $consulta = $this->crearConsulta((object) ['id' => 15]);

        $fila = $this->createMock(UsersRowStub::class);
        $fila->expects($this->once())->method('update')
            ->with([
                'username' => 'ana',
                'first_name' => 'Ana',
                'last_name' => 'Lopez',
                'picture_file_name' => null
            ]);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('users')->willReturnCallback(fn($id = null) => $id === null ? $consulta : $fila);

        $service = $this->crearServicioConDB($db);
        $service->EditUser(15, 'ana', 'Ana', 'Lopez', null);
    }

    // 10. Editar un usuario inexistente
    // Prueba de EditUser para lanzar una excepcion si el usuario no existe
    public function test_RechazaUsuarioInexistente(): void
    {
        $consulta = $this->crearConsulta(null);

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('users')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User does not exist');

        $service->EditUser(999, 'ana', 'Ana', 'Lopez', null);
    }

    // 11. Actualizar una preferencia existente
    // Prueba de SetUserSetting para actualizar una preferencia existente
    public function test_ActualizaPreferenciaExistente(): void
    {
        $fila = $this->createMock(UsersRowStub::class);
        $fila->expects($this->once())->method('update')
            ->with($this->callback(function ($datos) {
                return $datos['value'] === 'azul' && isset($datos['row_updated_timestamp']);
            }));

        $consulta = $this->createMock(UsersQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($fila);
        $consulta->expects($this->never())->method('createRow');

        $db = $this->createStub(UsersDatabaseStub::class);
        $db->method('user_settings')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $service->SetUserSetting(15, 'qa_tema', 'azul');
    }
}