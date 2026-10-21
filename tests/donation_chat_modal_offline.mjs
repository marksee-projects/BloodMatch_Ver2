// Render real modal/card components with mocked auth/query/API; no network or database.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync, existsSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const require=createRequire(new URL('../frontend/package.json',import.meta.url))
const { build }=require('esbuild')
const React=require('react')
const { renderToStaticMarkup }=require('react-dom/server')
const root=fileURLToPath(new URL('../frontend/',import.meta.url))
const context={id:9,match_id:42,request_id:91,donor_id:2,required_blood_type:'A+',facility_name:'Test facility',quantity_units:1,request_status:'OPEN',active:true,offer_cycle:1,current_cycle:1,coordination:[],report:null,report_in_context:true}
const calls=[]
const fixture={contexts:[context],auth:{user:{id:2},refresh:async()=>{}},hold:null}
const offlineApi={post:async(path,body)=>{calls.push({path,body});return {changed:true}},postForm:async(path,body)=>{calls.push({path,body});return new Promise(resolve=>{fixture.hold=resolve})}}
const bundle=await build({absWorkingDir:root,entryPoints:['src/components/DonationContextCard.jsx'],bundle:true,write:false,platform:'node',format:'cjs',packages:'external',jsx:'automatic',plugins:[{name:'offline',setup(b){
  b.onResolve({filter:/.*/},args=>{
    if(!args.path.startsWith('.')&&!isAbsolute(args.path))return {path:args.path,external:true}
    const path=resolve(args.importer?dirname(args.importer):root,args.path)
    const found=[path,`${path}.jsx`,`${path}.js`].find(existsSync)
    return {path:found,namespace:'source'}
  })
  b.onLoad({filter:/AuthContext\.jsx$/},()=>({contents:'export const useAuth=()=>fixture.auth',loader:'js'}))
  b.onLoad({filter:/apiClient\.js$/},()=>({contents:'export const api=offlineApi',loader:'js'}))
  b.onLoad({filter:/\.css$/},()=>({contents:'export default new Proxy({}, {get:(_,k)=>k})',loader:'js'}))
  b.onLoad({filter:/.*/,namespace:'source'},args=>({contents:readFileSync(args.path,'utf8').replace('function ContextWorkspace(','export function ContextWorkspace('),loader:extname(args.path)==='.jsx'?'jsx':'js'}))
}}]})
const module={exports:{}}
const icons=new Proxy({}, {get:()=>()=>React.createElement('svg')})
new Function('require','module','exports','fixture','offlineApi',bundle.outputFiles[0].text)(id=>id==='@phosphor-icons/react'?icons:id==='react-router-dom'?{useLocation:()=>({search:'?request=91'}),Link:({to,children,...props})=>React.createElement('a',{href:to,...props},children)}:id==='@tanstack/react-query'?{useQuery:()=>({data:{contexts:fixture.contexts},refetch:async()=>{}}),useQueryClient:()=>({invalidateQueries:()=>{}})}:require(id),module,module.exports,fixture,offlineApi)
const originalState=React.useState,originalRef=React.useRef
let states=[],refs=[],stateIndex=0,refIndex=0,inWorkspace=false,tree
React.useState=initial=>{
  const actual=originalState(initial)
  if(!inWorkspace)return actual
  const index=stateIndex++;if(!(index in states))states[index]=initial
  return [states[index],next=>{states[index]=typeof next==='function'?next(states[index]):next}]
}
React.useRef=initial=>{
  const actual=originalRef(initial)
  if(!inWorkspace)return actual
  const index=refIndex++;if(!(index in refs))refs[index]=actual
  return refs[index]
}
function Render(){stateIndex=0;refIndex=0;inWorkspace=true;try{tree=module.exports.ContextWorkspace({conversationId:24});return tree}finally{inWorkspace=false}}
const render=()=>renderToStaticMarkup(React.createElement(Render))
function find(node,predicate){if(Array.isArray(node))return node.map(n=>find(n,predicate)).find(Boolean);if(!node||typeof node!=='object')return;return predicate(node)?node:find(node.props?.children,predicate)}
const button=label=>find(tree,n=>n.props?.children===label)
const dialog=()=>find(tree,n=>n.props?.dialogRole==='dialog')
let checks=0
function check(ok,label){assert(ok,label);checks++}
const tick=()=>new Promise(resolve=>setImmediate(resolve))
try{
  let html=render()
  check(html.includes('Report donation')&&!html.includes('role="dialog"')&&!html.includes('<form'),'Closed chat reminder contains no form or modal')
  check(!html.includes('I donated')&&!html.includes('<select'),'Actions and selector do not consume chat space')
  button('Report donation').props.onClick();html=render()
  check(html.includes('role="dialog"')&&html.includes('aria-modal="true"'),'Report donation opens the real accessible dialog')
  check(html.includes('<form')&&html.includes('Request coordination'),'Report donation opens the donor form and exact context selector directly')
  check(!html.includes('Request #')&&html.includes('Report completed donation'),'Context uses meaningful labels without visible request IDs')
  fixture.contexts=[context,{...context,id:10,match_id:43,request_id:92}];render()
  find(tree,n=>n.type==='select').props.onChange({target:{value:'10'}});html=render()
  check(html.includes('<form')&&String(find(tree,n=>n.type==='select').props.value)==='10','Switching to another donor context keeps the report form available')
  find(tree,n=>n.type==='select').props.onChange({target:{value:'9'}});fixture.contexts=[context];render()
  check(calls.length===0,'Opening a dialog creates no report, signal or acknowledgment')
  html=render()
  check(html.includes('Choose donation date')&&html.includes('Choose donation time'),'Form retains explicit picker buttons')
  check(html.includes('Donation time</label>')&&!html.includes('Philippine time')&&!html.includes('UTC+08:00')&&html.includes('step="60"'),'Time label is concise and picker uses minutes without seconds')
  check(html.includes('Upload donation proof'),'Optional private proof input remains')
  find(tree,n=>n.type?.name==='DonationDateTimeFields').props.onChange('2020-01-01T12:00')
  render()
  const form=find(tree,n=>n.type==='form')
  form.props.onSubmit({preventDefault(){}});form.props.onSubmit({preventDefault(){}})
  await tick();html=render()
  check(calls.length===1&&calls[0].path==='/api/donation-completion','Submission reuses canonical endpoint and rejects rapid repeats')
  check(calls[0].body.get('match_id')==='42'&&calls[0].body.get('context_id')==='9','Submitted details remain bound to exact match and accepted context')
  dialog().props.onCancel();html=render()
  check(html.includes('role="dialog"')&&html.includes('aria-busy="true"'),'Closing cannot discard an in-flight submission')
  fixture.hold({changed:true});await tick();html=render()
  check(html.includes('Report submitted.')&&!html.includes('<form'),'Success feedback remains in modal and clears submitted form')
  dialog().props.onCancel();html=render()
  check(!html.includes('role="dialog"'),'Close restores compact chat view')
  button('Report donation').props.onClick();render()
  find(tree,n=>n.type?.name==='DonationDateTimeFields').props.onChange('2099-01-01T12:00')
  render();find(tree,n=>n.type==='form').props.onSubmit({preventDefault(){}});await tick();html=render()
  check(html.includes('already passed')&&html.includes('role="alert"')&&html.includes('<form'),'Future-time rejection is visible inside modal without losing form')
  check(calls.length===1&&html.includes('value="2099-01-01"'),'Rejected client validation preserves date and performs no submission')
  dialog().props.onCancel();render();button('Report donation').props.onClick();html=render()
  check(html.includes('value="2099-01-01"'),'Closing and reopening retains the unsent draft')
  fixture.contexts=[{...context,id:10,request_id:92},context];render()
  check(String(find(tree,n=>n.type==='select').props.value)==='9','Polling a new request context cannot silently retarget an open draft')
  fixture.contexts=[context]
  fixture.auth.user={id:1};states=[];refs=[]
  context.report={id:5,status:'PENDING',content_version:4,workflow_state:'awaiting_requester',requester_response:null,offer_context_id:9}
  render();button('Report donation').props.onClick();html=render()
  check(html.includes('Donation received')&&html.includes('Did not donate')&&html.includes('Not sure')&&!html.includes('>I donated<'),'Requester retains all three acknowledgment choices')
  button('Donation received').props.onClick();await tick()
  check(calls.at(-1).path==='/api/donation-completion/5/acknowledge'&&calls.at(-1).body.expected_version===4,'Acknowledgment remains bound to canonical report version')
  context.report_in_context=false;html=render()
  check(!html.includes('>Donation received<'),'Other offer-cycle report cannot be acknowledged through this card')
  console.log(`${checks} chat donation-modal checks passed; API mocked, no HTTP/database/files/SMTP.`)
}finally{React.useState=originalState;React.useRef=originalRef}
