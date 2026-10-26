<?php

require_once __DIR__ . '/carrito.php';
require_once __DIR__ . '/entrega.php';

/**
 * Pedidos de la tienda en línea. Se crean "pendientes de pago" (D4): no descuentan ni apartan existencias.
 * El pago nunca se procesa (D6): no hay conexión con bancos ni pasarelas.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Carrito
 * @see Entrega
 */
class Pedido
{
    const ESTADO_PENDIENTE = 'pendiente_pago';

    /**
     * Folio que ve el cliente: P + fecha + 5 caracteres al azar (sin 0/O ni 1/I para dictarlo sin confusión).
     *
     * @return string El folio, por ejemplo P261026-K7M2Q
     */
    private static function nuevoFolio(): string
    {
        $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $sufijo = '';
        for ($i = 0; $i < 5; $i++) {
            $sufijo .= $letras[random_int(0, strlen($letras) - 1)];
        }
        return 'P' . date('ymd') . '-' . $sufijo;
    }//-- Fin funcion nuevo folio


    /**
     * Crea el pedido con el carrito revisado otra vez (T-S5) y los datos de entrega.
     * Si algo cambió (existencias, precios, bajas) no se crea y el cliente ve qué se ajustó antes de confirmar de nuevo.
     *
     * @param int    $ID_Cliente Cliente con la sesión iniciada
     * @param array  $lineas     Carrito leído con Carrito::leer()
     * @param array  $entrega    Datos de entrega ya validados
     * @param string $clave      Clave que genera el navegador por cada confirmación (ver folioPorClave())
     * @return array [folio, true si se creó ahora, false si otro envío con la misma clave lo creó primero]
     * @throws ErrorDatos 409 "Tu carrito cambió..." con la revisión del carrito si hay avisos
     * @throws ErrorApi 409 "Tu carrito está vacío"
     * @throws PDOException Si falla la consulta, o si el folio se repite tres veces
     * @see Carrito::revisar()
     */
    public static function crear(int $ID_Cliente, array $lineas, array $entrega, string $clave): array
    {
        // T-S5: se revisa otra vez. Si algo cambió (existencias, precios, bajas) no se crea el pedido
        // y el cliente ve qué se ajustó antes de confirmar de nuevo
        $revision = Carrito::revisar($lineas);
        if ($revision['avisos']) {
            throw new ErrorDatos('Tu carrito cambió. Revisa los avisos y vuelve a confirmar.', 409, $revision);
        }
        if (!$revision['lineas']) {
            throw new ErrorApi('Tu carrito está vacío', 409);
        }

        $resumen = $revision['resumen'];
        $connection = new Conexion;

        for ($intento = 1; ; $intento++) {
            $folio = self::nuevoFolio();
            $connection->beginTransaction();
            try {
                $sql = $connection->prepare('INSERT INTO Pedido (Folio, ID_Cliente, Clave_Envio, Fecha, Hora, Subtotal, IVA, Total,
                    Estado, Recibe, Telefono, Calle, Numero_Exterior, Numero_Interior, Colonia, Codigo_Postal, Ciudad, Entidad, Referencias)
                    VALUES (:Folio, :ID_Cliente, :Clave, CURDATE(), CURTIME(), :Subtotal, :IVA, :Total, :Estado, :Recibe, :Telefono,
                    :Calle, :Exterior, :Interior, :Colonia, :CP, :Ciudad, :Entidad, :Referencias)');
                $sql->execute([
                    ':Folio' => $folio,
                    ':ID_Cliente' => $ID_Cliente,
                    ':Clave' => $clave,
                    ':Subtotal' => $resumen['subtotal'],
                    ':IVA' => $resumen['iva'],
                    ':Total' => $resumen['total'],
                    ':Estado' => self::ESTADO_PENDIENTE,
                    ':Recibe' => $entrega['recibe'],
                    ':Telefono' => $entrega['telefono'],
                    ':Calle' => $entrega['calle'],
                    ':Exterior' => $entrega['numero_exterior'],
                    ':Interior' => $entrega['numero_interior'],
                    ':Colonia' => $entrega['colonia'],
                    ':CP' => $entrega['codigo_postal'],
                    ':Ciudad' => $entrega['ciudad'],
                    ':Entidad' => $entrega['entidad'],
                    ':Referencias' => $entrega['referencias'],
                ]);
                $ID_Pedido = (int) $connection->lastInsertId();

                $detalle = $connection->prepare('INSERT INTO Detalle_Pedido (ID_Pedido, ID_Producto, Cantidad, Precio_Unitario, Importe)
                    VALUES (:Pedido, :Producto, :Cantidad, :Precio, :Importe)');
                foreach ($revision['lineas'] as $linea) {
                    $detalle->execute([
                        ':Pedido' => $ID_Pedido,
                        ':Producto' => $linea['id'],
                        ':Cantidad' => $linea['cantidad'],
                        ':Precio' => $linea['precio'],
                        ':Importe' => $linea['importe'],
                    ]);
                }
                $connection->commit();
                return [$folio, true];

            } catch (PDOException $e) {
                $connection->rollBack();
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // Dos envíos con la misma clave al mismo tiempo: el otro ya creó el pedido
                $existente = self::folioPorClave($ID_Cliente, $clave);
                if ($existente !== null) {
                    return [$existente, false];
                }
                // Folio repetido (muy poco probable): se intenta con otro
                if ($intento >= 3) {
                    throw $e;
                }
            }
        }
    }//-- Fin funcion crear


    /**
     * Folio del pedido que ya se creó con esa clave. El mismo envío repetido (doble clic, P12)
     * regresa ese pedido en lugar de crear otro.
     *
     * @param int    $ID_Cliente Cliente
     * @param string $clave      Clave de la confirmación
     * @return string|null El folio, o null si no hay pedido con esa clave
     * @throws PDOException Si falla la consulta a la base de datos
     */
    public static function folioPorClave(int $ID_Cliente, string $clave): ?string
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT Folio FROM Pedido WHERE ID_Cliente = :id AND Clave_Envio = :clave');
        $sql->execute([':id' => $ID_Cliente, ':clave' => $clave]);
        $folio = $sql->fetchColumn();
        return $folio === false ? null : $folio;
    }


