export const emptyMatchFilters = { search: '', status: 'all', chapter: 'all' }

export function filterMatches(matches, filters) {
  const search = filters.search.trim().toLocaleLowerCase()
  return matches.filter(match => {
    const chapter = match.chapter_id == null ? 'unassigned' : String(match.chapter_id)
    const statusMatches = filters.status === 'all'
      || (filters.status === 'awaiting' ? ['POTENTIAL', 'NOTIFIED'].includes(match.status) : match.status === filters.status)
    return String(match.display_name || '').toLocaleLowerCase().includes(search)
      && statusMatches && (filters.chapter === 'all' || filters.chapter === chapter)
  })
}

export function matchChapterOptions(matches) {
  const chapters = new Map()
  for (const match of matches) {
    const value = match.chapter_id == null ? 'unassigned' : String(match.chapter_id)
    chapters.set(value, { value, label: match.chapter_name || 'Not assigned' })
  }
  return [...chapters.values()].sort((a, b) => a.label.localeCompare(b.label))
}
