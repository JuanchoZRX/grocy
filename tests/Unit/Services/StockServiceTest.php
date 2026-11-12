<?php

namespace Grocy\Tests\Unit\Services;

use Grocy\Services\DatabaseService;
use Grocy\Services\StockService;


// LocalizationService usa GROCY_LOCALE, que normalmente define LocaleMiddleware.

if (!defined('GROCY_LOCALE')) define('GROCY_LOCALE', GROCY_DEFAULT_LOCALE);



final class StockServiceTest extends \ServiceTestCase
{
    // Datos de la plantilla de pruebas.
	
    private const nombreProducto = 'Patata';
    private const codigoBarras = '7501234567890';
    private const precio = 1000.0;
    private const fecha = 'Y-m-d';

    private $db;
    private int $ubicacionId;
    private int $productoId;



    // Prepara la base y crea el producto de prueba con su código de barras.

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = DatabaseService::GetInstance()->GetDbConnection();
        $this->ubicacionId = $this->db->locations()->where('name', 'Fridge')->fetch()->id;
        $unidadId = $this->db->quantity_units()->where('name', 'Piece')->fetch()->id;

        $this->db->insert('products', [
            'name' => self::nombreProducto,
            'location_id' => $this->ubicacionId,
            'qu_id_purchase' => $unidadId,
            'qu_id_stock' => $unidadId,
            'min_stock_amount' => 5,
            'due_type' => 2
        ]);
        $this->productoId = $this->db->lastInsertId();
        $this->db->insert('product_barcodes', ['product_id' => $this->productoId, 'barcode' => self::codigoBarras]);
    }


    // Crea una instancia nueva de StockService para cada uso.
    private function servicio(): StockService
    {
        return new StockService();
    }


    // Inserta un lote de stock del producto con la fecha de vencimiento indicada.

    private function agregarStock(float $cantidad, string $vencimiento = '+30 days'): void
    {
        $this->db->insert('stock', [
            'product_id' => $this->productoId,
            'amount' => $cantidad,
            'stock_id' => uniqid(),
            'best_before_date' => date(self::fecha, strtotime($vencimiento)),
            'location_id' => $this->ubicacionId
        ]);
    }


    // Suma las cantidades de todos los lotes del producto.

    private function stockTotal(): float
    {
        return $this->db->stock()->where('product_id = :1', $this->productoId)->sum('amount');
    }


    // Registra una compra del producto con la cantidad indicada.

    private function agregarCompra(float $cantidad): void
    {
        $this->servicio()->AddProduct($this->productoId, $cantidad, null, StockService::TRANSACTION_TYPE_PURCHASE, date(self::fecha), self::precio);
    }



    /**
     * Prueba 1: GetStockEntry devuelve una entrada por id.
     * Qué prueba: la cantidad guardada.
     * Se espera: 9 unidades.
     */

    public function test_ObtieneEntradaDeStock(): void
    {
        // Arrange
        $this->agregarStock(9);

        // Act y Assert
        $this->assertEquals(9.0, $this->servicio()->GetStockEntry($this->db->lastInsertId())->amount);
    }



    /**
     * Prueba 2: GetProductStockEntries lista los lotes del producto.
     * Qué prueba: una fila por lote.
     * Se espera: una entrada con 5 unidades.
     */

    public function test_ListaLotesDelProducto(): void
    {
        // Arrange
        $this->agregarStock(5);

        // Act
        $entradas = $this->servicio()->GetProductStockEntries($this->productoId);

        // Assert
        $this->assertSame(1, $entradas->count());
        $this->assertEquals(5.0, $entradas->fetch()->amount);
    }



    /**
     * Prueba 3: GetLocationStockEntries lista el stock de una ubicación.
     * Qué prueba: el filtro por ubicación.
     * Se espera: una entrada con 4 unidades.
     */

    public function test_ListaStockDeUbicacion(): void
    {
        // Arrange
        $this->agregarStock(4);

        // Act
        $entradas = $this->servicio()->GetLocationStockEntries($this->ubicacionId);

        // Assert
        $this->assertSame(1, $entradas->count());
        $this->assertEquals(4.0, $entradas->fetch()->amount);
    }



    /**
     * Prueba 4: GetProductStockLocations lista ubicaciones con stock.
     * Qué prueba: la ubicación del lote.
     * Se espera: una fila con 5 unidades.
     */

    public function test_ListaUbicacionesConStock(): void
    {
        // Arrange
        $this->agregarStock(5);

        // Act
        $filas = $this->servicio()->GetProductStockLocations($this->productoId);

        // Assert
        $this->assertSame(1, $filas->count());
        $this->assertEquals(5.0, $filas->fetch()->amount);
    }



    /**
     * Prueba 5: GetCurrentStockLocations devuelve el stock por ubicación.
     * Qué prueba: la vista incluye el producto.
     * Se espera: una fila con 6 unidades.
     */

    public function test_DevuelveStockPorUbicacion(): void
    {
        // Arrange
        $this->agregarStock(6);

        // Act y Assert
        $this->assertEquals(6.0, $this->servicio()->GetCurrentStockLocations()[0]->amount);
    }



    /**
     * Prueba 6: GetCurrentStockLocationContent devuelve el contenido.
     * Qué prueba: la consulta de contenido con stock.
     * Se espera: una fila con 7 unidades.
     */

    public function test_DevuelveContenidoDeUbicacion(): void
    {
        // Arrange
        $this->agregarStock(7);

        // Act y Assert
        $this->assertEquals(7.0, $this->servicio()->GetCurrentStockLocationContent()[0]->amount);
    }



    /**
     * Prueba 7: GetCurrentStock lista el stock actual.
     * Qué prueba: la cantidad total del producto.
     * Se espera: una fila con 4 unidades.
     */

    public function test_ListaStockActual(): void
    {
        // Arrange
        $this->agregarStock(4);

        // Act y Assert
        $this->assertEquals(4.0, $this->servicio()->GetCurrentStock()[0]->amount);
    }



    /**
     * Prueba 8: GetProductDetails devuelve el detalle del producto.
     * Qué prueba: nombre y stock del detalle.
     * Se espera: el producto de prueba con 6 unidades.
     */

    public function test_DevuelveDetalleDelProducto(): void
    {
        // Arrange
        $this->agregarStock(6);

        // Act
        $detalles = $this->servicio()->GetProductDetails($this->productoId);

        // Assert
        $this->assertSame(self::nombreProducto, $detalles['product']->name);
        $this->assertEquals(6.0, $detalles['stock_amount']);
    }



    /**
     * Prueba 9: GetProductIdFromBarcode encuentra el producto.
     * Qué prueba: el código creado en la preparación.
     * Se espera: el id del producto.
     */

    public function test_ObtieneIdPorCodigoDeBarras(): void
    {
        // Act y Assert
        $this->assertSame($this->productoId, $this->servicio()->GetProductIdFromBarcode(self::codigoBarras));
    }



    /**
     * Prueba 10: GetProductPriceHistory devuelve una compra.
     * Qué prueba: la vista toma los movimientos de compra.
     * Se espera: una entrada con el precio configurado.
     */

    public function test_DevuelveHistorialDePrecios(): void
    {
        // Arrange
        $this->agregarCompra(1);

        // Act
        $historial = $this->servicio()->GetProductPriceHistory($this->productoId);

        // Assert
        $this->assertSame(1, count($historial));
        $this->assertEquals(self::precio, $historial[0]['price']);
    }



    /**
     * Prueba 11: GetMissingProducts detecta stock faltante.
     * Qué prueba: mínimo 5 sin stock.
     * Se espera: faltante de 5 unidades.
     */

    public function test_DetectaProductosFaltantes(): void
    {
        // Act y Assert
        $this->assertEquals(5.0, $this->servicio()->GetMissingProducts()[0]->amount_missing);
    }



    /**
     * Prueba 12: GetDueProducts detecta un próximo vencimiento.
     * Qué prueba: stock que vence dentro del rango.
     * Se espera: una fila del producto.
     */

    public function test_DetectaProductosPorVencer(): void
    {
        // Arrange
        $this->agregarStock(1, '+2 days');

        // Act y Assert
        $this->assertSame(1, count($this->servicio()->GetDueProducts(5)));
    }



    /**
     * Prueba 13: GetExpiredProducts detecta un producto vencido.
     * Qué prueba: stock con fecha anterior a hoy.
     * Se espera: una fila del producto.
     */

    public function test_DetectaProductosVencidos(): void
    {
        // Arrange
        $this->agregarStock(1, '-1 day');

        // Act y Assert
        $this->assertSame(1, count($this->servicio()->GetExpiredProducts()));
    }



    /**
     * Prueba 14: AddProduct registra stock y booking.
     * Qué prueba: una compra crea stock y movimiento.
     * Se espera: 5 unidades y 1 booking.
     */

    public function test_RegistraCompra(): void
    {
        // Act
        $this->agregarCompra(5);

        // Assert
        $this->assertSame(5.0, $this->stockTotal());
        $this->assertSame(1, $this->db->stock_log()->where('product_id = :1', $this->productoId)->count());
    }



    /**
     * Prueba 15: ConsumeProduct descuenta stock.
     * Qué prueba: consumir 3 de 10.
     * Se espera: 7 unidades.
     */

    public function test_DescuentaStockAlConsumir(): void
    {
        // Arrange
        $this->agregarStock(10);

        // Act
        $this->servicio()->ConsumeProduct($this->productoId, 3, false, StockService::TRANSACTION_TYPE_CONSUME);

        // Assert
        $this->assertSame(7.0, $this->stockTotal());
    }



    /**
     * Prueba 16: InventoryProduct ajusta el stock.
     * Qué prueba: corregir de 10 a 12.
     * Se espera: 12 unidades.
     */

    public function test_AjustaStockAlInventariar(): void
    {
        // Arrange
        $this->agregarStock(10);

        // Act
        $this->servicio()->InventoryProduct($this->productoId, 12, date(self::fecha));

        // Assert
        $this->assertSame(12.0, $this->stockTotal());
    }



    /**
     * Prueba 17: OpenProduct marca stock abierto.
     * Qué prueba: abrir 4 de 10 separa el lote.
     * Se espera: 4 abiertas y 6 cerradas.
     */

    public function test_MarcaStockAbierto(): void
    {
        // Arrange
        $this->agregarStock(10);

        // Act
        $this->servicio()->OpenProduct($this->productoId, 4);

        // Assert
        $this->assertEquals(4.0, $this->db->stock()->where('open = 1')->sum('amount'));
        $this->assertEquals(6.0, $this->db->stock()->where('open = 0')->sum('amount'));
    }



    /**
     * Prueba 18: CopyProduct duplica un producto.
     * Qué prueba: copia con otro id y nombre con prefijo.
     * Se espera: "Copy of Producto de prueba".
     */

    public function test_DuplicaProducto(): void
    {
        // Act
        $idCopia = (int)$this->servicio()->CopyProduct($this->productoId);

        // Assert
        $this->assertNotSame($this->productoId, $idCopia);
        $this->assertSame('Copy of ' . self::nombreProducto, $this->db->products()->where('id = :1', $idCopia)->fetch()->name);
    }



    /**
     * Prueba 19: AddProductToShoppingList agrega un producto.
     * Qué prueba: la entrada con cantidad.
     * Se espera: cantidad 2.
     */

    public function test_AgregaProductoALista(): void
    {
        // Act
        $this->servicio()->AddProductToShoppingList($this->productoId, 2);

        // Assert
        $this->assertEquals(2.0, $this->db->shopping_list()->where('product_id = :1', $this->productoId)->fetch()->amount);
    }



    /**
     * Prueba 20: RemoveProductFromShoppingList quita un producto.
     * Qué prueba: quitar la cantidad total.
     * Se espera: cero entradas.
     */

    public function test_QuitaProductoDeLista(): void
    {
        // Arrange
        $this->servicio()->AddProductToShoppingList($this->productoId, 3);

        // Act
        $this->servicio()->RemoveProductFromShoppingList($this->productoId, 3);

        // Assert
        $this->assertSame(0, $this->db->shopping_list()->where('product_id = :1', $this->productoId)->count());
    }



    /**
     * Prueba 21: ClearShoppingList borra la lista.
     * Qué prueba: limpiar elimina las entradas.
     * Se espera: cero entradas.
     */

    public function test_VaciaListaDeCompras(): void
    {
        // Arrange
        $this->servicio()->AddProductToShoppingList($this->productoId, 1);

        // Act
        $this->servicio()->ClearShoppingList();

        // Assert
        $this->assertSame(0, $this->db->shopping_list()->where('product_id = :1', $this->productoId)->count());
    }



    /**
     * Prueba 22: AddMissingProductsToShoppingList agrega faltantes.
     * Qué prueba: mínimo 5 sin stock.
     * Se espera: entrada con cantidad 5.
     */

    public function test_AgregaFaltantesALista(): void
    {
        // Act
        $this->servicio()->AddMissingProductsToShoppingList();

        // Assert
        $this->assertEquals(5.0, $this->db->shopping_list()->where('product_id = :1', $this->productoId)->fetch()->amount);
    }



    /**
     * Prueba 23: GetShoppinglistInPrintableStrings devuelve la lista.
     * Qué prueba: el producto aparece en el texto.
     * Se espera: una línea con el nombre del producto.
     */

    public function test_DevuelveListaEnTexto(): void
    {
        // Arrange
        $this->servicio()->AddProductToShoppingList($this->productoId, 2);

        // Act
        $lineas = $this->servicio()->GetShoppinglistInPrintableStrings();

        // Assert
        $this->assertSame(1, count($lineas));
        $this->assertStringContainsString(self::nombreProducto, $lineas[0]);
    }



    /**
     * Prueba 24: AddExpiredProductsToShoppingList agrega vencidos.
     * Qué prueba: un producto vencido se agrega a la lista.
     * Se espera: entrada con cantidad 1.
     */

    public function test_AgregaVencidosALista(): void
    {
        // Arrange
        $this->agregarStock(1, '-1 day');

        // Act
        $this->servicio()->AddExpiredProductsToShoppingList();

        // Assert
        $this->assertEquals(1.0, $this->db->shopping_list()->where('product_id = :1', $this->productoId)->fetch()->amount);
    }



    /**
     * Prueba 25: CompactStockEntries une entradas iguales.
     * Qué prueba: dos lotes idénticos se juntan.
     * Se espera: una entrada con 15 unidades.
     */

    public function test_UneEntradasDeStock(): void
    {
        // Arrange
        $this->agregarStock(10);
        $this->agregarStock(5);

        // Act
        $this->servicio()->CompactStockEntries($this->productoId);

        // Assert
        $this->assertSame(1, $this->db->stock()->where('product_id = :1', $this->productoId)->count());
        $this->assertSame(15.0, $this->stockTotal());
    }
}
