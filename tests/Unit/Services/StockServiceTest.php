<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\BaseService;
use Grocy\Services\StockService;
use PHPUnit\Framework\TestCase;

interface StockQueryStub
{
    public function where($condicion, ...$valores);
    public function fetch();
    public function createRow($datos);
    public function orderBy($campo, $orden);
    public function delete();
}

interface StockDatabaseStub
{
    public function products($id = null);
    public function product_barcodes();
    public function products_price_history();
    public function shopping_locations();
    public function shopping_lists();
    public function shopping_list();
    public function stock();
    public function stock_log();
    public function stock_next_use();
    public function stock_current_locations();
    public function locations();
}

interface StockRowStub
{
    public function save();
}

final class StockServiceTest extends TestCase
{
    private const codigoBarras = '7501234567890';
    private const precio = 1000.0;

    private object $producto;


    protected function setUp(): void
    {
        $this->producto = (object) [
            'id' => 5,
            'qu_id_purchase' => 1,
            'enable_tare_weight_handling' => 0
        ];
    }


    private function crearDB(array $colecciones = []): StockDatabaseStub
    {
        $db = $this->createStub(StockDatabaseStub::class);
        foreach ($colecciones as $metodo => $resultado)
        {
            // Configura cada método de la base falsa con el resultado indicado.
            $db->method($metodo)->willReturn($resultado);
        }

        return $db;
    }


    private function crearConsulta($resultado): StockQueryStub
    {
        $consulta = $this->createStub(StockQueryStub::class);
        // where() permite encadenar la consulta y fetch() devuelve el resultado simulado.
        $consulta->method('where')->willReturnSelf();
        $consulta->method('fetch')->willReturn($resultado);

        return $consulta;
    }


    private function crearServicio(StockDatabaseStub $db, array $metodos = []): StockService
    {
        if ($metodos)
        {
            // Crea un mock y sustituye solo los métodos indicados.
            $servicio = $this->getMockBuilder(StockService::class)
                ->disableOriginalConstructor()
                ->onlyMethods($metodos)
                ->getMock();
        }
        else
        {
            // Crea el servicio sin abrir una conexión real a la base de datos.
            $servicio = (new \ReflectionClass(StockService::class))->newInstanceWithoutConstructor();
        }

        // Inyecta la base falsa en la propiedad protegida del servicio.
        (new \ReflectionProperty(BaseService::class, 'DB'))->setValue($servicio, $db);

        return $servicio;
    }


    private function crearServicioConStock(string $filtro): StockService
    {
        $servicio = $this->crearServicio($this->crearDB(), ['GetCurrentStock']);

        // Exige una llamada con el filtro exacto y devuelve un producto simulado.
        $servicio->expects($this->once())->method('GetCurrentStock')
            ->with($filtro)->willReturn(['producto']);

        return $servicio;
    }


    private function comprobarQueFalla(StockDatabaseStub $db, string $mensaje, callable $accion): void
    {
        // Declara qué excepción y qué mensaje debe producir la acción.

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($mensaje);

        $accion($this->crearServicio($db));
    }


    private function crearServicioParaCompra(): StockService
    {
        $log = $this->createMock(StockRowStub::class);
        // Cada fila debe guardarse exactamente una vez.
        $log->expects($this->once())->method('save');
        $stock = $this->createMock(StockRowStub::class);
        $stock->expects($this->once())->method('save');

        $logQuery = $this->createMock(StockQueryStub::class);

        // Comprueba los datos del log y devuelve la fila falsa que se guardará.
        $logQuery->expects($this->once())->method('createRow')
            ->with($this->callback(static fn (array $datos): bool => $datos['product_id'] === 5
                && $datos['amount'] === 5.0
                && $datos['transaction_type'] === StockService::TRANSACTION_TYPE_PURCHASE))
            ->willReturn($log);

        $stockQuery = $this->createMock(StockQueryStub::class);

        // Comprueba los datos de stock y devuelve su fila falsa.
        $stockQuery->expects($this->once())->method('createRow')
            ->with($this->callback(static fn (array $datos): bool => $datos['product_id'] === 5
                && $datos['amount'] === 5.0
                && $datos['best_before_date'] === '2026-12-31'))
            ->willReturn($stock);

        $db = $this->crearDB([
            'products' => $this->crearConsulta($this->producto),
            'stock_log' => $logQuery,
            'stock' => $stockQuery
        ]);

        $servicio = $this->crearServicio($db, ['GetProductDetails', 'CompactStockEntries']);

        // Simula el detalle inicial y exige compactar las entradas tras la compra.
        $servicio->expects($this->once())->method('GetProductDetails')
            ->willReturn(['product' => $this->producto, 'stock_amount' => 0.0]);
        $servicio->expects($this->once())->method('CompactStockEntries')->with(5);

        return $servicio;
    }


