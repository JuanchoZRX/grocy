function abrirProducto(nombre: string) {
	cy.visit('/products')
	cy.get('#products-table tbody')
		.contains('td', nombre)
		.parent('tr')
		.find('a.btn-info')
		.invoke('attr', 'href')
		.then((href) => {
			cy.visit(href!)
		})
	cy.location('pathname').should('match', /\/product\/\d+$/)
}

function seleccionarPrimeraOpcion(selector: string) {
	cy.get(selector).find('option').then(($opciones) => {
		const valor = $opciones.toArray().map((opcion) => opcion.value).find((value) => value)
		cy.get(selector).select(valor!, { force: true })
	})
}

function guardarProducto(destino: 'continue' | 'return' = 'return') {
	cy.get(`.save-product-button[data-location="${destino}"]`).click()
}

describe('Casos de prueba - Grocy', () => {
	beforeEach(() => {
		cy.login()
		cy.limpiarDatos()
		cy.sembrarDatos()
	})

	it('CU01 - Caso 1 - Abre el alta de productos', () => {
		// Arrange
		cy.visit('/products')

		// Act
		cy.contains('#related-links a', 'Añadir').click()

		// Assert
		cy.location('pathname').should('eq', '/product/new')
		cy.get('#name').should('be.visible')
	})

	it('CU02 - Caso 2 - Crea el producto Papa', () => {
		// Arrange
		cy.visit('/products')
		cy.contains('#related-links a', 'Añadir').click()

		// Act
		cy.get('#name').type('Papa')
		seleccionarPrimeraOpcion('#location_id')
		seleccionarPrimeraOpcion('#qu_id_stock')
		seleccionarPrimeraOpcion('#qu_id_purchase')
		guardarProducto('return')

		// Assert
		cy.location('pathname').should('eq', '/products')
		cy.contains('#products-table tbody tr', 'Papa').should('exist')
	})

	it('CU03 - Caso 3 - Rechaza un producto Tomate duplicado', () => {
		// Arrange
		cy.visit('/products')
		cy.contains('#related-links a', 'Añadir').click()

		// Act
		cy.get('#name').type('Tomate')
		seleccionarPrimeraOpcion('#location_id')
		seleccionarPrimeraOpcion('#qu_id_stock')
		seleccionarPrimeraOpcion('#qu_id_purchase')
		guardarProducto('return')

		// Assert
		cy.contains('.toast-error', 'Error al guardar, probablemente este artículo ya existe').should('be.visible')
		cy.visit('/products')
		cy.contains('#products-table tbody tr', 'Tomate').should('have.length', 1)
	})

	it('CU04 - Caso 4 - Renombra Helado a Granizado', () => {
		// Arrange
		abrirProducto('Helado')

		// Act
		cy.get('#name').clear().type('Granizado')
		guardarProducto('return')

		// Assert
		cy.location('pathname').should('eq', '/products')
		cy.contains('#products-table tbody tr', 'Granizado').should('exist')
		cy.contains('#products-table tbody tr', 'Helado').should('not.exist')
	})

	it('CU05 - Caso 5 - Rechaza renombrar Helado a Tomate', () => {
		// Arrange
		abrirProducto('Helado')

		// Act
		cy.get('#name').clear().type('Tomate')
		guardarProducto('return')

		// Assert
		cy.contains('.toast-error', 'Error al guardar, probablemente este artículo ya existe').should('be.visible')
		cy.reload()
		cy.get('#name').should('have.value', 'Helado')
	})

	it('CU06 - Caso 10 - Guarda 5 como minimo de existencias de Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#min_stock_amount').clear().type('5')
		guardarProducto('continue')

		// Assert
		cy.get('#min_stock_amount').should('have.value', '5')
	})

	it('CU07 - Caso 11 - Rechaza -1 como minimo de existencias de Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#min_stock_amount').clear().type('-1')
		guardarProducto('continue')

		// Assert
		cy.get('#group-min_stock_amount .invalid-feedback').should('be.visible').and('contain', 'Esto no puede ser menor que 0')
		cy.reload()
		cy.get('#min_stock_amount').should('have.value', '0')
	})

	it('CU08 - Caso 12 - Guarda 5 dias por defecto en Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#default_best_before_days').clear().type('5')
		guardarProducto('continue')

		// Assert
		cy.get('#default_best_before_days').should('have.value', '5')
	})

	it('CU09 - Caso 13 - Rechaza -2 dias por defecto en Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#default_best_before_days').clear().type('-2')
		guardarProducto('continue')

		// Assert
		cy.get('#group-default_best_before_days .invalid-feedback').should('be.visible').and('contain', 'Esto no puede ser menor que -1')
		cy.reload()
		cy.get('#default_best_before_days').should('have.value', '0')
	})

	it('CU10 - Caso 14 - Guarda 0 dias despues de abierto en Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#default_best_before_days_after_open').clear().type('0')
		guardarProducto('continue')

		// Assert
		cy.get('#default_best_before_days_after_open').should('have.value', '0')
	})

	it('CU11 - Caso 15 - Rechaza -1 dias despues de abierto en Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#default_best_before_days_after_open').clear().type('-1')
		guardarProducto('continue')

		// Assert
		cy.get('#group-default_best_before_days_after_open .invalid-feedback').should('be.visible').and('contain', 'Esto no puede ser menor que 0')
		cy.reload()
		cy.get('#default_best_before_days_after_open').should('have.value', '0')
	})

	it('CU12 - Caso 20 - Guarda 5 como peso de contenedor de Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#enable_tare_weight_handling').check({ force: true })
		cy.get('#tare_weight').clear().type('5')
		guardarProducto('continue')

		// Assert
		cy.get('#tare_weight').should('have.value', '5')
		cy.get('#enable_tare_weight_handling').should('be.checked')
	})

	it('CU13 - Caso 21 - Rechaza -1 como peso de contenedor de Tomate', () => {
		// Arrange
		abrirProducto('Tomate')

		// Act
		cy.get('#enable_tare_weight_handling').check({ force: true })
		cy.get('#tare_weight').clear().type('-1')
		guardarProducto('continue')

		// Assert
		cy.get('#group-tare_weight .invalid-feedback').should('be.visible').and('contain', 'Esto no puede ser menor que 0')
		cy.reload()
		cy.get('#tare_weight').should('have.value', '0')
	})

	it('CU14 - Caso 22 - Registra la unidad Docena', () => {
		// Arrange
		cy.visit('/quantityunits')
		cy.get('a[href*="/quantityunit/new"]').first().click()

		// Act
		cy.get('#name').type('Docena')
		cy.get('#name_plural').type('Docenas')
		cy.get('.save-quantityunit-button[data-location="return"]').click()

		// Assert
		cy.location('pathname').should('eq', '/quantityunits')
		cy.contains('td', 'Docena').should('exist')
	})

	it('CU15 - Caso 24 - Registra la tienda Mercadona', () => {
		// Arrange
		cy.visit('/shoppinglocation/new')

		// Act
		cy.get('#name').type('Mercadona')
		cy.get('#save-shopping-location-button').click()

		// Assert
		cy.location('pathname').should('eq', '/shoppinglocations')
		cy.contains('#shoppinglocations-table tbody tr', 'Mercadona').should('exist')
	})
})
