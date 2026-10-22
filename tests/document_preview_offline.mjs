import assert from 'node:assert/strict'
import {loadDocumentPreview} from '../frontend/src/services/documentPreview.js'
import {documentLabel} from '../frontend/src/services/documentLabels.js'
const file={id:123,mime_type:'image/png'}
const signal=new AbortController().signal
let calls=0
const blob=new Blob(['synthetic image'],{type:'image/png'})
const loaded=await loadDocumentPreview(file,signal,async(path,options)=>{
  calls++; assert.equal(path,'/api/admin/documents/123/file'); assert.equal(options.credentials,'same-origin'); assert.equal(options.cache,'no-store'); assert.equal(options.redirect,'error'); assert.equal(options.signal,signal)
  return {ok:true,headers:new Headers({'content-type':'image/png'}),blob:async()=>blob}
})
assert.equal(loaded,blob)
assert.equal(documentLabel({doc_type:'national_id',document_side:'front'}),'National ID — front')
assert.equal(documentLabel({doc_type:'national_id',document_side:'back'}),'National ID — back')
assert.equal(documentLabel({doc_type:'national_id'}),'National ID (side not specified)')
await loadDocumentPreview(file,signal,async(path)=>{
  assert.equal(path,'/api/profile/documents/123/file')
  return {ok:true,headers:new Headers({'content-type':'image/png'}),blob:async()=>blob}
},true)
await assert.rejects(loadDocumentPreview({...file,mime_type:'text/html'},signal,()=>{throw Error('Must not fetch')}),/unavailable/)
await assert.rejects(loadDocumentPreview({...file,id:'../123'},signal,()=>{throw Error('Must not fetch')}),/unavailable/)
await assert.rejects(loadDocumentPreview(file,signal,async()=>({ok:false})),/Check your access/)
await assert.rejects(loadDocumentPreview(file,signal,async()=>({ok:true,headers:new Headers({'content-type':'text/html'})})),/could not be verified/)
assert.equal(calls,1)
console.log('PASS private document fetch options, format rejection and authorization failures; fake fetch only.')
