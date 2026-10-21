import { focusManager, onlineManager } from '@tanstack/react-query'
import { api } from './apiClient'

export const demandAccessError = error => [401, 403].includes(error?.status)
const deniedSessions = new WeakMap()
export const demandDeniedSession = error => error && deniedSessions.get(error)
export const canReadDemand = user => Boolean(user?.id && user.account_status === 'active' && ['member', 'admin'].includes(user.role))

export function chapterDemandOptions(user, { bloodType = 'All', urgency = 'All', days = '' } = {}) {
  const params = new URLSearchParams()
  if (bloodType !== 'All') params.set('blood_type', bloodType)
  if (urgency !== 'All') params.set('urgency', urgency)
  if (days) params.set('days', String(days))
  return {
    queryKey: ['chapter-demand', user?.id ?? null, user?.role ?? null, user?.account_status ?? null, params.toString()],
    queryFn: async ({ signal }) => {
      try {
        const result = await api.get(`/api/demand-map?${params}`, { signal })
        return result.chapters || []
      } catch (error) {
        if (demandAccessError(error)) deniedSessions.set(error, user)
        throw error
      }
    },
    enabled: query => canReadDemand(user) && !demandAccessError(query.state.error),
    staleTime: 0,
    refetchOnMount: 'always',
    refetchOnWindowFocus: query => !demandAccessError(query.state.error) && 'always',
    refetchOnReconnect: query => !demandAccessError(query.state.error) && 'always',
    refetchInterval: query => demandAccessError(query.state.error) || !onlineManager.isOnline() || !focusManager.isFocused() ? false : 30000,
    refetchIntervalInBackground: false,
    retry: false,
  }
}
