<?php
// GET /api/tienda/categorias: categorías con productos a la venta y cuántos tiene cada una (pública)

require_once __DIR__ . '/../../models/catalogo.php';

responder(['categorias' => Catalogo::categorias()]);
