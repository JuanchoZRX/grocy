<?php

// Tzu Rue Hsu
// 8 pruebas unitarias de RecipesService.php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\RecipesService;
use LessQL\Result;
use PHPUnit\Framework\TestCase;

interface RecipesDatabaseStub
{
    public function recipes($id = null);
    public function recipes_resolved();
    public function products($id);
    public function shopping_list();
}

abstract class RecipeShoppingListRowStub
{
    public float $amount;

    abstract public function save();
    abstract public function update($datos);
}

final class RecipesServiceTest extends TestCase
{
    // 1. CopyRecipe
    // Rechaza copiar una receta que no existe.
    public function test_RechazaCopiarRecetaInexistente(): void
    {
        $servicio = $this->crearServicioSinReceta(999);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Recipe does not exist');

        $servicio->CopyRecipe(999);
    }

    // 2. ConsumeRecipe
    // Rechaza consumir una receta que no existe.
    public function test_RechazaConsumirRecetaInexistente(): void
    {
        $servicio = $this->crearServicioSinReceta(999);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Recipe does not exist');

        $servicio->ConsumeRecipe(999);
    }

    // 3. GetRecipesResolved
    // Devuelve la consulta original cuando no se pasa un filtro.
    public function test_DevuelveConsultaSinFiltro(): void
    {
        $consulta = $this->createMock(Result::class);
        $consulta->expects($this->never())->method('where');

        $db = $this->createStub(RecipesDatabaseStub::class);
        $db->method('recipes_resolved')->willReturn($consulta);

        $this->assertSame($consulta, $this->crearServicio($db)->GetRecipesResolved());
    }

    // 4. GetRecipesResolved
    // Aplica el filtro recibido y devuelve la consulta filtrada.
    public function test_AplicaFiltroDeRecetas(): void
    {
        $filtro = ['recipe_id' => 5];
        $consultaFiltrada = $this->createStub(Result::class);
        $consulta = $this->createMock(Result::class);
        $consulta->expects($this->once())->method('where')
            ->with($filtro)->willReturn($consultaFiltrada);

        $db = $this->createStub(RecipesDatabaseStub::class);
        $db->method('recipes_resolved')->willReturn($consulta);

        $this->assertSame($consultaFiltrada, $this->crearServicio($db)->GetRecipesResolved($filtro));
    }

    // 5. AddNotFulfilledProductsToShoppingList
    // Agrega las cinco unidades faltantes cuando el ingrediente aún no está en la lista.
    public function test_AgregaIngredienteFaltante(): void
    {
        $fila = $this->createMock(RecipeShoppingListRowStub::class);
        $fila->expects($this->once())->method('save');

        $lista = $this->createMock(Result::class);
        $lista->expects($this->once())->method('where')->with('product_id', 7)->willReturnSelf();
        $lista->method('fetch')->willReturn(null);
        $lista->expects($this->once())->method('createRow')->with(['product_id' => 7, 'amount' => 5.0, 'qu_id' => 2])->willReturn($fila);

        $db = $this->crearDBParaCompras();
        $db->method('shopping_list')->willReturn($lista);

        $this->crearServicio($db, $this->ingredientes(0))->AddNotFulfilledProductsToShoppingList(5);
    }

    // 6. AddNotFulfilledProductsToShoppingList
    // Si ya hay dos unidades en la lista, actualiza la misma fila a cinco.
    public function test_ActualizaIngredienteEnLaLista(): void
    {
        $fila = $this->createMock(RecipeShoppingListRowStub::class);
        $fila->amount = 2.0;
        $fila->expects($this->once())->method('update')->with(['amount' => 5.0]);

        $lista = $this->createMock(Result::class);
        $lista->expects($this->once())->method('where')->with('product_id', 7)->willReturnSelf();
        $lista->method('fetch')->willReturn($fila);
        $lista->expects($this->never())->method('createRow');

        $db = $this->crearDBParaCompras();
        $db->method('shopping_list')->willReturn($lista);

        $this->crearServicio($db, $this->ingredientes(2))->AddNotFulfilledProductsToShoppingList(5);
    }

    // 7. AddNotFulfilledProductsToShoppingList
    // Omite el ingrediente cuando su ID está en la lista de exclusión.
    public function test_OmiteIngredienteExcluido(): void
    {
        $db = $this->createMock(RecipesDatabaseStub::class);
        $db->method('recipes')->willReturn((object) ['not_check_shoppinglist' => 0]);
        $db->expects($this->never())->method('products');
        $db->expects($this->never())->method('shopping_list');

        $this->crearServicio($db, $this->ingredientes(0))->AddNotFulfilledProductsToShoppingList(5, [7]);
    }

    // 8. AddNotFulfilledProductsToShoppingList
    // Omite el ingrediente cuando las cinco unidades ya están en la lista.
    public function test_OmiteIngredienteConFaltanteCubierto(): void
    {
        $db = $this->createMock(RecipesDatabaseStub::class);
        $db->method('recipes')->willReturn((object) ['not_check_shoppinglist' => 0]);
        $db->method('products')->willReturn((object) ['qu_id_purchase' => 2]);
        $db->expects($this->never())->method('shopping_list');

        $this->crearServicio($db, $this->ingredientes(5))->AddNotFulfilledProductsToShoppingList(5);
    }

    private function crearServicioSinReceta(int $id): RecipesService
    {
        $consulta = $this->createMock(Result::class);
        $consulta->expects($this->once())->method('where')->with('id = :1', $id)->willReturnSelf();
        $consulta->method('fetch')->willReturn(null);

        $db = $this->createStub(RecipesDatabaseStub::class);
        $db->method('recipes')->willReturn($consulta);

        return $this->crearServicio($db);
    }

    private function crearDBParaCompras(): RecipesDatabaseStub
    {
        $db = $this->createStub(RecipesDatabaseStub::class);
        $db->method('recipes')->willReturn((object) ['not_check_shoppinglist' => 0]);
        $db->method('products')->willReturn((object) ['qu_id_purchase' => 2]);

        return $db;
    }

    private function ingredientes(int $cantidadEnLista): array
    {
        return [(object) [
            'recipe_id' => 5,
            'product_id' => 7,
            'missing_amount' => 5,
            'amount_on_shopping_list' => $cantidadEnLista,
            'only_check_single_unit_in_stock' => 0
        ]];
    }

    private function crearServicio(RecipesDatabaseStub $db, ?array $ingredientes = null): RecipesService
    {
        if ($ingredientes === null) {
            $servicio = (new \ReflectionClass(RecipesService::class))->newInstanceWithoutConstructor();
        } else {
            // Usa los ingredientes de prueba sin consultar SQLite.
            $servicio = $this->getMockBuilder(RecipesService::class)->disableOriginalConstructor()->onlyMethods(['GetRecipesPosResolved'])->getMock();
            $servicio->expects($this->once())->method('GetRecipesPosResolved')->willReturn($ingredientes);
        }
        (new \ReflectionProperty(BaseService::class, 'DB'))->setValue($servicio, $db);

        return $servicio;
    }
}
