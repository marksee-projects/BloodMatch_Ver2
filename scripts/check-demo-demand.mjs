import fs from 'node:fs';import path from 'node:path';
const dir=path.resolve(import.meta.dirname,'../backend/storage/maintenance/DEMO_20261022_7C91'),file=path.join(dir,'demand-api-check.json');
if(fs.existsSync(file)){console.log('Demand API check already recorded.');process.exit(0);}
const c=JSON.parse(fs.readFileSync(path.join(dir,'existing-access.json'))).find(a=>a.id===85),cookies=new Map();
async function req(url,method='GET',data){let token;if(method!=='GET')token=(await req('/api/csrf')).data.csrf_token;const r=await fetch('http://127.0.0.1:8011'+url,{method,headers:{Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; '),'Content-Type':'application/json',...(token?{'X-CSRF-Token':token}:{})},body:data?JSON.stringify(data):undefined,signal:AbortSignal.timeout(15000)});for(const s of r.headers.getSetCookie()){const v=s.split(';')[0],i=v.indexOf('=');cookies.set(v.slice(0,i),v.slice(i+1));}const b=await r.json();if(!r.ok||!b.success)throw Error(`${method} ${url}: ${r.status} ${b.error?.message}`);return b;}
await req('/api/login','POST',{email:c.email,password:c.password});
const count=async()=>{const d=await req('/api/demand-map?chapter_id=2&urgency=emergency');return d.data.chapters.reduce((n,c)=>n+c.open_requests_count,0);};
const before=await count();const needed=new Date(Date.now()+7*86400000).toISOString().slice(0,19).replace('T',' ');
const created=await req('/api/requests','POST',{required_blood_type:'O+',quantity_units:1,facility_name:'Bataan General Hospital and Medical Center (BGHMC)',hospital_id:1,location_id:26,urgency:'emergency',needed_datetime:needed});
const id=created.data.request.id;const during=await count();await req('/api/requests/'+id+'/cancel','POST',{});const after=await count();
const evidence={request_id:id,chapter_id:2,urgency:'emergency',before,during,after,pass:during===before+1&&after===before,browser_tested:false,poll_interval_ms_from_source:30000,live_action_request_id:107,live_action_filter:{chapter_id:3,urgency:'emergency'},live_expected_change:'1 -> 0 after cancellation, once next successful refresh arrives'};
fs.writeFileSync(file,JSON.stringify(evidence,null,2));console.log(JSON.stringify(evidence));
