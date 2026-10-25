import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { TiendaProvider } from './TiendaContext'
import { CarritoProvider } from './carrito/CarritoContext'
import Carrito from './carrito/Carrito'
import { CategoriasProvider } from './catalogo/CategoriasContext'
import Cuenta from './cuenta/Cuenta'
import Ingresar from './cuenta/Ingresar'
import Registro from './cuenta/Registro'
import { SesionProvider } from './cuenta/SesionContext'
import Busqueda from './catalogo/Busqueda'
import Categoria from './catalogo/Categoria'
import Inicio from './catalogo/Inicio'
import Producto from './catalogo/Producto'
import Diseno from './componentes/Diseno'
import NoEncontrada from './componentes/NoEncontrada'
import Proximamente from './componentes/Proximamente'

// Rutas de la tienda. Flujo: explorar → carrito → cuenta → entrega → resumen → pago (PLAN_TIENDA.md)
export default function App() {
  return (
    <TiendaProvider>
      <SesionProvider>
        <CarritoProvider>
          <CategoriasProvider>
            <BrowserRouter>
              <Routes>
                <Route element={<Diseno />}>
                  <Route path="/" element={<Inicio />} />
                  <Route path="/categoria/:id" element={<Categoria />} />
                  <Route path="/buscar" element={<Busqueda />} />
                  <Route path="/producto/:id" element={<Producto />} />
                  <Route path="/carrito" element={<Carrito />} />
                  <Route path="/ingresar" element={<Ingresar />} />
                  <Route path="/registro" element={<Registro />} />
                  <Route path="/cuenta" element={<Cuenta />} />
                  <Route
                    path="/compra/entrega"
                    element={<Proximamente titulo="Datos de entrega" fase="T4 (compra)" />}
                  />
                  <Route
                    path="/compra/resumen"
                    element={<Proximamente titulo="Resumen de la compra" fase="T4 (compra)" />}
                  />
                  <Route
                    path="/compra/pago/:folio"
                    element={<Proximamente titulo="Pago" fase="T4 (compra)" />}
                  />
                  <Route path="*" element={<NoEncontrada />} />
                </Route>
              </Routes>
            </BrowserRouter>
          </CategoriasProvider>
        </CarritoProvider>
      </SesionProvider>
    </TiendaProvider>
  )
}
