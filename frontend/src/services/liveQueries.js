// Shared visible-tab refresh policy for operational views.
export const liveQueryOptions = {
  staleTime: 0,
  refetchOnMount: 'always',
  refetchOnWindowFocus: 'always',
  refetchOnReconnect: 'always',
  refetchInterval: 10000,
  refetchIntervalInBackground: false,
  retry: false,
}

export function invalidateDonationViews(client) {
  for (const key of ['donation-reports', 'donation-chat-contexts', 'confirmed-donation-summary', 'admin-dashboard', 'my-requests', 'my-responses', 'request-matches', 'home-feed']) {
    client.invalidateQueries({ queryKey: [key] })
  }
}
