<?php
// Compra (fase T4): datos de entrega, confirmación del pedido "pendiente de pago" y pago no disponible
// Usa el catálogo de prueba (SEED_TIENDA=1)

final class PedidosApiTest extends ApiTestCase
{
    private const ENTREGA = [
        'recibe' => 'Ana Pérez',
        'telefono' => '656-123-4567',
        'calle' => 'Av.   Juárez',
        'numero_exterior' => '123-B',
        'numero_interior' => '',
        'colonia' => 'Centro',
        'codigo_postal' => '32000',
        'ciudad' => 'Ciudad Juárez',
        'entidad' => 'Chihuahua',
        'referencias' => "Portón verde,\nfrente al parque",
    ];

    protected function setUp(): void
    {
        ServidorApi::url();
        BaseDatosPrueba::crearConEsquemaBase();
        BaseDatosPrueba::ejecutarSeeder(['SEED_TIENDA' => '1', 'SEED_DEMO' => '0']);
    }

    private function clienteRegistrado(string $correo = 'ana@ejemplo.mx'): ClienteApi
    {
        $cliente = $this->cliente();
        [$codigo] = $cliente->post('/api/tienda/registro', [
            'nombre' => 'Ana', 'apellidos' => 'Pérez', 'correo' => $correo, 'telefono' => '6561234567', 'password' => 'secreta123',
        ]);
        $this->assertSame(201, $codigo);
        return $cliente;
    }

    private function id(string $nombre): int
    {
        return (int) $this->valor('SELECT ID_Producto FROM Producto WHERE Nombre_Producto = ?', [$nombre]);
    }

