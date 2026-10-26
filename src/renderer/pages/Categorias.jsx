import { useState, useRef } from 'react'
import {
  getTodasCategorias,
  getCategoria,
  createCategoria,
  modifyCategoria,
  deleteCategoria,
} from '../api/api'
import { useFetch } from '../hooks/useFetch'
import { ActionButton, ConfirmModal, FormModal, FormAlert, fieldInputClass } from '../components'
import {
  avisoDesactivarCategoria,
  errorDelApi,
  parseCategoria,
  validateCategoria,
} from '../utils/catalogos'
import { useHeader } from '../context/HeaderContext.jsx'
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/react/20/solid'

const capitalize = (v) => (v ? v.charAt(0).toUpperCase() + v.slice(1) : '—')

const TABLE_COLUMNS = [
  { label: 'ID', field: 'ID_Categoria' },
  { label: 'NOMBRE', field: 'Nombre_Categoria', grow: true },
  { label: 'PRODUCTOS', field: 'Productos' },
  { label: 'ESTADO', field: 'Estado', format: capitalize },
]

const ESTADO_OPTIONS = [
  { value: 'activo', label: 'Activo' },
  { value: 'inactivo', label: 'Inactivo' },
]

const EMPTY_CATEGORIA = { nombre: '', estado: 'activo' }

// El API marca el nombre cuando ya existe otra categoría con ese nombre
const CAMPOS_API = { Nombre_Categoria: 'nombre' }

function buildCategoriaPayload(parsed) {
  return {
    Nombre_Categoria: parsed.nombre,
    Estado: parsed.estado,
  }
}

function categoriaToForm(c) {
  return {
    nombre: c.Nombre_Categoria ?? '',
    estado: c.Estado ?? 'activo',
  }
}

