<?php
// Pruebas unitarias para el servicio de localización (LocalizationService).
// Paulo herrera
namespace Grocy\Tests\Unit\Services;

use Grocy\Services\LocalizationService;
use Gettext\Translations;
use PHPUnit\Framework\TestCase;

// Simula el catalogo de traducciones
interface LocalizationCatalogStub
{
    public function getHeader($nombre);
    public function getPluralForms();
    public function toJsonString();
}

final class LocalizationServiceTest extends TestCase
{
    
    private function crearServicioConCatalogo(object $catalogo): LocalizationService
    {
        $service = (new \ReflectionClass(LocalizationService::class))->newInstanceWithoutConstructor();

        $propiedad = new \ReflectionProperty(LocalizationService::class, 'Po');
        $propiedad->setValue($service, $catalogo);

        return $service;
    }

    // 1. GetPluralCount
    // Prueba de GetPluralCount para obtener la cantidad de formas plurales
    public function test_ObtieneCantidadDeFormasPlurales(): void
    {
    
        $catalogo = $this->createMock(LocalizationCatalogStub::class);

        $catalogo->expects($this->once())->method('getHeader')->with(Translations::HEADER_PLURAL)->willReturn('nplurals=3; plural=(n == 1 ? 0 : n == 2 ? 1 : 2);');

        $catalogo->method('getPluralForms')->willReturn([3, '(n == 1 ? 0 : n == 2 ? 1 : 2)']);

        $service = $this->crearServicioConCatalogo($catalogo);

  
        $resultado = $service->GetPluralCount();

       
        $this->assertSame(3, $resultado);
    }

    // 2. GetPluralDefinition
    // Prueba de GetPluralDefinition para obtener la definición plural predeterminada
    public function test_ObtieneDefinicionPluralPredeterminada(): void
    {
        // Arrange: el catálogo no tiene configuración de plurales
        $catalogo = $this->createStub(LocalizationCatalogStub::class);
        $catalogo->method('getHeader')->willReturn(null);

        $service = $this->crearServicioConCatalogo($catalogo);

        // Act
        $resultado = $service->GetPluralDefinition();

        // Assert
        $this->assertSame('(n != 1)', $resultado);
    }

    // 3. GetPoAsJsonString
    // Prueba de GetPoAsJsonString para obtener las traducciones como JSON
    public function test_ObtieneTraduccionesComoJson(): void
    {
        $json = '{"Hello":"Hola","Goodbye":"Adiós"}';

        $catalogo = $this->createStub(LocalizationCatalogStub::class);
        $catalogo->method('toJsonString')->willReturn($json);

        $service = $this->crearServicioConCatalogo($catalogo);

        $resultado = $service->GetPoAsJsonString();

        $this->assertSame($json, $resultado);
    }
}