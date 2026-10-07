import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import RequestFormModal from '../components/RequestFormModal'

const RequestCreationContext = createContext(null)

export function RequestCreationProvider({ children }) {
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const openCreateRequest = useCallback(() => setOpen(true), [])
  const close = useCallback(() => setOpen(false), [])
  const handleCreated = (result) => {
    setOpen(false)
    queryClient.invalidateQueries({ queryKey: ['my-requests'] })
    queryClient.invalidateQueries({ queryKey: ['profile'] })
    queryClient.invalidateQueries({ queryKey: ['home-feed'] })
    navigate(`/requests/${result.request.id}/matches`, { state: { requestCreated: true } })
  }
  return (
    <RequestCreationContext.Provider value={openCreateRequest}>
      {children}
      <RequestFormModal open={open} onClose={close} onSuccess={handleCreated} />
    </RequestCreationContext.Provider>
  )
}

export function useRequestCreation() {
  return useContext(RequestCreationContext)
}

// Compatibility for bookmarked create links; normal actions open in place.
export function CreateRequestEntry() {
  const openCreateRequest = useRequestCreation()
  const navigate = useNavigate()
  useEffect(() => {
    navigate('/matches', { replace: true })
    openCreateRequest()
  }, [navigate, openCreateRequest])
  return null
}