    // Prepara una lista vacía que guarda una entrada con los datos esperados

    private function crearServicioConListaVacia(array $datosEsperados, array $metodos = []): StockService
    {
        $fila = $this->createMock(StockRowStub::class);

        // La fila insertada debe guardarse exactamente una vez.
        $fila->expects($this->once())->method('save');

        $compras = $this->createMock(StockQueryStub::class);
        $compras->method('where')->willReturnSelf();
        $compras->method('fetch')->willReturn(null);

        // createRow() debe recibir los datos esperados y devolver la fila simulada.
        $compras->expects($this->once())->method('createRow')
            ->with($datosEsperados)->willReturn($fila);

        $db = $this->createStub(StockDatabaseStub::class);
        $db->method('shopping_lists')->willReturn($this->crearConsulta((object) ['id' => 1]));
        $db->method('products')->willReturnMap([[null, $this->crearConsulta($this->producto)], [5, $this->producto]]);
        $db->method('shopping_list')->willReturn($compras);

        return $this->crearServicio($db, $metodos);
    }


    // 1. GetProductStockEntries: devuelve los lotes del producto.

    public function test_ListaLotesDelProducto(): void
    {
        // Arrange
        $lote = (object) ['amount' => 5.0];
        $consulta = $this->createStub(StockQueryStub::class);
        $consulta->method('where')->willReturn([$lote]);
        $db = $this->crearDB(['stock_next_use' => $consulta]);

        // Act
        $resultado = $this->crearServicio($db)->GetProductStockEntries(5);

        // Assert
        $this->assertSame([$lote], $resultado);
    }


    // 2. GetProductStockLocations: devuelve las ubicaciones con stock.

    public function test_ListaUbicacionesConStock(): void
    {
        // Arrange
        $ubicacion = (object) ['product_id' => 5, 'amount' => 5.0];
        $consulta = $this->createMock(StockQueryStub::class);

        // expects(once) fija una llamada a where(); with() valida el filtro y willReturn() simula el resultado.
        
        $consulta->expects($this->once())->method('where')
            ->with('product_id = 5')->willReturn([$ubicacion]);
        $db = $this->crearDB(['stock_current_locations' => $consulta]);

        // Act
        $resultado = $this->crearServicio($db)->GetProductStockLocations(5);

        // Assert
        $this->assertSame([$ubicacion], $resultado);
    }


    // 3. GetProductStockLocations: incluye las ubicaciones de los subproductos.

    public function test_ListaUbicacionesConSubproductos(): void
    {
        // Arrange
        $filtro = '(product_id IN (SELECT sub_product_id FROM products_resolved WHERE parent_product_id = 5) OR product_id = 5)';
        $consulta = $this->createMock(StockQueryStub::class);
        // Exige que where() reciba el filtro que incluye los subproductos.
        $consulta->expects($this->once())->method('where')
            ->with($filtro)->willReturn(['ubicacion']);
        $db = $this->crearDB(['stock_current_locations' => $consulta]);

        // Act
        $resultado = $this->crearServicio($db)->GetProductStockLocations(5, true);

        // Assert
        $this->assertSame(['ubicacion'], $resultado);
    }


    // 4. GetStockEntry: devuelve la entrada de stock solicitada.

    public function test_ObtieneEntradaDeStock(): void
    {
        // Arrange
        $fila = (object) ['id' => 11, 'amount' => 9.0];
        $db = $this->crearDB(['stock' => $this->crearConsulta($fila)]);

        // Act
        $resultado = $this->crearServicio($db)->GetStockEntry(11);

        // Assert
        $this->assertSame($fila, $resultado);
    }


    // 5. GetLocationStockEntries: devuelve los lotes de una ubicación.

    public function test_ListaStockDeUbicacion(): void
    {
        // Arrange
        $lote = (object) ['amount' => 4.0];
        $stock = $this->createMock(StockQueryStub::class);
        // Exige una búsqueda por la ubicación y simula el lote encontrado.
        $stock->expects($this->once())->method('where')
            ->with('location_id', 2)->willReturn([$lote]);
        $db = $this->crearDB([
            'locations' => $this->crearConsulta((object) ['id' => 2]),
            'stock' => $stock
        ]);

        // Act
        $resultado = $this->crearServicio($db)->GetLocationStockEntries(2);

        // Assert
        $this->assertSame([$lote], $resultado);
    }


    // 6. GetLocationStockEntries: falla si la ubicación no existe.

