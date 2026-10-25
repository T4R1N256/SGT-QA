<?php
// GET /api/tienda/destacados: un producto disponible de cada categoría, para el inicio (pública)

require_once __DIR__ . '/../../models/catalogo.php';

responder(['productos' => Catalogo::destacados()]);
