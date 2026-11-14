<?php

// Tzu Rue Hsu
// 8 pruebas unitarias de BatteriesService

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\BatteriesService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

interface BatteriesQueryStub
{
    public function where($condicion, ...$valores);
    public function fetch();
    public function orderBy($campo, $orden);
    public function count();
    public function max($campo);
    public function min($campo);
    public function createRow($datos);
}

interface BatteriesDatabaseStub
{
    public function batteries($id = null);
    public function batteries_current();
    public function battery_charge_cycles();
    public function lastInsertId();
}

interface BatteryChargeCycleRowStub
{
    public function save();
    public function update($datos);
}

final class BatteriesServiceTest extends TestCase
{
    // 1. GetBatteryDetails
    // Rechaza la consulta cuando la bateria no existe
    public function test_RechazaConsultarBateriaInexistente(): void
    {
        $db = $this->createMock(BatteriesDatabaseStub::class);
        $db->method('batteries')->willReturn($this->crearBusquedaDeBateria(999, null));
        $db->expects($this->never())->method('battery_charge_cycles');
        $db->expects($this->never())->method('batteries_current');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Battery does not exist');

        $this->crearServicioConDB($db)->GetBatteryDetails(999);
    }

    // 2. GetCurrent
    // Vincula cada estado con su batería por ID
    public function test_AsociaCadaEstadoConSuBateria(): void
    {
        $control = (object) ['id' => 5, 'name' => 'Control'];
        $linterna = (object) ['id' => 8, 'name' => 'Linterna'];
        $estados = [(object) ['battery_id' => 8], (object) ['battery_id' => 5]];
        $servicio = $this->crearServicioConEstados([$control, $linterna], $estados);

        $resultado = $servicio->GetCurrent();

        $this->assertSame($estados, $resultado);
        $this->assertSame($linterna, $resultado[0]->battery);
        $this->assertSame($control, $resultado[1]->battery);
    }

    // 3. GetCurrent
    // Devuelve null en el campo battery si no encuentra una bateria activa
    public function test_DevuelveEstadoSinBateriaActivaAsociada(): void
    {
        $bateriaActiva = (object) ['id' => 5, 'name' => 'Control'];
        $estados = [(object) ['battery_id' => 8]];
        $servicio = $this->crearServicioConEstados([$bateriaActiva], $estados);

        $resultado = $servicio->GetCurrent();

        $this->assertSame($estados, $resultado);
        $this->assertNull($resultado[0]->battery);
    }

    // 4. GetCurrent
    // Devuelve una lista vacia cuando no hay estados
    public function test_DevuelveListaVaciaSiNoHayEstados(): void
    {
        $servicio = $this->crearServicioConEstados([], []);

        $this->assertSame([], $servicio->GetCurrent());
    }

    // 5. TrackChargeCycle
    // Guarda la carga con la fecha indicada y devuelve su ID
    public function test_RegistraCargaYDevuelveSuId(): void
    {
        $fecha = '2026-11-12 10:00:00';
        $fila = $this->createMock(BatteryChargeCycleRowStub::class);
        $fila->expects($this->once())->method('save');

        $cargas = $this->createMock(BatteriesQueryStub::class);
        $cargas->expects($this->once())->method('createRow')->with(['battery_id' => 5, 'tracked_time' => $fecha])->willReturn($fila);

        $db = $this->createStub(BatteriesDatabaseStub::class);
        $db->method('batteries')->willReturn($this->crearBusquedaDeBateria(5, (object) ['id' => 5]));
        $db->method('battery_charge_cycles')->willReturn($cargas);
        $db->method('lastInsertId')->willReturn('42');

        $resultado = $this->crearServicioConDB($db)->TrackChargeCycle(5, $fecha);

        $this->assertSame('42', $resultado);
    }

    // 6. TrackChargeCycle
    // Rechaza el registro si la batería no existe
    public function test_RechazaRegistrarCargaDeBateriaInexistente(): void
    {
        $db = $this->createMock(BatteriesDatabaseStub::class);
        $db->method('batteries')->willReturn($this->crearBusquedaDeBateria(999, null));
        $db->expects($this->never())->method('battery_charge_cycles');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Battery does not exist');

        $this->crearServicioConDB($db)->TrackChargeCycle(999, '2026-11-12 10:00:00');
    }

    // 7. UndoChargeCycle
    // Marca la carga como deshecha y registra la fecha del cambio
    public function test_MarcaCargaComoDeshechaYRegistraFecha(): void
    {
        $datosActualizados = null;
        $fila = $this->createMock(BatteryChargeCycleRowStub::class);
        $fila->expects($this->once())->method('update')->willReturnCallback(
            function (array $datos) use (&$datosActualizados): void {
                $datosActualizados = $datos;
            }
        );
        $servicio = $this->crearServicioParaDeshacerCarga(42, $fila);

        // Comprueba la fecha sin fijar la hora de ejecucion
        $antes = date('Y-m-d H:i:s');
        $servicio->UndoChargeCycle(42);
        $despues = date('Y-m-d H:i:s');

        $this->assertSame(1, $datosActualizados['undone'] ?? null);
        $fecha = $datosActualizados['undone_timestamp'] ?? null;
        $this->assertIsString($fecha);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $fecha);
        $this->assertGreaterThanOrEqual($antes, $fecha);
        $this->assertLessThanOrEqual($despues, $fecha);
    }

    // 8. UndoChargeCycle
    // Rechaza deshacer una carga inexistente o ya deshecha
    public function test_RechazaDeshacerUnaCargaNoDisponible(): void
    {
        $servicio = $this->crearServicioParaDeshacerCarga(42, null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Charge cycle does not exist or was already undone');

        $servicio->UndoChargeCycle(42);
    }

    private function crearBusquedaDeBateria(int $id, ?object $bateria): BatteriesQueryStub
    {
        $consulta = $this->createMock(BatteriesQueryStub::class);
        $consulta->expects($this->once())->method('where')->with('id = :1', $id)->willReturnSelf();
        $consulta->method('fetch')->willReturn($bateria);

        return $consulta;
    }

    private function crearServicioConEstados(array $baterias, array $estados): BatteriesService
    {
        $consulta = $this->createMock(BatteriesQueryStub::class);
        $consulta->expects($this->once())->method('where')->with('active = 1')->willReturnSelf();
        $consulta->expects($this->once())->method('orderBy')->with('name', 'COLLATE NOCASE')->willReturn($baterias);

        $db = $this->createStub(BatteriesDatabaseStub::class);
        $db->method('batteries')->willReturn($consulta);
        $db->method('batteries_current')->willReturn($estados);

        return $this->crearServicioConDB($db);
    }

    private function crearServicioParaDeshacerCarga(int $id, ?BatteryChargeCycleRowStub $fila): BatteriesService
    {
        $cargas = $this->createMock(BatteriesQueryStub::class);
        $cargas->expects($this->once())->method('where')->with('id = :1 AND undone = 0', $id)->willReturnSelf();
        $cargas->method('fetch')->willReturn($fila);

        $db = $this->createStub(BatteriesDatabaseStub::class);
        $db->method('battery_charge_cycles')->willReturn($cargas);

        return $this->crearServicioConDB($db);
    }

    private function crearServicioConDB(BatteriesDatabaseStub $db): BatteriesService
    {
        // Asigna la base simulada sin abrir una conexión.
        $servicio = (new \ReflectionClass(BatteriesService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BaseService::class, 'DB'))->setValue($servicio, $db);

        return $servicio;
    }
}