    public function test_RechazaUbicacionInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['locations' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Location does not exist',
            static fn (StockService $servicio) => $servicio->GetLocationStockEntries(999));
    }


    // 7. GetProductIdFromBarcode: busca el producto por su código de barras.

    public function test_ObtieneIdPorCodigoDeBarras(): void
    {
        // Arrange
        $db = $this->crearDB(['product_barcodes' => $this->crearConsulta((object) ['product_id' => 5])]);

        // Act
        $resultado = $this->crearServicio($db)->GetProductIdFromBarcode(self::codigoBarras);

        // Assert
        $this->assertSame(5, $resultado);
    }


    // 8. GetProductIdFromBarcode: falla si el código no existe.

    public function test_RechazaCodigoDeBarrasInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['product_barcodes' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'No product with barcode ' . self::codigoBarras . ' found',
            static fn (StockService $servicio) => $servicio->GetProductIdFromBarcode(self::codigoBarras));
    }


    // 9. GetProductPriceHistory: devuelve los precios y el lugar de compra.

    public function test_DevuelveHistorialDePrecios(): void
    {
        // Arrange
        $historial = $this->createMock(StockQueryStub::class);
        // where() filtra por producto; orderBy() exige ordenar por fecha descendente y devuelve una fila.
        $historial->expects($this->once())->method('where')->with('product_id = :1', 5)->willReturnSelf();
        $historial->expects($this->once())->method('orderBy')->with('purchased_date', 'DESC')
            ->willReturn([(object) ['purchased_date' => '2026-11-01', 'price' => self::precio, 'shopping_location_id' => 7]]);

        $db = $this->crearDB([
            'products' => $this->crearConsulta($this->producto),
            'shopping_locations' => [(object) ['id' => 7, 'name' => 'Mercado']],
            'products_price_history' => $historial
        ]);

        // Act
        $resultado = $this->crearServicio($db)->GetProductPriceHistory(5);

        // Assert
        $this->assertSame(self::precio, $resultado[0]['price']);
        $this->assertSame('Mercado', $resultado[0]['shopping_location']->name);
    }


    // 10. GetProductPriceHistory: falla si el producto no existe.

    public function test_RechazaHistorialDeProductoInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Product does not exist or is inactive',
            static fn (StockService $servicio) => $servicio->GetProductPriceHistory(999));
    }


    // 11. GetDueProducts: encuentra productos que vencen dentro del plazo.

    public function test_DetectaProductosPorVencer(): void
    {
        // Arrange
        $servicio = $this->crearServicioConStock("WHERE best_before_date <= date('now', '5 days')");

        // Act
        $resultado = $servicio->GetDueProducts(5);

        // Assert
        $this->assertSame(['producto'], $resultado);
    }


    // 12. GetDueProducts: excluye los productos que ya vencieron.

    public function test_ExcluyeVencidosDeProximos(): void
    {
        // Arrange
        $servicio = $this->crearServicioConStock("WHERE best_before_date <= date('now', '5 days') AND best_before_date >= date()");

        // Act
        $resultado = $servicio->GetDueProducts(5, true);

        // Assert
        $this->assertSame(['producto'], $resultado);
    }


    // 13. GetExpiredProducts: encuentra productos vencidos.

    public function test_DetectaProductosVencidos(): void
    {
        // Arrange
        $servicio = $this->crearServicioConStock('WHERE best_before_date < date() AND due_type = 2');

        // Act
        $resultado = $servicio->GetExpiredProducts();

        // Assert
        $this->assertSame(['producto'], $resultado);
    }


    // 14. MergeProducts: impide fusionar un producto consigo mismo.

    public function test_RechazaFusionarProductoConsigoMismo(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta($this->producto)]);

        // Act y Assert
        $this->comprobarQueFalla($db, '$productIdToKeep cannot equal $productIdToRemove',
            static fn (StockService $servicio) => $servicio->MergeProducts(5, 5));
    }


    // 15. MergeProducts: falla si no existe el producto que se conservará.

    public function test_RechazaFusionarProductoInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, '$productIdToKeep does not exist or is inactive',
            static fn (StockService $servicio) => $servicio->MergeProducts(999, 5));
    }


    // 16. AddProduct: registra una compra en el stock y en el historial.

    public function test_RegistraCompra(): void
    {
        // Arrange
        $servicio = $this->crearServicioParaCompra();

        // Act
        $transaccion = $servicio->AddProduct(5, 5.0, '2026-12-31', StockService::TRANSACTION_TYPE_PURCHASE, '2026-11-20', self::precio);

        // Assert
        $this->assertIsString($transaccion);
    }


    // 17. AddProduct: falla si el producto no existe.

    public function test_RechazaCompraDeProductoInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Product does not exist or is inactive',
            static fn (StockService $servicio) => $servicio->AddProduct(999, 1.0, null, StockService::TRANSACTION_TYPE_PURCHASE, '2026-11-20', self::precio));
    }


    // 18. AddProduct: rechaza una cantidad igual o menor que cero.

    public function test_RechazaCompraConCantidadNoPositiva(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta($this->producto)]);

        // Act y Assert
        $this->comprobarQueFalla($db, "Amount can't be <= 0",
            static fn (StockService $servicio) => $servicio->AddProduct(5, 0.0, null, StockService::TRANSACTION_TYPE_PURCHASE, '2026-11-20', self::precio));
    }


    // 19. ConsumeProduct: falla si el producto no existe.

    public function test_RechazaConsumirProductoInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Product does not exist or is inactive',
            static fn (StockService $servicio) => $servicio->ConsumeProduct(999, 1.0, false, StockService::TRANSACTION_TYPE_CONSUME));
    }


    // 20. ConsumeProduct: rechaza una cantidad igual o menor que cero.

    public function test_RechazaConsumirCantidadNoPositiva(): void
    {
        // Arrange
        $db = $this->crearDB(['products' => $this->crearConsulta($this->producto)]);

        // Act y Assert
        $this->comprobarQueFalla($db, "Amount can't be <= 0",
            static fn (StockService $servicio) => $servicio->ConsumeProduct(5, 0.0, false, StockService::TRANSACTION_TYPE_CONSUME));
    }


    // 21. AddProductToShoppingList: falla si la lista no existe.

    public function test_RechazaListaDeComprasInexistente(): void
    {
        // Arrange
        $db = $this->crearDB(['shopping_lists' => $this->crearConsulta(null)]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Shopping list does not exist',
            static fn (StockService $servicio) => $servicio->AddProductToShoppingList(5, 1, -1, null, 999));
    }


    // 22. AddProductToShoppingList: falla si el producto no existe.

    public function test_RechazaProductoDeListaInexistente(): void
    {
        // Arrange
        $db = $this->crearDB([
            'shopping_lists' => $this->crearConsulta((object) ['id' => 1]),
            'products' => $this->crearConsulta(null)
        ]);

        // Act y Assert
        $this->comprobarQueFalla($db, 'Product does not exist or is inactive',
            static fn (StockService $servicio) => $servicio->AddProductToShoppingList(999));
    }


    // 23. AddProductToShoppingList: agrega un producto nuevo a la lista.

    public function test_AgregaProductoALista(): void
    {
        // Arrange
        $servicio = $this->crearServicioConListaVacia([
            'product_id' => 5,
            'amount' => 2,
            'qu_id' => 1,
            'shopping_list_id' => 1,
            'note' => null
        ]);

        // Act
        $servicio->AddProductToShoppingList(5, 2);

        // Assert: las expectativas del mock comprueban la fila guardada.
    }


    // 24. AddMissingProductsToShoppingList: agrega las cantidades faltantes.

    public function test_AgregaFaltantesALista(): void
    {
        // Arrange
        $servicio = $this->crearServicioConListaVacia([
            'product_id' => 5,
            'amount' => 5.0,
            'shopping_list_id' => 1,
            'qu_id' => 1
        ], ['GetMissingProducts']);

        // Exige una llamada a GetMissingProducts() y devuelve la cantidad faltante simulada.
        $servicio->expects($this->once())->method('GetMissingProducts')
            ->willReturn([(object) ['id' => 5, 'amount_missing' => 5.0]]);

        // Act
        $servicio->AddMissingProductsToShoppingList();

        // Assert: las expectativas del mock comprueban la cantidad agregada.
    }


    // 25. ClearShoppingList: elimina las entradas completadas.

    public function test_VaciaEntradasCompletadas(): void
    {
        // Arrange
        $compras = $this->createMock(StockQueryStub::class);

        // where() comprueba el filtro de entradas completadas; delete() debe ejecutarse una vez.

        $compras->expects($this->once())->method('where')
            ->with('shopping_list_id = :1 AND IFNULL(done, 0) = 1', 1)->willReturnSelf();
        
        $compras->expects($this->once())->method('delete');

        $db = $this->crearDB([
            'shopping_lists' => $this->crearConsulta((object) ['id' => 1]),
            'shopping_list' => $compras
        ]);

        // Act
        $this->crearServicio($db)->ClearShoppingList(1, true);

        // Assert: las expectativas del mock comprueban el filtro y la eliminación.
    }
}