    // Azúcar $34.00 × 2 + Arroz $32.50 × 1 = $100.50
    private function carrito(): array
    {
        return [
            ['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 2, 'precio' => 34],
            ['id' => $this->id('Arroz blanco 1 kg'), 'cantidad' => 1, 'precio' => 32.5],
        ];
    }

    private function confirmar(ClienteApi $cliente, ?array $productos = null, string $clave = 'clave-de-prueba-1'): array
    {
        return $cliente->post('/api/tienda/pedidos', ['productos' => $productos ?? $this->carrito(), 'clave' => $clave]);
    }

    private function comprar(ClienteApi $cliente, string $clave = 'clave-de-prueba-1'): string
    {
        [$codigo] = $cliente->post('/api/tienda/entrega', self::ENTREGA);
        $this->assertSame(200, $codigo);
        [$codigo, $cuerpo] = $this->confirmar($cliente, null, $clave);
        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
        return $cuerpo['folio'];
    }

    public function testLasRutasDeCompraPidenSesion(): void
    {
        $rutas = [['GET', 'entrega'], ['POST', 'entrega'], ['GET', 'pedidos'], ['POST', 'pedidos'],
            ['GET', 'pedido?folio=P1'], ['POST', 'pedido/pagar']];

        foreach ($rutas as [$metodo, $ruta]) {
            [$codigo, $cuerpo] = $this->cliente()->peticion($metodo, "/api/tienda/$ruta", $metodo === 'POST' ? [] : null);

            $this->assertSame(401, $codigo, "$metodo $ruta");
            $this->assertSame('Inicia sesión para continuar', $cuerpo['mensaje']);
        }
    }

    public function testSinPedidosLaEntregaSePrellenaConLosDatosDelCliente(): void
    {
        [$codigo, $cuerpo] = $this->clienteRegistrado()->get('/api/tienda/entrega');

        $this->assertSame(200, $codigo);
        $this->assertFalse($cuerpo['confirmada']);
        $this->assertSame('Ana Pérez', $cuerpo['entrega']['recibe']);
        $this->assertSame('6561234567', $cuerpo['entrega']['telefono']);
        $this->assertSame('', $cuerpo['entrega']['calle']);
    }

    public function testGuardarLaEntregaLaLimpiaYMarcaElPasoComoHecho(): void
    {
        $cliente = $this->clienteRegistrado();

        [$codigo, $cuerpo] = $cliente->post('/api/tienda/entrega', self::ENTREGA);
        [, $leida] = $cliente->get('/api/tienda/entrega');

        $this->assertSame(200, $codigo);
        $this->assertSame('6561234567', $cuerpo['entrega']['telefono']);
        $this->assertSame('Av. Juárez', $cuerpo['entrega']['calle']);
        $this->assertSame('Portón verde, frente al parque', $cuerpo['entrega']['referencias']);
        $this->assertTrue($leida['confirmada']);
        $this->assertSame($cuerpo['entrega'], $leida['entrega']);
    }

    public function testLaEntregaVaciaMarcaCadaCampoObligatorio(): void
    {
        // P8
        [$codigo, $cuerpo] = $this->clienteRegistrado()->post('/api/tienda/entrega', array_map(fn() => '  ', self::ENTREGA));

        $this->assertSame(400, $codigo);
        $this->assertSame([
            'recibe' => 'Escribe quién recibe el pedido',
            'telefono' => 'Escribe un teléfono de contacto',
            'calle' => 'Escribe la calle',
            'numero_exterior' => 'Escribe el número exterior',
            'colonia' => 'Escribe la colonia',
            'codigo_postal' => 'Escribe el código postal',
            'ciudad' => 'Escribe la ciudad o municipio',
            'entidad' => 'Elige la entidad',
        ], $cuerpo['errores']);
    }

    /** @return array<string, array{array, string, string}> */
    public static function entregasInvalidas(): array
    {
        return [
            // P9
            'código postal de 4 dígitos' => [['codigo_postal' => '3200'], 'codigo_postal', 'El código postal debe tener 5 dígitos'],
            'código postal con letras' => [['codigo_postal' => '32A00'], 'codigo_postal', 'El código postal debe tener 5 dígitos'],
            'teléfono corto' => [['telefono' => '656123'], 'telefono', 'El teléfono debe tener 10 dígitos'],
            'entidad que no existe' => [['entidad' => 'Narnia'], 'entidad', 'Elige una entidad de la lista'],
            // P10
            'quien recibe con HTML' => [['recibe' => '<b>Ana</b>'], 'recibe', 'El nombre de quien recibe solo puede tener letras, espacios, apóstrofos y guiones'],
            'número exterior con HTML' => [['numero_exterior' => '<1>'], 'numero_exterior', 'El número exterior solo puede tener letras, números, espacios y - / # .'],
            'número interior largo' => [['numero_interior' => 'Depto 12345'], 'numero_interior', 'El número interior no puede tener más de 10 caracteres'],
            'calle larga' => [['calle' => str_repeat('a', 101)], 'calle', 'La calle no puede tener más de 100 caracteres'],
            'referencias largas' => [['referencias' => str_repeat('a', 256)], 'referencias', 'Las referencias no pueden tener más de 255 caracteres'],
            'dato que no es texto' => [['colonia' => ['Centro']], 'colonia', 'Dato no válido'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('entregasInvalidas')]
    public function testRechazaDatosDeEntregaInvalidos(array $cambios, string $campo, string $mensaje): void
    {
        $cliente = $this->clienteRegistrado();

        [$codigo, $cuerpo] = $cliente->post('/api/tienda/entrega', array_merge(self::ENTREGA, $cambios));
        [, $leida] = $cliente->get('/api/tienda/entrega');

        $this->assertSame(400, $codigo);
        $this->assertSame([$campo => $mensaje], $cuerpo['errores']);
        $this->assertFalse($leida['confirmada']);
    }

    public function testLasComillasYAcentosEnLaDireccionSeGuardanComoTexto(): void
    {
        // P10: lo que no es formato se guarda tal cual y React lo muestra como texto
        $cliente = $this->clienteRegistrado();

        [$codigo, $cuerpo] = $cliente->post('/api/tienda/entrega', array_merge(self::ENTREGA, ['calle' => 'Calle "La Ñ" <esquina>']));

        $this->assertSame(200, $codigo);
        $this->assertSame('Calle "La Ñ" <esquina>', $cuerpo['entrega']['calle']);
    }

    public function testNoSeConfirmaSinPasarPorLaEntrega(): void
    {
        // P7
        [$codigo, $cuerpo] = $this->confirmar($this->clienteRegistrado());

        $this->assertSame(409, $codigo);
        $this->assertSame('entrega', $cuerpo['paso']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));
    }

    public function testConfirmarCreaElPedidoPendienteDePago(): void
    {
        $cliente = $this->clienteRegistrado();
        $azucar = $this->id('Azúcar estándar 1 kg');

        $folio = $this->comprar($cliente);

        $this->assertMatchesRegularExpression('/^P\d{6}-[A-HJ-NP-Z2-9]{5}$/', $folio);
        $pedido = $this->fila('SELECT * FROM Pedido WHERE Folio = ?', [$folio]);
        $this->assertSame('pendiente_pago', $pedido['Estado']);
        $this->assertEquals([86.64, 13.86, 100.5], [$pedido['Subtotal'], $pedido['IVA'], $pedido['Total']]);
        $this->assertSame(['Ana Pérez', '6561234567', 'Av. Juárez', 'Chihuahua'], [$pedido['Recibe'], $pedido['Telefono'], $pedido['Calle'], $pedido['Entidad']]);

        $detalle = BaseDatosPrueba::conexion()->query("SELECT ID_Producto, Cantidad, Precio_Unitario, Importe FROM Detalle_Pedido
            WHERE ID_Pedido = {$pedido['ID_Pedido']} ORDER BY ID_Detalle_Pedido")->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals([[$azucar, 2, 34, 68], [$this->id('Arroz blanco 1 kg'), 1, 32.5, 32.5]], $detalle);

        // D4: el pedido pendiente no descuenta existencias
        $this->assertSame(50, $this->existencia($azucar));
    }

    public function testDespuesDeConfirmarLaEntregaSeVuelveAPedirYSePrellenaConLaUltima(): void
    {
        $cliente = $this->clienteRegistrado();
        $this->comprar($cliente);

        [, $cuerpo] = $cliente->get('/api/tienda/entrega');

        // T-S4: la siguiente compra pide confirmar la entrega, prellenada con la del último pedido
        $this->assertFalse($cuerpo['confirmada']);
        $this->assertSame('Av. Juárez', $cuerpo['entrega']['calle']);
        $this->assertSame('32000', $cuerpo['entrega']['codigo_postal']);
    }

    public function testElMismoEnvioDosVecesNoDuplicaElPedido(): void
    {
        // P12: doble clic en "Confirmar"
        $cliente = $this->clienteRegistrado();
        $cliente->post('/api/tienda/entrega', self::ENTREGA);

        [$codigo1, $primero] = $this->confirmar($cliente);
        [$codigo2, $segundo] = $this->confirmar($cliente);

        $this->assertSame([201, true], [$codigo1, $primero['creado']]);
        $this->assertSame([200, false], [$codigo2, $segundo['creado']]);
        $this->assertSame($primero['folio'], $segundo['folio']);
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));
    }

    public function testSiElCarritoCambioNoSeCreaElPedidoYSeAvisa(): void
    {
        // T-S5 / P4: la existencia bajó entre el resumen y la confirmación
        $cliente = $this->clienteRegistrado();
        $cliente->post('/api/tienda/entrega', self::ENTREGA);
        BaseDatosPrueba::conexion()->exec("UPDATE Producto SET Cantidad = 1 WHERE Nombre_Producto = 'Azúcar estándar 1 kg'");

        [$codigo, $cuerpo] = $this->confirmar($cliente);

        $this->assertSame(409, $codigo);
        $this->assertSame('Tu carrito cambió. Revisa los avisos y vuelve a confirmar.', $cuerpo['mensaje']);
        $this->assertSame(['ajustado'], array_column($cuerpo['avisos'], 'tipo'));
        $this->assertSame(1, $cuerpo['lineas'][0]['cantidad']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));

        // La entrega sigue guardada: al confirmar otra vez con el carrito ajustado se crea el pedido
        $ajustado = array_map(fn($l) => ['id' => $l['id'], 'cantidad' => $l['cantidad'], 'precio' => $l['precio']], $cuerpo['lineas']);
        [$codigo] = $this->confirmar($cliente, $ajustado, 'clave-de-prueba-2');
        $this->assertSame(201, $codigo);
    }

