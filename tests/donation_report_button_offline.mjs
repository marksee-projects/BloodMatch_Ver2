import assert from 'node:assert/strict'
import {createRequire} from 'node:module'
const require=createRequire(new URL('../frontend/package.json',import.meta.url))
const {build}=require('esbuild'),React=require('react'),{renderToStaticMarkup}=require('react-dom/server')
const fixture={data:null,error:null}
const result=await build({jsx:'automatic',entryPoints:['./frontend/src/components/DonationOfferStatus.jsx'],bundle:true,write:false,platform:'node',format:'cjs',external:['react','react-router-dom','@tanstack/react-query','@phosphor-icons/react'],plugins:[{name:'offline',setup(b){b.onLoad({filter:/AuthContext\.jsx$/},()=>({contents:'export const useAuth=()=>({user:{id:2}})',loader:'js'}));b.onLoad({filter:/apiClient\.js$/},()=>({contents:'export const api={}',loader:'js'}));b.onLoad({filter:/Button\.jsx$/},()=>({contents:"import React from 'react'; export const Button=({children,disabled,title,onClick})=>React.createElement('button',{disabled,title,onClick},children)",loader:'js'}));}}]})
const module={exports:{}}
new Function('require','module','exports',result.outputFiles[0].text)(id=>id==='@tanstack/react-query'?{useQuery:()=>fixture}:require(id),module,module.exports)
function markup(data,error=null){fixture.data=data;fixture.error=error;return renderToStaticMarkup(React.createElement(module.exports.DonationReportButton,{matchId:42,onClick:()=>{}}))}
assert(!markup({status:'accepted',report_id:null}).includes('disabled'))
for(const id of [17,'17']){const html=markup({status:'accepted',report_id:id});assert(html.includes('disabled'));assert(html.includes('Report submitted'))}
assert(markup({status:'awaiting_acceptance'}).includes('disabled'))
assert(markup(null).includes('disabled'))
assert(markup({status:'accepted'},new Error('unavailable')).includes('disabled'))
console.log('PASS donation report button: accepted offer enabled; submitted report, awaiting acceptance, loading and errors disabled. No API/database calls.')
