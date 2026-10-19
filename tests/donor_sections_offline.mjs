import assert from 'node:assert/strict'
import { donorSections } from '../frontend/src/services/donorSections.js'
import { filterMatches } from '../frontend/src/services/matchFilters.js'
const ranked = [
  { match_id: 2, donor_reference: 'donor-2', display_name: 'Second', chapter_id: 1, status: 'NOTIFIED' },
  { match_id: 1, donor_reference: 'donor-1', display_name: 'First', chapter_id: 2, status: 'POTENTIAL' },
]
let checks = 0
const check = (actual, expected) => { assert.deepEqual(actual, expected); checks++ }
check(donorSections(ranked).suggestions.map(m => m.match_id), [2,1])
check(donorSections(ranked).offers, [])
const offered = { match_id: 2, donor_reference: 'donor-2', donor_name: 'Second', status: 'RESPONDED', offer_cycle: 1 }
let groups = donorSections(ranked, [offered])
check(groups.offers.map(m => m.match_id), [2])
check(groups.suggestions.map(m => m.match_id), [1])
check(groups.offers[0].chapter_id, 1)
groups = donorSections(ranked, [{ ...offered, context_id: 9, conversation_id: 7, status: 'CLOSED' }])
check(groups.offers[0].conversation_id, 7)
check(groups.suggestions.map(m => m.match_id), [1])
check(donorSections([{ ...ranked[0], status: 'COMPLETED' }]).offers.length, 1)
check(donorSections([{ ...ranked[0], status: 'RESPONDED' }]).suggestions.length, 0)
check(filterMatches(groups.offers, { search: 'second', status: 'CLOSED', chapter: '1' }).length, 1)
check(donorSections([...ranked, { ...ranked[0], match_id: 3 }], [offered]).suggestions.map(m => m.match_id), [1])
console.log(`${checks} donor-section offline checks passed; no HTTP or database.`)