    /**
     * Pedidos del cliente, del más reciente al más antiguo.
     *
     * @param int $ID_Cliente Cliente
     * @return array Lista de ['folio', 'fecha', 'hora', 'estado', 'total', 'productos', 'piezas']
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function listar(int $ID_Cliente): array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT p.Folio, p.Fecha, p.Hora, p.Estado, p.Total,
                COALESCE(SUM(d.Cantidad), 0) AS Piezas, COUNT(d.ID_Detalle_Pedido) AS Productos
            FROM Pedido p LEFT JOIN Detalle_Pedido d ON d.ID_Pedido = p.ID_Pedido
            WHERE p.ID_Cliente = :id
            GROUP BY p.ID_Pedido
            ORDER BY p.ID_Pedido DESC');
        $sql->execute([':id' => $ID_Cliente]);

        return array_map(fn($fila) => [
            'folio' => $fila['Folio'],
            'fecha' => $fila['Fecha'],
            'hora' => substr($fila['Hora'], 0, 5),
            'estado' => $fila['Estado'],
            'total' => (float) $fila['Total'],
            'productos' => (int) $fila['Productos'],
            'piezas' => (int) $fila['Piezas'],
        ], $sql->fetchAll());
    }//-- Fin funcion listar


    /**
     * Un pedido del cliente con sus productos, su resumen y sus datos de entrega.
     *
     * @param int    $ID_Cliente Cliente
     * @param string $folio      Folio del pedido
     * @return array|null El pedido, o null si no existe o no es de ese cliente
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function leer(int $ID_Cliente, string $folio): ?array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT * FROM Pedido WHERE ID_Cliente = :id AND Folio = :folio');
        $sql->execute([':id' => $ID_Cliente, ':folio' => $folio]);
        $pedido = $sql->fetch();
        if (!$pedido) {
            return null;
        }

        $sql = $connection->prepare('SELECT d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida, d.Cantidad, d.Precio_Unitario, d.Importe
            FROM Detalle_Pedido d INNER JOIN Producto p ON p.ID_Producto = d.ID_Producto
            WHERE d.ID_Pedido = :pedido ORDER BY d.ID_Detalle_Pedido');
        $sql->execute([':pedido' => $pedido['ID_Pedido']]);
        $lineas = array_map(fn($fila) => [
            'id' => (int) $fila['ID_Producto'],
            'nombre' => $fila['Nombre_Producto'],
            'unidad' => $fila['Unidad_Medida'],
            'cantidad' => (int) $fila['Cantidad'],
            'precio' => (float) $fila['Precio_Unitario'],
            'importe' => (float) $fila['Importe'],
        ], $sql->fetchAll());

        return [
            'folio' => $pedido['Folio'],
            'fecha' => $pedido['Fecha'],
            'hora' => substr($pedido['Hora'], 0, 5),
            'estado' => $pedido['Estado'],
            'lineas' => $lineas,
            'resumen' => [
                'productos' => count($lineas),
                'piezas' => array_sum(array_column($lineas, 'cantidad')),
                'subtotal' => (float) $pedido['Subtotal'],
                'iva' => (float) $pedido['IVA'],
                'envio' => 0,
                'total' => (float) $pedido['Total'],
            ],
            'entrega' => Entrega::desdeFila($pedido),
        ];
    }//-- Fin funcion leer
}
