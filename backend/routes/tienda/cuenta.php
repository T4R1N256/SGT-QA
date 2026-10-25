<?php
// GET /api/tienda/cuenta: el cliente con la sesión iniciada, o null si no hay sesión
// (la tienda la pide al cargar para saber si mostrar "Ingresar" o la cuenta)

responder(['cliente' => clienteActual()]);
