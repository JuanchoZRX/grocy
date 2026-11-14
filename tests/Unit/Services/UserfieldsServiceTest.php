<?php

// Paulo Herrera
// 11 pruebas unitarias para UserfieldsService.php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\UserfieldsService;
use PHPUnit\Framework\TestCase;

// Simula las consultas
interface UserfieldsQueryStub
{
    public function where($campo, ...$valores);
    public function orderBy($campo, ...$opciones);
    public function fetch();
    public function fetchAll();
    public function createRow($datos);
}

// Simula la base de datos
interface UserfieldsDatabaseStub
{
    public function userfields($id = null);
    public function userentities();
    public function userfield_values();
    public function userfield_values_resolved();
}

// Simula una fila
interface UserfieldsRowStub
{
    public function save();
    public function update($datos);
}

final class UserfieldsServiceTest extends TestCase
{
    private function crearServicioConDB(object $db): UserfieldsService
    {
        $service = (new \ReflectionClass(UserfieldsService::class))
            ->newInstanceWithoutConstructor();

        $propiedad = new \ReflectionProperty(BaseService::class, 'DB');
        $propiedad->setValue($service, $db);

        // Evita leer el archivo OpenAPI real
        $spec = json_decode('{
            "components": {
                "schemas": {
                    "ExposedEntity": {
                        "enum": ["products", "chores"]
                    }
                }
            }
        }');

        $propiedad = new \ReflectionProperty(
            UserfieldsService::class,
            'OpenApiSpec'
        );
        $propiedad->setValue($service, $spec);

        return $service;
    }

    private function crearDB(array $entidades = []): object
    {
        $consulta = $this->createStub(UserfieldsQueryStub::class);
        $consulta->method('orderBy')->willReturn($entidades);

        $db = $this->createStub(UserfieldsDatabaseStub::class);
        $db->method('userentities')->willReturn($consulta);

        return $db;
    }

    private function crearConsulta(array $filas = []): object
    {
        $consulta = $this->createStub(UserfieldsQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('orderBy')->willReturn($consulta);
        $consulta->method('fetchAll')->willReturn($filas);

        return $consulta;
    }

    // 1. GetAllFields
    // Prueba de GetAllFields para obtener todos los campos.
    public function test_ObtieneTodosLosCampos(): void
    {
        
        $campos = [
            (object) ['id' => 1, 'name' => 'Color'],
            (object) ['id' => 2, 'name' => 'Marca']
        ];

        $consulta = $this->createMock(UserfieldsQueryStub::class);
        $consulta->expects($this->once())->method('orderBy')
            ->with('name', 'COLLATE NOCASE')->willReturnSelf();
        $consulta->method('fetchAll')->willReturn($campos);

        
        $db = $this->crearDB();
        $db->method('userfields')->willReturn($consulta);
     
        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetAllFields();

        
        $this->assertSame($campos, $resultado);
    }

    // 2. GetAllValues
    // Prueba de GetAllValues para obtener todos los valores de una entidad.
    public function test_ObtieneTodosLosValoresDeUnaEntidad(): void
    {
        $valores = [
            (object) ['name' => 'Color', 'value' => 'Rojo']
        ];

        $consulta = $this->createMock(UserfieldsQueryStub::class);
        $consulta->expects($this->once())->method('where')
            ->with('entity', 'products')->willReturnSelf();
        $consulta->method('orderBy')->willReturnSelf();
        $consulta->method('fetchAll')->willReturn($valores);

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($this->crearConsulta());
        $db->method('userfield_values_resolved')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetAllValues('products');

        $this->assertSame($valores, $resultado);
    }

    // 3. GetEntities
    // Prueba de GetEntities para obtener las entidades expuestas.
    public function test_ObtieneEntidadesOrdenadas(): void
    {
        $db = $this->crearDB([
            (object) ['name' => 'vehiculos']
        ]);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetEntities();

        $this->assertSame([
            'chores',
            'products',
            'userentity-vehiculos',
            'users'
        ], $resultado);
    }

    // 4. GetField
    // Prueba de GetField para obtener un campo por su ID.
    public function test_ObtieneCampoPorId(): void
    {
        $campo = (object) ['id' => 7, 'name' => 'Color'];
        
        $db = $this->createMock(UserfieldsDatabaseStub::class);
        $db->expects($this->once())->method('userfields')
            ->with(7)->willReturn($campo);

        
        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetField(7);

        $this->assertSame($campo, $resultado);
    }

    // 5. GetFieldTypes
    // Prueba de GetFieldTypes para obtener los tipos de campos.
    public function test_ObtieneTiposDeCampos(): void
    {
        $service = $this->crearServicioConDB($this->crearDB());

        $resultado = $service->GetFieldTypes();

        $this->assertCount(14, $resultado);
        $this->assertSame('checkbox', $resultado['USERFIELD_TYPE_CHECKBOX']);
        $this->assertSame('date', $resultado['USERFIELD_TYPE_DATE']);
        $this->assertSame(
            'text-single-line',
            $resultado['USERFIELD_TYPE_SINGLE_LINE_TEXT']
        );
    }

    // 6. GetFields
    // Prueba de GetFields para obtener los campos de una entidad.
    public function test_ObtieneCamposDeUnaEntidad(): void
    {
        $campos = [(object) ['id' => 7, 'name' => 'Color']];

        $consulta = $this->createMock(UserfieldsQueryStub::class);
        $consulta->expects($this->once())->method('where')
            ->with('entity', 'products')->willReturnSelf();
        $consulta->method('orderBy')->willReturnSelf();
        $consulta->method('fetchAll')->willReturn($campos);

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetFields('products');

        $this->assertSame($campos, $resultado);
    }

    // 7. GetValues
    // Prueba de GetValues para obtener los valores de un objeto.
    public function test_ObtieneValoresDeUnObjeto(): void
    {
        $campos = [
            (object) ['name' => 'Color'],
            (object) ['name' => 'Marca']
        ];

        $valores = [
            (object) ['name' => 'Color', 'value' => 'Rojo']
        ];

        $consulta = $this->createMock(UserfieldsQueryStub::class);
        $consulta->expects($this->once())->method('where')
            ->with('entity = :1 AND object_id = :2', 'products', 10)
            ->willReturnSelf();
        $consulta->method('orderBy')->willReturnSelf();
        $consulta->method('fetchAll')->willReturn($valores);

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($this->crearConsulta($campos));
        $db->method('userfield_values_resolved')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);
        $resultado = $service->GetValues('products', 10);

        $this->assertSame([
            'Color' => 'Rojo',
            'Marca' => null
        ], $resultado);
    }