function CategoriaForm({ data, setData, response, setResponse, mode, aviso }) {
  function onChange(field) {
    return (e) => {
      setResponse(null)
      setData((prev) => ({ ...prev, [field]: e.target.value }))
    }
  }
  const cls = (field) => fieldInputClass(response, field)
  return (
    <div className="flex flex-col gap-3">
      <FormAlert response={response} />
      <div>
        <label className="block text-xs font-semibold text-gray-700 mb-1">
          Nombre de la categoría *
        </label>
        <input
          className={cls('nombre')}
          type="text"
          placeholder="Nombre de la categoría"
          value={data.nombre}
          onChange={onChange('nombre')}
          maxLength={50}
        />
      </div>
      {mode === 'edit' && (
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Estado *</label>
          <select className={cls('estado')} value={data.estado} onChange={onChange('estado')}>
            {ESTADO_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
          {aviso && (
            <p
              role="alert"
              className="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-300 rounded-lg px-3 py-2"
            >
              {aviso}
            </p>
          )}
        </div>
      )}
    </div>
  )
}

/**
 * Categorías (solo admin): alta, edición, activación y desactivación. No acepta nombres repetidos
 * y, si la categoría tiene productos activos, pide confirmar antes de desactivarla.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Categorias() {
  const {
    data: categoriasData,
    loading: loadingCategorias,
    refetch: refetchCategorias,
  } = useFetch(getTodasCategorias)

  const categorias = categoriasData?.categorias ?? []

  const [selectedId, setSelectedId] = useState(null)
  const [searchQuery, setSearchQuery] = useState('')

  const [newCategoria, setNewCategoria] = useState(EMPTY_CATEGORIA)
  const [creating, setCreating] = useState(false)
  const [createResponse, setCreateResponse] = useState(null)

  const [editCategoria, setEditCategoria] = useState(EMPTY_CATEGORIA)
  const [originalEdit, setOriginalEdit] = useState(EMPTY_CATEGORIA)
  const [editing, setEditing] = useState(false)
  const [editResponse, setEditResponse] = useState(null)
  const [loadingEdit, setLoadingEdit] = useState(false)
  // Productos activos que se avisaron al desactivar desde Editar: el siguiente guardado ya va confirmado
  const [editAviso, setEditAviso] = useState(null)

  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState(null)
  // Productos activos que muestra el aviso de Desactivar (de la lista o, si cambiaron, de la respuesta del API)
  const [productosAviso, setProductosAviso] = useState(0)

  const newModalRef = useRef(null)
  const editModalRef = useRef(null)
  const deleteModalRef = useRef(null)

  const selected = categorias.find((c) => c.ID_Categoria === selectedId) ?? null

  useHeader(
    <>
      <span className="text-lg text-gray-400 font-normal">({categorias.length})</span>
      <input
        type="text"
        value={searchQuery}
        onChange={(e) => setSearchQuery(e.target.value)}
        placeholder="Buscar categoría..."
        className="w-56 px-3 py-1.5 rounded-lg border border-gray-600 bg-gray-700 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-400"
      />
      <ActionButton
        onClick={() => {
          setNewCategoria(EMPTY_CATEGORIA)
          setCreateResponse(null)
          newModalRef.current.showModal()
        }}
        className="w-auto text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 rounded-lg gap-1.5"
      >
        <PlusIcon className="w-4 h-4 shrink-0" />
        Nueva categoría
      </ActionButton>
    </>
  )

  const filtered = categorias.filter((c) =>
    (c.Nombre_Categoria ?? '').toLowerCase().includes(searchQuery.toLowerCase())
  )

  const editChanged = Object.keys(editCategoria).some((k) => editCategoria[k] !== originalEdit[k])
  const editDesactiva = originalEdit.estado === 'activo' && editCategoria.estado === 'inactivo'

  async function openEditModal() {
    setLoadingEdit(true)
    setEditResponse(null)
    const res = await getCategoria(selectedId)
    setLoadingEdit(false)
    if (!res.ok) {
      setEditResponse({ type: 'error', message: res.mensaje })
      return
    }
    const loaded = categoriaToForm(res.categoria)
    setEditCategoria(loaded)
    setOriginalEdit(loaded)
    setEditAviso(null)
    editModalRef.current.showModal()
  }

  async function handleCreate() {
    const parsed = parseCategoria(newCategoria)
    const err = validateCategoria(parsed)
    if (err) return setCreateResponse({ type: 'error', ...err })

    setCreating(true)
    const res = await createCategoria(buildCategoriaPayload(parsed))
    setCreating(false)
    if (res.ok) {
      newModalRef.current.close()
      setNewCategoria(EMPTY_CATEGORIA)
      setCreateResponse(null)
      refetchCategorias()
    } else {
      setCreateResponse(errorDelApi(res, CAMPOS_API, 'Error al crear la categoría'))
    }
  }

  async function handleUpdate() {
    const parsed = parseCategoria(editCategoria)
    const err = validateCategoria(parsed)
    if (err) return setEditResponse({ type: 'error', ...err })

    // Desactivar una categoría con productos activos se confirma: el primer intento muestra el aviso del API
    const confirmado = editDesactiva && editAviso !== null
    setEditing(true)
    const res = await modifyCategoria(selectedId, buildCategoriaPayload(parsed), confirmado)
    setEditing(false)
    if (res.ok) {
      editModalRef.current.close()
      setEditResponse(null)
      setEditAviso(null)
      refetchCategorias()
    } else if (res.requiere_confirmacion) {
      setEditAviso(res.productos)
      setEditResponse(null)
    } else {
      setEditResponse(errorDelApi(res, CAMPOS_API, 'Error al actualizar la categoría'))
    }
  }

  // El aviso ya dice cuántos productos activos tiene: confirmar el diálogo confirma la desactivación.
  // Si mientras tanto cambiaron, el API la bloquea y el aviso se actualiza para confirmar otra vez
  async function handleDeleteConfirm() {
    setDeleting(true)
    setDeleteError(null)
    const res = await deleteCategoria(selectedId, productosAviso > 0)
    setDeleting(false)
    if (res.ok) {
      deleteModalRef.current.close()
      setSelectedId(null)
      refetchCategorias()
    } else if (res.requiere_confirmacion) {
      setProductosAviso(res.productos)
      setDeleteError(
        'Los productos de la categoría cambiaron. Revisa el aviso y confirma otra vez.'
      )
    } else {
      setDeleteError(res.mensaje ?? 'Error al desactivar la categoría')
    }
  }

  const canDelete = !!selected && selected.Estado !== 'inactivo'

  return (
    <div
      className="flex flex-col gap-4 w-full h-full border-none"
      onClick={() => setSelectedId(null)}
    >
      <ConfirmModal
        ref={deleteModalRef}
        title="Desactivar categoría"
        busy={deleting}
        busyLabel="Desactivando..."
        confirmLabel={productosAviso > 0 ? 'Desactivar de todas formas' : 'Desactivar'}
        error={deleteError}
        onConfirm={handleDeleteConfirm}
        onClose={() => setDeleteError(null)}
      >
        {avisoDesactivarCategoria(selected?.Nombre_Categoria, productosAviso)}
      </ConfirmModal>

      <FormModal
        ref={newModalRef}
        title="Nueva categoría"
        busy={creating}
        submitLabel="Guardar categoría"
        submitClass="bg-emerald-600 hover:bg-emerald-700"
        onSubmit={handleCreate}
        onClose={() => {
          setNewCategoria(EMPTY_CATEGORIA)
          setCreateResponse(null)
        }}
      >
        <CategoriaForm
          data={newCategoria}
          setData={setNewCategoria}
          response={createResponse}
          setResponse={setCreateResponse}
          mode="create"
        />
      </FormModal>

      <FormModal
        ref={editModalRef}
        title="Editar categoría"
        busy={editing}
        canSubmit={editChanged && !loadingEdit}
        submitLabel={
          editDesactiva && editAviso !== null ? 'Desactivar de todas formas' : 'Guardar cambios'
        }
        onSubmit={handleUpdate}
        onClose={() => {
          setEditCategoria(EMPTY_CATEGORIA)
          setOriginalEdit(EMPTY_CATEGORIA)
          setEditResponse(null)
          setEditAviso(null)
        }}
      >
        {loadingEdit ? (
          <p className="text-sm text-gray-500 py-4 text-center">Cargando...</p>
        ) : (
          <CategoriaForm
            data={editCategoria}
            setData={(cambio) => {
              // Si cambia algo, el aviso se vuelve a pedir al guardar
              setEditAviso(null)
              setEditCategoria(cambio)
            }}
            response={editResponse}
            setResponse={setEditResponse}
            mode="edit"
            aviso={
              editDesactiva && editAviso !== null
                ? avisoDesactivarCategoria(originalEdit.nombre, editAviso)
                : null
            }
          />
        )}
      </FormModal>

      <div className="flex flex-col w-full h-full border border-gray-300 bg-white shadow rounded-lg overflow-hidden">
        <CategoriaTableHeader />
        <div className="flex flex-col w-full overflow-y-auto flex-1">
          {loadingCategorias ? (
            <p className="w-full text-center text-gray-600 py-4">Cargando categorías...</p>
          ) : filtered.length === 0 ? (
            <p className="w-full text-center text-gray-600 py-4">Sin categorías</p>
          ) : (
            <ul>
              {filtered.map((c) => (
                <CategoriaTableRow
                  key={c.ID_Categoria}
                  categoria={c}
                  selected={c.ID_Categoria === selectedId}
                  onSelect={setSelectedId}
                />
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6" onClick={(e) => e.stopPropagation()}>
        <ActionButton
          className="font-normal rounded-lg"
          onClick={openEditModal}
          disabled={!selectedId}
        >
          <span className="flex items-center gap-2 justify-center">
            <PencilSquareIcon className="w-4 h-4" />
            Editar seleccionada
          </span>
        </ActionButton>
        <ActionButton
          className="font-normal rounded-lg"
          onClick={() => {
            setDeleteError(null)
            setProductosAviso(selected?.Productos ?? 0)
            deleteModalRef.current.showModal()
          }}
          disabled={!canDelete}
        >
          <span className="flex items-center gap-2 justify-center">
            <TrashIcon className="w-4 h-4" />
            Desactivar seleccionada
          </span>
        </ActionButton>
      </div>
    </div>
  )
}

const headerCell = 'py-2 text-xs text-center border-b-2 border-gray-300 text-white'
const rowCell = 'py-2 text-xs text-center text-gray-700 truncate px-1'

function CategoriaTableHeader() {
  return (
    <div className="w-full h-fit bg-gray-800 grid grid-cols-5">
      {TABLE_COLUMNS.map(({ label, grow }) => (
        <div key={label} className={`${headerCell} ${grow ? 'col-span-2' : ''}`}>
          {label}
        </div>
      ))}
    </div>
  )
}

function CategoriaTableRow({ categoria, selected, onSelect }) {
  const isInactive = categoria.Estado === 'inactivo'
  return (
    <li
      onClick={(e) => {
        e.stopPropagation()
        onSelect(categoria.ID_Categoria)
      }}
      className={`grid grid-cols-5 cursor-pointer border-b border-gray-100 hover:bg-gray-50 ${
        selected ? 'bg-gray-100' : ''
      } ${isInactive ? 'opacity-60' : ''}`}
    >
      {TABLE_COLUMNS.map(({ field, grow, format }) => (
        <span key={field} className={`${rowCell} ${grow ? 'col-span-2' : ''}`}>
          {format ? format(categoria[field]) : (categoria[field] ?? '—')}
        </span>
      ))}
    </li>
  )
}
