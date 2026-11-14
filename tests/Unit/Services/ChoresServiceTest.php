<?php

// Tzu Rue Hsu
// 9 pruebas unitarias de ChoresService.php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\ChoresService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

interface ChoresQueryStub
{
    public function where($condicion, ...$valores);
    public function fetch();
    public function createRow($datos);
}

interface ChoresDatabaseStub
{
    public function chores($id = null);
    public function users();
    public function chores_current();
    public function chores_log();
    public function lastInsertId();
}

abstract class ChoreLogRowStub
{
    public int $chore_id;

    abstract public function save();
    abstract public function update($datos);
}

final class ChoresServiceTest extends TestCase
{
    // 1. TrackChore
    // Guarda la ejecución normal, de solo fecha u omitida y devuelve su ID.
    #[DataProvider('registrosDeEjecucion')]
    public function test_GuardaEjecucionYDevuelveSuId(int $soloFecha, bool $omitida, string $fechaGuardada, int $omitidaGuardada): void
    {
        $tarea = (object) [
            'id' => 5,
            'track_date_only' => $soloFecha,
            'period_type' => ChoresService::CHORE_PERIOD_TYPE_DAILY,
            'consume_product_on_execution' => 0
        ];
        $fechaProgramada = '2026-11-12 08:00:00';
        $fila = $this->createMock(ChoreLogRowStub::class);
        $fila->expects($this->once())->method('save');

        $historial = $this->createMock(ChoresQueryStub::class);
        $historial->expects($this->once())->method('createRow')->with([
            'chore_id' => 5,
            'tracked_time' => $fechaGuardada,
            'done_by_user_id' => 7,
            'skipped' => $omitidaGuardada,
            'scheduled_execution_time' => $fechaProgramada
        ])->willReturn($fila);

        $db = $this->crearDBParaRegistrar($tarea, (object) ['id' => 7]);
        $db->method('chores_current')->willReturn($this->crearConsulta((object) ['next_estimated_execution_time' => $fechaProgramada]));
        $db->method('chores_log')->willReturn($historial);
        $db->method('lastInsertId')->willReturn('42');

        $servicio = $this->crearServicioConReasignacionSimulada($db, 5);

        $resultado = $servicio->TrackChore(5, '2026-11-12 15:30:00', 7, $omitida);

        $this->assertSame('42', $resultado);
    }

    public static function registrosDeEjecucion(): array
    {
        return [
            'ejecución normal' => [0, false, '2026-11-12 15:30:00', 0],
            'solo fecha' => [1, false, '2026-11-12 00:00:00', 0],
            'ejecución omitida' => [0, true, '2026-11-12 15:30:00', 1]
        ];
    }

    // 2. TrackChore
    // Rechaza registrar una tarea que no existe.
    public function test_RechazaRegistrarTareaInexistente(): void
    {
        $db = $this->createMock(ChoresDatabaseStub::class);
        $db->method('chores')->willReturn($this->crearConsulta(null));
        $db->expects($this->never())->method('chores_log');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Chore does not exist');

        $this->crearServicioConDB($db)->TrackChore(999, '2026-11-12 15:30:00', 7);
    }

    // 3. TrackChore
    // Rechaza registrar una tarea para un usuario inexistente.
    public function test_RechazaRegistrarTareaParaUsuarioInexistente(): void
    {
        $db = $this->crearDBParaRegistrar((object) ['id' => 5], null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User does not exist');

        $this->crearServicioConDB($db)->TrackChore(5, '2026-11-12 15:30:00', 999);
    }

    // 4. TrackChore
    // Rechaza omitir una tarea que se ejecuta manualmente.
    public function test_RechazaOmitirTareaSinHorario(): void
    {
        $tarea = (object) [
            'id' => 5,
            'track_date_only' => 0,
            'period_type' => ChoresService::CHORE_PERIOD_TYPE_MANUALLY
        ];
        $db = $this->crearDBParaRegistrar($tarea, (object) ['id' => 7]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Chores without a schedule can't be skipped");

        $this->crearServicioConDB($db)->TrackChore(5, '2026-11-12 15:30:00', 7, true);
    }

    // 5. UndoChoreExecution
    // Marca la ejecución como deshecha y pide recalcular la asignación.
    public function test_DeshaceEjecucionYRecalculaAsignacion(): void
    {
        $fila = $this->createMock(ChoreLogRowStub::class);
        $fila->chore_id = 5;
        $fila->expects($this->once())->method('update')->with($this->callback(
            static fn (array $datos): bool => ($datos['undone'] ?? null) === 1
                && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $datos['undone_timestamp'] ?? '') === 1
        ));
        $db = $this->crearDBParaDeshacer($fila);

        $this->crearServicioConReasignacionSimulada($db, 5)->UndoChoreExecution(42);
    }

    // 6. UndoChoreExecution
    // Rechaza deshacer una ejecución inexistente o ya deshecha.
    public function test_RechazaDeshacerEjecucionNoDisponible(): void
    {
        $db = $this->crearDBParaDeshacer(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Execution does not exist or was already undone');

        $this->crearServicioConDB($db)->UndoChoreExecution(42);
    }

    // 7. MergeChores
    // Rechaza fusionar una tarea consigo misma.
    public function test_RechazaFusionarUnaTareaConsigoMisma(): void
    {
        $db = $this->createStub(ChoresDatabaseStub::class);
        $db->method('chores')->willReturn($this->crearConsulta((object) ['id' => 5]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('$choreIdToKeep cannot equal $choreIdToRemove');

        $this->crearServicioConDB($db)->MergeChores(5, 5);
    }

    private function crearConsulta(?object $fila): ChoresQueryStub
    {
        $consulta = $this->createStub(ChoresQueryStub::class);
        $consulta->method('where')->willReturnSelf();
        $consulta->method('fetch')->willReturn($fila);

        return $consulta;
    }

    private function crearDBParaRegistrar(object $tarea, ?object $usuario): ChoresDatabaseStub
    {
        $db = $this->createStub(ChoresDatabaseStub::class);
        $db->method('chores')->willReturnMap([
            [null, $this->crearConsulta($tarea)],
            [$tarea->id, $tarea]
        ]);
        $db->method('users')->willReturn($this->crearConsulta($usuario));

        return $db;
    }

    private function crearDBParaDeshacer(?ChoreLogRowStub $fila): ChoresDatabaseStub
    {
        $consulta = $this->createMock(ChoresQueryStub::class);
        $consulta->expects($this->once())->method('where')->with('id = :1 AND undone = 0', 42)->willReturnSelf();
        $consulta->method('fetch')->willReturn($fila);

        $db = $this->createStub(ChoresDatabaseStub::class);
        $db->method('chores_log')->willReturn($consulta);

        return $db;
    }

    private function crearServicioConReasignacionSimulada(ChoresDatabaseStub $db, int $tareaId): ChoresService
    {
        // Aísla el registro de la lógica de asignación.
        $servicio = $this->getMockBuilder(ChoresService::class)->disableOriginalConstructor()->onlyMethods(['CalculateNextExecutionAssignment'])->getMock();
        $servicio->expects($this->once())->method('CalculateNextExecutionAssignment')->with($tareaId);

        return $this->crearServicioConDB($db, $servicio);
    }

    private function crearServicioConDB(ChoresDatabaseStub $db, ?ChoresService $servicio = null): ChoresService
    {
        $servicio ??= (new \ReflectionClass(ChoresService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BaseService::class, 'DB'))->setValue($servicio, $db);

        return $servicio;
    }
}