    // 8. SetValues
    // Prueba de SetValues para guardar un valor nuevo.
    public function test_GuardaValorNuevo(): void
    {
        $campo = $this->crearConsulta();
        $campo->method('fetch')->willReturn((object) ['id' => 7]);

        $fila = $this->createMock(UserfieldsRowStub::class);
        $fila->expects($this->once())->method('save');

        $valores = $this->createMock(UserfieldsQueryStub::class);
        $valores->expects($this->once())->method('where')
            ->with('field_id = :1 AND object_id = :2', 7, 10)
            ->willReturnSelf();
        $valores->method('fetch')->willReturn(null);
        $valores->expects($this->once())->method('createRow')
            ->with([
                'field_id' => 7,
                'object_id' => 10,
                'value' => 'Rojo'
            ])
            ->willReturn($fila);

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($campo);
        $db->method('userfield_values')->willReturn($valores);

        $service = $this->crearServicioConDB($db);
        $service->SetValues('products', 10, ['Color' => 'Rojo']);
    }

    // 9. Consultar una entidad inválida
    // Prueba de GetFields para una entidad inexistente.
    public function test_RechazaEntidadInexistente(): void
    {
        $service = $this->crearServicioConDB($this->crearDB());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Entity does not exist or is not exposed');

        $service->GetFields('entidad_inexistente');
    }

    // 10. Actualizar un valor que ya existe.
    // Prueba de SetValues para actualizar un valor existente.
    public function test_ActualizaValorExistente(): void
    {
        $campo = $this->crearConsulta();
        $campo->method('fetch')->willReturn((object) ['id' => 7]);

        $fila = $this->createMock(UserfieldsRowStub::class);
        $fila->expects($this->once())->method('update')
            ->with(['value' => 'Azul']);

        $valores = $this->createMock(UserfieldsQueryStub::class);
        $valores->expects($this->once())->method('where')
            ->with('field_id = :1 AND object_id = :2', 7, 10)
            ->willReturnSelf();
        $valores->method('fetch')->willReturn($fila);
        $valores->expects($this->never())->method('createRow');

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($campo);
        $db->method('userfield_values')->willReturn($valores);

        $service = $this->crearServicioConDB($db);
        $service->SetValues('products', 10, ['Color' => 'Azul']);
    }

    // 11. Guardar un campo que no existe.
    // Prueba de SetValues para un campo inexistente.
    public function test_RechazaCampoInexistente(): void
    {
        $consulta = $this->crearConsulta();
        $consulta->method('fetch')->willReturn(null);

        $db = $this->crearDB();
        $db->method('userfields')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Field Inexistente is not a valid userfield of the given entity'
        );

        $service->SetValues('products', 10, ['Inexistente' => 'valor']);
    }
}