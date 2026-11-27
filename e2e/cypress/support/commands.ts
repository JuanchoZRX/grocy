declare namespace Cypress {
	interface Chainable {
		login(): Chainable<void>
		sembrarDatos(): Chainable<void>
		limpiarDatos(): Chainable<void>
	}
}

function api(ruta: string, metodo = 'GET', datos?: object) {
	return fetch(`/api/objects/${ruta}`, {
		method: metodo,
		headers: datos ? { 'Content-Type': 'application/json' } : undefined,
		body: datos ? JSON.stringify(datos) : undefined
	}).then((respuesta) => {
		if (!respuesta.ok) {
			throw new Error(`${metodo} /api/objects/${ruta} respondió ${respuesta.status}`)
		}

		return respuesta.status === 204 ? null : respuesta.json()
	})
}

Cypress.Commands.add('login', () => {
	cy.session('admin', () => {
		cy.visit('/login')
		cy.get('#username').type('admin')
		cy.get('#password_input').type('admin')
		cy.get('#login-button').click()
		cy.location('pathname', { timeout: 15000 }).should('not.eq', '/login')
	})
})

Cypress.Commands.add('limpiarDatos', () => {
	cy.visit('/')

	return cy.then(async () => {
		for (const entidad of ['products', 'quantity_units', 'locations', 'shopping_locations', 'tasks']) {
			for (const objeto of (await api(entidad)) as Array<{ id: number }>) {
				await api(`${entidad}/${objeto.id}`, 'DELETE')
			}
		}
	})
})

Cypress.Commands.add('sembrarDatos', () => {
	return cy.then(async () => {
		for (const [entidad, nombres] of Object.entries({
			locations: ['Refrigeradora', 'Cocina'],
			quantity_units: ['Pack', 'Piece'],
			shopping_locations: ['Automercado', 'pricesmart', 'Pali'],
			tasks: ['Barrer']
		})) {
			for (const nombre of nombres) await api(entidad, 'POST', { name: nombre })
		}

		const [ubicaciones, unidades] = (await Promise.all([api('locations'), api('quantity_units')])) as Array<
			Array<{ id: number; name: string }>
		>
		const ubicacion = ubicaciones.find((fila) => fila.name === 'Refrigeradora')!.id
		const unidad = unidades.find((fila) => fila.name === 'Pack')!.id

		for (const nombre of ['Tomate', 'harina', 'Pollo', 'Helado', 'Miel']) {
			await api('products', 'POST', {
				name: nombre,
				location_id: ubicacion,
				qu_id_purchase: unidad,
				qu_id_stock: unidad
			})
		}
	})
})
