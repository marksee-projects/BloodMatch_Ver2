// Actual Home callbacks and request construction; synthetic transport, no browser/DB mutation.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync, existsSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const require=createRequire(new URL('../frontend/package.json',import.meta.url))
const {build}=require('esbuild'),React=require('react'),{renderToStaticMarkup}=require('react-dom/server')
const root=fileURLToPath(new URL('../frontend/',import.meta.url))
const fixture={search:'',calls:[],options:null}
const api={get:async(path,options)=>{fixture.calls.push({path,options});return {requests:[]}}}
const bundle=await build({absWorkingDir:root,entryPoints:['src/pages/HomeFeedPage.jsx'],bundle:true,write:false,platform:'node',format:'cjs',packages:'external',jsx:'automatic',plugins:[{name:'home-controls',setup(b){
  b.onResolve({filter:/.*/},args=>{
    if(!args.path.startsWith('.')&&!isAbsolute(args.path))return {path:args.path,external:true}
    const p=resolve(args.importer?dirname(args.importer):root,args.path)
    return {path:[p,p+'.jsx',p+'.js'].find(existsSync),namespace:'source'}
  })
  b.onLoad({filter:/AuthContext\.jsx$/},()=>({contents:'export const useAuth=()=>({user:{id:1,blood_type:"O+",email_verified:true},refresh:async()=>{}})',loader:'js'}))
  b.onLoad({filter:/apiClient\.js$/},()=>({contents:'export const api=offlineApi',loader:'js'}))
  b.onLoad({filter:/FeedCard\.jsx$/},()=>({contents:'export function FeedCard(){return null}',loader:'js'}))
  b.onLoad({filter:/(DemandMapWidget|EmailVerificationDialog)\.jsx$/},()=>({contents:'export default function Component(){return null}',loader:'js'}))
  b.onLoad({filter:/\.css$/},()=>({contents:'export default new Proxy({},{get:(_,k)=>k})',loader:'js'}))
  b.onLoad({filter:/.*/,namespace:'source'},args=>({contents:readFileSync(args.path,'utf8'),loader:extname(args.path)==='.jsx'?'jsx':'js'}))
}}]})
const router={Link:({to,children,...props})=>React.createElement('a',{href:to,...props},children),useSearchParams:()=>[new URLSearchParams(fixture.search),next=>{fixture.search=next.toString()}]}
const query={useInfiniteQuery:options=>{fixture.options=options;return {data:{pages:[{page:1,requests:[],has_more:true,total_matching:30,total_unfiltered:40}]},isPending:false,hasNextPage:true,isFetchingNextPage:false,refetch:async()=>{},fetchNextPage:async()=>{}}},useQuery:()=>({data:{municipalities:[{psgc_code:'030803000',name:'City of Balanga'},{psgc_code:'030807000',name:'Mariveles'}]}})}
const icons=new Proxy({},{get:()=>()=>React.createElement('svg')})
const mod={exports:{}}
new Function('require','module','exports','offlineApi',bundle.outputFiles[0].text)(id=>id==='react-router-dom'?router:id==='@tanstack/react-query'?query:id==='@phosphor-icons/react'?icons:require(id),mod,mod.exports,api)
let tree
function Render(){tree=mod.exports.default();return tree}
const render=()=>renderToStaticMarkup(React.createElement(Render))
const find=(node,predicate)=>Array.isArray(node)?node.map(n=>find(n,predicate)).find(Boolean):!node||typeof node!=='object'?null:predicate(node)?node:find(node.props?.children,predicate)
const selects=()=>{const found=[];const visit=node=>{if(Array.isArray(node))node.forEach(visit);else if(node&&typeof node==='object'){if(node.type==='select')found.push(node);visit(node.props?.children)}};visit(tree);return found}
const change=(position,value)=>{selects()[position].props.onChange({target:{value}});render()}
const params=()=>new URLSearchParams(fixture.search)
render()
assert.equal(selects().length,3)
change(0,'030803000');change(1,'A+');change(2,'emergency')
assert.equal(params().get('municipality_code'),'030803000')
assert.equal(params().get('blood'),'A+')
assert.equal(params().get('urgency'),'emergency')
const combined=fixture.search
const signal=new AbortController().signal
await fixture.options.queryFn({pageParam:1,signal})
let requestUrl=new URL(fixture.calls.at(-1).path,'http://local')
assert.equal(requestUrl.searchParams.get('blood'),'A+')
assert.equal(requestUrl.searchParams.get('municipality_code'),'030803000')
assert.equal(requestUrl.searchParams.get('urgency'),'emergency')
assert.equal(fixture.calls.at(-1).options.signal,signal)
const key=fixture.options.queryKey.at(-1)
find(tree,node=>node.props?.children==='Load more').props.onClick();render()
assert.equal(params().get('page'),'2')
assert.equal(fixture.options.queryKey.at(-1),key)
await fixture.options.queryFn({pageParam:2,signal})
assert.equal(new URL(fixture.calls.at(-1).path,'http://local').searchParams.get('page'),'2')
change(1,'O+')
assert(!params().has('page'))
assert.equal(params().get('urgency'),'emergency')
change(0,'');assert(!params().has('municipality_code')&&params().get('blood')==='O+')
find(tree,node=>node.props?.children==='Reset filters').props.onClick();render()
assert.equal(fixture.search,'')
// Simulate restored URL state. Real browser history/focus are outside SSR coverage.
fixture.search=combined;render()
assert.deepEqual(selects().map(node=>node.props.value),['030803000','A+','emergency'])
fixture.search='blood=A%2B%2CB%2B&urgency=urgent%2Cemergency';render()
assert.deepEqual(selects().slice(1).map(node=>node.props.value),['A+,B+','urgent,emergency'])
// Retired text-search URLs do not add an unsupported Home filter.
fixture.search='q=Hospital';render();await fixture.options.queryFn({pageParam:1,signal})
assert(!new URL(fixture.calls.at(-1).path,'http://local').searchParams.has('q'))
console.log('PASS Home filter callbacks, intersection parameters, plus encoding, reset, paging reset/cache key, restored selections, legacy multiselect and abort forwarding. Retired q is not sent. Synthetic transport only.')