    public function testUnPrecioAlteradoNoSeConfirmaSinAvisar(): void
    {
        // P11: el navegador dice $0.01; el servidor avisa el precio real y no crea el pedido
        $cliente = $this->clienteRegistrado();
        $cliente->post('/api/tienda/entrega', self::ENTREGA);

        [$codigo, $cuerpo] = $this->confirmar($cliente, [['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 1, 'precio' => 0.01]]);

        $this->assertSame(409, $codigo);
        $this->assertSame(['precio'], array_column($cuerpo['avisos'], 'tipo'));
        $this->assertEquals(34, $cuerpo['resumen']['total']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));
    }

    public function testNoSeConfirmaUnCarritoVacioNiSinClave(): void
    {
        $cliente = $this->clienteRegistrado();
        $cliente->post('/api/tienda/entrega', self::ENTREGA);

        [$vacio, $cuerpoVacio] = $this->confirmar($cliente, []);
        [$sinClave, $cuerpoClave] = $cliente->post('/api/tienda/pedidos', ['productos' => $this->carrito()]);
        [$claveMala] = $this->confirmar($cliente, null, 'x');

        $this->assertSame([409, 'Tu carrito está vacío'], [$vacio, $cuerpoVacio['mensaje']]);
        $this->assertSame([400, 'Falta la clave del envío'], [$sinClave, $cuerpoClave['mensaje']]);
        $this->assertSame(400, $claveMala);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));
    }

