<?php
// Carrito de la tienda en línea. Vive en el navegador; el servidor lo revisa en cada paso (T-S3):
// precios vigentes, existencias, productos dados de baja y el desglose del IVA.
// El precio que manda el navegador solo sirve para avisar si cambió: los importes usan siempre el de la base de datos

require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/configuracion.php';

class Carrito
{
    const MAX_LINEAS = 100;

    // Desglose de un total que ya incluye IVA (D2), en centavos: subtotal = total ÷ (1 + tasa) redondeado,
    // y el IVA es la diferencia, para que subtotal + IVA dé siempre el total exacto (igual que desglosarIVA en la tienda)
    public static function desglose(int $totalCentavos, float $tasa): array
    {
        $subtotal = (int) round($totalCentavos / (1 + $tasa / 100));
        return [$subtotal, $totalCentavos - $subtotal];
    }//-- Fin funcion desglose


    private static function dinero(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    private static function pesos(float $monto): string
    {
        return '$' . number_format($monto, 2);
    }


    // $lineas: [[id, cantidad, precio que vio el cliente o null], ...] ya validadas
    // Regresa las líneas vigentes (con la cantidad ajustada a la existencia), los avisos y el resumen
    public static function revisar(array $lineas): array
    {
        $productos = Catalogo::productosPorId(array_column($lineas, 0));
        $faltantes = array_diff(array_column($lineas, 0), array_keys($productos));
        $nombres = Catalogo::nombres($faltantes);

        $vigentes = [];
        $avisos = [];
        $totalCentavos = 0;
        $piezas = 0;

        foreach ($lineas as [$id, $cantidad, $precioVisto]) {
            $producto = $productos[$id] ?? null;

            if ($producto === null) {
                $avisos[] = ['id' => $id, 'tipo' => 'retirado', 'mensaje' => isset($nombres[$id])
                    ? "«{$nombres[$id]}» ya no está a la venta y se quitó de tu carrito."
                    : 'Un producto de tu carrito ya no está a la venta y se quitó.'];
                continue;
            }
            $nombre = $producto['nombre'];

            if ($producto['existencia'] === 0) {
                $avisos[] = ['id' => $id, 'tipo' => 'agotado', 'mensaje' => "«{$nombre}» se agotó y se quitó de tu carrito."];
                continue;
            }
            if ($cantidad > $producto['existencia']) {
                $cantidad = $producto['existencia'];
                $avisos[] = ['id' => $id, 'tipo' => 'ajustado',
                    'mensaje' => "Solo hay {$cantidad} disponibles de «{$nombre}»; ajustamos tu cantidad a {$cantidad}."];
            }
            if ($precioVisto !== null && aCentavos($precioVisto) !== aCentavos($producto['precio'])) {
                $avisos[] = ['id' => $id, 'tipo' => 'precio', 'mensaje' => "El precio de «{$nombre}» cambió de "
                    . self::pesos($precioVisto) . ' a ' . self::pesos($producto['precio']) . '.'];
            }

            $importe = aCentavos($producto['precio']) * $cantidad;
            $totalCentavos += $importe;
            $piezas += $cantidad;
            $vigentes[] = [
                'id' => $id,
                'nombre' => $nombre,
                'unidad' => $producto['unidad'],
                'precio' => $producto['precio'],
                'cantidad' => $cantidad,
                'existencia' => $producto['existencia'],
                'disponibilidad' => $producto['disponibilidad'],
                'importe' => self::dinero($importe),
            ];
        }

        $tasa = Configuracion::tasaIVA();
        [$subtotal, $iva] = self::desglose($totalCentavos, $tasa);

        return [
            'lineas' => $vigentes,
            'avisos' => $avisos,
            'resumen' => [
                'productos' => count($vigentes),
                'piezas' => $piezas,
                'subtotal' => self::dinero($subtotal),
                'iva' => self::dinero($iva),
                'tasa_iva' => $tasa,
                'envio' => 0,
                'total' => self::dinero($totalCentavos),
            ],
        ];
    }//-- Fin funcion revisar


    // Lee y valida el carrito que manda el navegador: {productos: [{id, cantidad, precio?}, ...]}
    // Regresa [[id, cantidad, precio o null], ...]
    public static function leer(array $data): array
    {
        $productos = $data['productos'] ?? null;
        if (!is_array($productos) || !array_is_list($productos)) {
            throw new ErrorValidacion('El carrito debe ser una lista de productos');
        }
        if (count($productos) > self::MAX_LINEAS) {
            throw new ErrorValidacion('El carrito no puede tener más de ' . self::MAX_LINEAS . ' productos distintos');
        }

        $lineas = [];
        foreach ($productos as $i => $linea) {
            if (!is_array($linea)) {
                throw new ErrorValidacion('Cada producto del carrito debe traer id y cantidad');
            }
            $n = $i + 1;
            $id = campoEntero(["El producto $n" => $linea['id'] ?? null], "El producto $n", true, 1);
            $cantidad = campoEntero(["La cantidad del producto $n" => $linea['cantidad'] ?? null], "La cantidad del producto $n", true, 1);
            $precio = campoDinero(["El precio del producto $n" => $linea['precio'] ?? null], "El precio del producto $n", false);

            if (isset($lineas[$id])) {
                throw new ErrorValidacion("El producto $n está repetido en el carrito");
            }
            $lineas[$id] = [$id, $cantidad, $precio];
        }
        return array_values($lineas);
    }//-- Fin funcion leer
}
