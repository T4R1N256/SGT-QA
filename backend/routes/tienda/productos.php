<?php
// GET /api/tienda/productos: catálogo con búsqueda, filtros, orden y paginación (pública)
// Parámetros (todos opcionales):
//   q            texto a buscar en el nombre, la descripción o la categoría (sin distinguir acentos)
//   categoria    ID de la categoría
//   disponibles  1 para ocultar los agotados
//   orden        nombre (por defecto), precio_asc o precio_desc
//   pagina       desde 1; cada página tiene Catalogo::POR_PAGINA productos

require_once __DIR__ . '/../../models/catalogo.php';

$q = $_GET['q'] ?? '';
if (!is_string($q) || !preg_match('/^.{0,100}$/us', trim($q))) {
    throw new ErrorValidacion('La búsqueda debe ser un texto de 100 caracteres o menos');
}

$ID_Categoria = parametroEntero('categoria', 'La categoría');
$soloDisponibles = campoOpcion($_GET, 'disponibles', ['0', '1'], false) === '1';
$orden = campoOpcion($_GET, 'orden', array_keys(Catalogo::ORDENES), false) ?? 'nombre';
$pagina = parametroEntero('pagina', 'La página') ?? 1;

$respuesta = [];
if ($ID_Categoria !== null) {
    $respuesta['categoria'] = Catalogo::categoria($ID_Categoria) ?? throw new ErrorApi('Categoría no encontrada', 404);
}

[$productos, $total] = Catalogo::productos(trim($q), $ID_Categoria, $soloDisponibles, $orden, $pagina);

responder($respuesta + [
    'productos' => $productos,
    'total' => $total,
    'pagina' => $pagina,
    'paginas' => (int) ceil($total / Catalogo::POR_PAGINA),
    'por_pagina' => Catalogo::POR_PAGINA,
]);
