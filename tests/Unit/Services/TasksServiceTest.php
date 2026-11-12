<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\TasksService;
use PHPUnit\Framework\TestCase;

// Para simular consultas de tareas.
interface TasksQueryStub
{
    public function where($campo, $valor);
    public function fetch();
}

// Para simular el acceso a la BD.
interface TasksDatabaseStub
{
    public function tasks();
}

// Para simular un registro de tarea.
interface TaskRowStub
{
    public function update($datos);
}

class TasksServiceTest extends TestCase
{
    // Crea el servicio con la BD simulada.
    private function crearServicioConDB(object $db): TasksService
    {
        $service = (new \ReflectionClass(TasksService::class))
            ->newInstanceWithoutConstructor();

        $propiedad = new \ReflectionProperty(
            TasksService::class,
            'DB'
        );

        $propiedad->setValue($service, $db);

        return $service;
    }

    // Prueba que MarkTaskAsCompleted devuelve true al completar una tarea existente.
    public function test_CompletaTareaExistente(): void
    {
        // Arrange: simula una tarea existente.
        $fila = $this->createStub(TaskRowStub::class);

        $consulta = $this->createStub(TasksQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($fila);

        $db = $this->createStub(TasksDatabaseStub::class);
        $db->method('tasks')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $resultado = $service->MarkTaskAsCompleted(
            5,
            '2026-11-11 10:00:00'
        );

        // Assert
        $this->assertTrue($resultado);
    }

    // Prueba que MarkTaskAsCompleted actualiza correctamente el estado de una tarea existente.
    public function test_ActualizaEstadoDeTareaCompletada(): void
    {
        // Arrange: simula el registro de una tarea.
        $datosActualizados = null;

        $fila = $this->createStub(TaskRowStub::class);

        $fila->method('update')->willReturnCallback(
                function ($datos) use (&$datosActualizados) {
                    $datosActualizados = $datos;
                }
            );

        $consulta = $this->createStub(TasksQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($fila);

        $db = $this->createStub(TasksDatabaseStub::class);
        $db->method('tasks')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $service->MarkTaskAsCompleted(
            5,
            '2026-11-11 10:00:00'
        );

        // Assert
        $this->assertSame(
            [
                'done' => 1,
                'done_timestamp' => '2026-11-11 10:00:00'
            ],
            $datosActualizados
        );
    }

    // Prueba que MarkTaskAsCompleted lanza una excepción si la tarea no existe.
    public function test_RechazaCompletarTareaInexistente(): void
    {
        // Arrange: simula una tarea que no existe.
        $consulta = $this->createStub(TasksQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(TasksDatabaseStub::class);
        $db->method('tasks')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Expect: que lance una excepción.
        $this->expectException(\Exception::class);

        // Act
        $service->MarkTaskAsCompleted(
            999,
            '2026-11-11 10:00:00'
        );
    }

    // Prueba que UndoTask restablece el estado y elimina la fecha de finalización.
    public function test_DeshaceTareaCompletada(): void
    {
        // Arrange: simula el registro de una tarea.
        $datosActualizados = null;

        $fila = $this->createStub(TaskRowStub::class);

        $fila->method('update')
            ->willReturnCallback(
                function ($datos) use (&$datosActualizados) {
                    $datosActualizados = $datos;
                }
            );

        $consulta = $this->createStub(TasksQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn($fila);

        $db = $this->createStub(TasksDatabaseStub::class);
        $db->method('tasks')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Act
        $service->UndoTask(5);

        // Assert
        $this->assertSame(
            [
                'done' => 0,
                'done_timestamp' => null
            ],
            $datosActualizados
        );
    }

    // Prueba que UndoTask lanza una excepción si la tarea no existe.
    public function test_RechazaDeshacerTareaInexistente(): void
    {
        // Arrange: simula una tarea inexistente.
        $consulta = $this->createStub(TasksQueryStub::class);
        $consulta->method('where')->willReturn($consulta);
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(TasksDatabaseStub::class);
        $db->method('tasks')->willReturn($consulta);

        $service = $this->crearServicioConDB($db);

        // Expect: la tarea inexistente genera una excepción.
        $this->expectException(\Exception::class);

        // Act
        $service->UndoTask(999);
    }
}