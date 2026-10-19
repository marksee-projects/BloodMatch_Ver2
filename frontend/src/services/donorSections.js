// Preserve server ranking; actual/accepted offers take precedence over suggestions.
export function donorSections(matches = [], offers = []) {
  const byMatch = new Map(matches.map(m => [String(m.match_id), m]))
  const actual = new Map(offers.map(o => [String(o.match_id), { ...byMatch.get(String(o.match_id)), ...o, display_name: o.donor_name }]))
  for (const m of matches) if (['RESPONDED', 'COMPLETED'].includes(m.status) && !actual.has(String(m.match_id))) actual.set(String(m.match_id), m)
  const offerDonors = new Set([...actual.values()].map(m => m.donor_reference).filter(Boolean))
  const ordered = matches.filter(m => actual.has(String(m.match_id))).map(m => actual.get(String(m.match_id)))
  const rankedIds = new Set(ordered.map(m => String(m.match_id)))
  ordered.push(...[...actual.values()].filter(m => !rankedIds.has(String(m.match_id))))
  return { offers: ordered, suggestions: matches.filter(m => ['POTENTIAL', 'NOTIFIED'].includes(m.status) && !actual.has(String(m.match_id)) && !offerDonors.has(m.donor_reference)) }
}

export function donorPage(rows, requestedPage = 1, pageSize = 5) {
  const totalPages = Math.max(1, Math.ceil(rows.length / pageSize))
  const page = Math.min(totalPages, Math.max(1, requestedPage))
  const start = (page - 1) * pageSize
  return { rows: rows.slice(start, start + pageSize), page, totalPages, start: rows.length ? start + 1 : 0, end: Math.min(start + pageSize, rows.length) }
}
