import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { TiendaProvider } from './TiendaContext'
import { CarritoProvider } from './carrito/CarritoContext'
import Carrito from './carrito/Carrito'
import { CategoriasProvider } from './catalogo/CategoriasContext'
import Cuenta from './cuenta/Cuenta'
import Ingresar from './cuenta/Ingresar'
import Registro from './cuenta/Registro'
import RequiereSesion from './cuenta/RequiereSesion'
import { SesionProvider } from './cuenta/SesionContext'
import Entrega from './compra/Entrega'
import Pago from './compra/Pago'
import Resumen from './compra/Resumen'
import Busqueda from './catalogo/Busqueda'
import Categoria from './catalogo/Categoria'
import Inicio from './catalogo/Inicio'
import Producto from './catalogo/Producto'
import Diseno from './componentes/Diseno'
import NoEncontrada from './componentes/NoEncontrada'

/**
 * Rutas de la tienda. Flujo: explorar → carrito → cuenta → entrega → resumen → pago (PLAN_TIENDA.md).
 * Los pasos de la compra piden sesión (D5); cada paso revisa además que el anterior esté hecho (P7).
 *
 * @returns {JSX.Element} La tienda
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
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
                  <Route element={<RequiereSesion />}>
                    <Route path="/compra/entrega" element={<Entrega />} />
                    <Route path="/compra/resumen" element={<Resumen />} />
                    <Route path="/compra/pago/:folio" element={<Pago />} />
                  </Route>
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