    public function testMisPedidosMuestraSoloLosDelCliente(): void
    {
        $ana = $this->clienteRegistrado();
        $primero = $this->comprar($ana, 'clave-ana-1');
        $segundo = $this->comprar($ana, 'clave-ana-2');
        $luis = $this->clienteRegistrado('luis@ejemplo.mx');

        [, $deAna] = $ana->get('/api/tienda/pedidos');
        [, $deLuis] = $luis->get('/api/tienda/pedidos');

        $this->assertSame([$segundo, $primero], array_column($deAna['pedidos'], 'folio'));
        $this->assertSame(
            ['folio', 'fecha', 'hora', 'estado', 'total', 'productos', 'piezas'],
            array_keys($deAna['pedidos'][0])
        );
        $this->assertEquals([100.5, 2, 3, 'pendiente_pago'], [
            $deAna['pedidos'][0]['total'], $deAna['pedidos'][0]['productos'], $deAna['pedidos'][0]['piezas'], $deAna['pedidos'][0]['estado'],
        ]);
        $this->assertSame([], $deLuis['pedidos']);
    }

    public function testElDetalleDelPedidoEsSoloDeSuCliente(): void
    {
        $ana = $this->clienteRegistrado();
        $folio = $this->comprar($ana);
        $luis = $this->clienteRegistrado('luis@ejemplo.mx');

        [$codigo, $cuerpo] = $ana->get("/api/tienda/pedido?folio=$folio");
        [$ajeno] = $luis->get("/api/tienda/pedido?folio=$folio");
        [$inexistente] = $ana->get('/api/tienda/pedido?folio=P000000-XXXXX');

        $this->assertSame(200, $codigo);
        $this->assertSame(['Azúcar estándar 1 kg', 'Arroz blanco 1 kg'], array_column($cuerpo['pedido']['lineas'], 'nombre'));
        $this->assertEquals(['subtotal' => 86.64, 'iva' => 13.86, 'total' => 100.5], array_intersect_key($cuerpo['pedido']['resumen'], array_flip(['subtotal', 'iva', 'total'])));
        $this->assertSame('Ciudad Juárez', $cuerpo['pedido']['entrega']['ciudad']);
        $this->assertSame([404, 404], [$ajeno, $inexistente]);
    }

    public function testPagarSiempreRespondeQueElPagoNoEstaDisponible(): void
    {
        // N11 / R14: no se cobra nada y el pedido sigue pendiente
        $cliente = $this->clienteRegistrado();
        $folio = $this->comprar($cliente);

        foreach (['tarjeta', 'transferencia', 'efectivo', 'tarjeta'] as $metodo) {
            [$codigo, $cuerpo] = $cliente->post('/api/tienda/pedido/pagar', ['folio' => $folio, 'metodo' => $metodo]);

            $this->assertSame(200, $codigo);
            $this->assertFalse($cuerpo['pagado']);
            $this->assertSame(
                "El pago en línea todavía no está disponible. No se hizo ningún cargo y tu pedido $folio sigue pendiente de pago.",
                $cuerpo['mensaje']
            );
        }
        $this->assertSame('pendiente_pago', $this->valor('SELECT Estado FROM Pedido WHERE Folio = ?', [$folio]));
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM Pedido'));
    }

    public function testPagarPideUnMetodoValidoYUnPedidoPropio(): void
    {
        $ana = $this->clienteRegistrado();
        $folio = $this->comprar($ana);
        $luis = $this->clienteRegistrado('luis@ejemplo.mx');

        [$sinMetodo, $cuerpo] = $ana->post('/api/tienda/pedido/pagar', ['folio' => $folio, 'metodo' => 'bitcoin']);
        [$ajeno] = $luis->post('/api/tienda/pedido/pagar', ['folio' => $folio, 'metodo' => 'tarjeta']);

        $this->assertSame(400, $sinMetodo);
        $this->assertSame(['metodo' => 'Elige un método de pago'], $cuerpo['errores']);
        $this->assertSame(404, $ajeno);
    }

    public function testAlIngresarOtroClienteNoHeredaLaEntregaDeLaSesion(): void
    {
        $this->clienteRegistrado('luis@ejemplo.mx');
        $navegador = $this->clienteRegistrado();
        $navegador->post('/api/tienda/entrega', self::ENTREGA);

        // Otra persona ingresa en el mismo navegador sin que Ana haya salido
        $navegador->post('/api/tienda/ingresar', ['correo' => 'luis@ejemplo.mx', 'password' => 'secreta123']);
        [, $cuerpo] = $navegador->get('/api/tienda/entrega');

        $this->assertFalse($cuerpo['confirmada']);
        $this->assertSame('', $cuerpo['entrega']['calle']);
    }
}
