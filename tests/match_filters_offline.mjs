import assert from 'node:assert/strict'
import { emptyMatchFilters, filterMatches, matchChapterOptions } from '../frontend/src/services/matchFilters.js'
const matches = [
  { match_id: 1, display_name: 'Alex Member', status: 'NOTIFIED', chapter_id: 1, chapter_name: 'First chapter' },
  { match_id: 2, display_name: 'Bea Member', status: 'RESPONDED', chapter_id: 2, chapter_name: 'Second chapter' },
  { match_id: 3, display_name: 'Alex Donor', status: 'POTENTIAL', chapter_id: 1, chapter_name: 'First chapter' },
  { match_id: 4, display_name: 'Completed donor', status: 'COMPLETED', chapter_id: null, chapter_name: null }
]
const before = JSON.stringify(matches)
const ids = filters => filterMatches(matches, { ...emptyMatchFilters, ...filters }).map(match => match.match_id)
assert.deepEqual(ids({}), [1, 2, 3, 4])
assert.deepEqual(ids({ search: ' ALEX ' }), [1, 3])
assert.deepEqual(ids({ status: 'RESPONDED' }), [2])
assert.deepEqual(ids({ status: 'awaiting' }), [1, 3])
assert.deepEqual(ids({ status: 'COMPLETED' }), [4])
assert.deepEqual(ids({ chapter: '1' }), [1, 3])
assert.deepEqual(ids({ chapter: 'unassigned' }), [4])
assert.deepEqual(ids({ search: 'member', status: 'RESPONDED', chapter: '2' }), [2])
assert.deepEqual(ids({ search: 'missing' }), [])
assert.deepEqual(filterMatches([], emptyMatchFilters), [])
assert.equal(matchChapterOptions(matches).length, 3)
assert.deepEqual(matchChapterOptions(matches).find(chapter => chapter.value === 'unassigned'), { value: 'unassigned', label: 'Not assigned' })
assert.equal(JSON.stringify(matches), before)
console.log('PASS 13 offline donor filter checks; source ordering unchanged, no network or database.')
