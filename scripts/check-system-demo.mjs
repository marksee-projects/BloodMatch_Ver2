// API verification only. Never claims mounted UI/browser acceptance.
import fs from 'node:fs';import path from 'node:path';import crypto from 'node:crypto';
const root=path.resolve(import.meta.dirname,'..'),dir=path.join(root,'backend/storage/maintenance/DEMO_20261022_7C91');
const evidenceFile=path.join(dir,'api-checks.json');if(fs.existsSync(evidenceFile)){console.log('API preparation already recorded; no duplicate fixtures.');process.exit(0);}
const manifest=JSON.parse(fs.readFileSync(path.join(dir,'manifest.json')));
const creds=fs.readFileSync(path.join(dir,'synthetic-credentials.jsonl'),'utf8').trim().split('\n').map(l=>JSON.parse(l));
const existing=JSON.parse(fs.readFileSync(path.join(dir,'existing-access.json')));
const credential=id=>{const c=creds.find(c=>c.id===id);if(!c)throw Error('Missing demo credential');return c;};
const previousFile=path.join(dir,'api-checks-incomplete.json');
const previous=fs.existsSync(previousFile)?JSON.parse(fs.readFileSync(previousFile)):{};
const checks=[],created=previous.created??{};
class Client {
 cookies=new Map();
 async req(url,method='GET',data,expected=200){
  let token;if(method!=='GET'){const r=await this.req('/api/csrf');token=r.data.csrf_token;}
  const headers={Cookie:[...this.cookies].map(([k,v])=>`${k}=${v}`).join('; '),Origin:'http://127.0.0.1:5174'};
  if(token)headers['X-CSRF-Token']=token;
  if(data&&!(data instanceof FormData))headers['Content-Type']='application/json';
  const r=await fetch('http://127.0.0.1:8011'+url,{method,headers,body:data instanceof FormData?data:data?JSON.stringify(data):undefined,signal:AbortSignal.timeout(15000)});
  for(const cookie of r.headers.getSetCookie()){const kv=cookie.split(';')[0];this.cookies.set(kv.slice(0,kv.indexOf('=')),kv.slice(kv.indexOf('=')+1));}
  const raw=await r.text();let body;try{body=JSON.parse(raw);}catch{body={raw};}
  if(r.status!==expected)throw Error(`${method} ${url}: expected ${expected}, got ${r.status}: ${body.error?.message??raw.slice(0,120)}`);
  return body;
 }
 async login(c){await this.req('/api/login','POST',{email:c.email,password:c.password});}
}
try{
 const anon=new Client();await anon.req('/api/analytics/summary','GET',undefined,401);checks.push('Anonymous analytics access denied (401)');
 const member=new Client();await member.login(credential(manifest.accounts.find(a=>a.label==='near').id));checks.push('Synthetic donor login through HTTP API succeeded');
 await member.req('/api/analytics/summary','GET',undefined,403);await member.req('/api/admin/chat-reports','GET',undefined,403);checks.push('Member analytics and staff chat report access denied (403)');
 const demand=await member.req('/api/demand-map?chapter_id=3&urgency=emergency');created.demand_before=demand.data;checks.push('Authenticated chapter/urgency demand API returned aggregate');
 const owner=new Client();await owner.login(existing.find(a=>a.id===85));checks.push('Copied existing example requester login succeeded; password unchanged');
 const main=await owner.req('/api/requests/'+manifest.requests['main-live'].id+'/matches');created.main_match_response=main.data;
 const ready=await owner.req('/api/requests/'+manifest.cases['report-ready'].request_id+'/offers');created.accepted_offer_response=ready.data;
 const a=new Client(),b=new Client();const aid=manifest.accounts.find(a=>a.label==='unavailable').id,bid=manifest.accounts.find(a=>a.label==='incompatible').id;await a.login(credential(aid));await b.login(credential(bid));
 if(!created.reported_chat){
 const consent=await a.req('/api/chat/requests','POST',{recipient_id:bid});const accepted=await b.req('/api/chat/requests/'+consent.data.request.id+'/decision','POST',{action:'accept'});const conv=accepted.data.conversation_id;
 const message=await b.req('/api/chat/conversations/'+conv+'/messages','POST',{body:manifest.batch+' synthetic reported-message evidence. No actual misconduct or real person.'});
 const messageId=message.data.message.id;
 const reported=await a.req('/api/chat/conversations/'+conv+'/reports','POST',{reason:manifest.batch+' simulated message-review case only',message_ids:[messageId]});created.reported_chat={conversation_id:conv,message_id:messageId,report_id:reported.data.report_id,reporter_id:aid};
 await a.req('/api/chat/conversations/'+conv+'/read','POST',{last_message_id:messageId});checks.push('Separate consent, message, read status and selected-message report APIs succeeded');
 }else checks.push('Earlier successful separate consent/message/read/report API case retained without duplication');
 const uncertain=manifest.cases.uncertain,donor=new Client();await donor.login(credential(uncertain.donor_id));
 if(!created.proof_listing){
 const form=new FormData();form.append('expected_version','1');form.append('proofs[]',new Blob([fs.readFileSync(path.join(dir,'DEMO_ONLY.png'))],{type:'image/png'}),'DEMO_ONLY.png');
 const proof=await donor.req('/api/donation-completion/'+uncertain.report_id+'/proof','POST',form);created.proof_response=proof;
 }
 const listing=await donor.req('/api/donation-completion/'+uncertain.report_id+'/proof');created.proof_listing=listing.data;
 const evidence=listing.data.evidence;const evidenceId=Array.isArray(evidence)?evidence[0]?.id:evidence.files?.[0]?.id;
 if(evidenceId){await member.req('/api/donation-proof/'+evidenceId+'/file','GET',undefined,403);checks.push('Outsider proof access denied (403)');}
 const reports=await donor.req('/api/donation-completion');created.donor_report_list=reports.data;
 const report=reports.data.reports.find(r=>r.id==uncertain.report_id);
 if(report.content_version!==2||report.requester_response!=='not_sure'||report.acknowledged_version!==1)throw Error('Proof update did not retain exact old uncertainty response');
 checks.push('Dummy proof persisted through multipart API; listing confirms version 2 and preserves uncertainty on acknowledged version 1');
 const admin=new Client();await admin.login(existing.find(a=>a.id===3));await admin.req('/api/admin/chat-reports/'+created.reported_chat.report_id);checks.push('Existing Admin can review the separate bounded chat-report evidence');
 await admin.req('/api/analytics/summary?days=30');const csv=await admin.req('/api/analytics/export.csv?days=30');created.csv_available=typeof csv.raw==='string';checks.push('Admin analytics and CSV endpoints return 200 (browser download/print not tested)');
 const reg=new Client(),email=manifest.batch.toLowerCase()+'.registration-check@example.com',password=crypto.randomBytes(16).toString('hex')+'A1';
 fs.appendFileSync(path.join(dir,'synthetic-credentials.jsonl'),JSON.stringify({email,password,label:'registration-check'})+'\n');
 await reg.req('/api/register','POST',{first_name:'Registration',last_name:'DEMO',email,password,chapter_id:1,date_of_birth:'1995-01-01',privacy_acknowledged:true},201);
 const mailFiles=fs.readdirSync(path.join(dir,'mail')).filter(f=>f.endsWith('.eml'));const mail=mailFiles.map(f=>({file:f,body:fs.readFileSync(path.join(dir,'mail',f),'utf8')})).find(m=>m.body.includes(email));
 if(!mail)throw Error('Registration email not captured locally');const code=mail.body.match(/<strong>(\d{6})<\/strong>/)?.[1];if(!code)throw Error('Captured OTP not found');
 await reg.req('/api/auth/verify','POST',{email,code});created.registration={email,captured_file:mail.file};checks.push('Fresh registration staged; OTP captured by local SMTP and verified through HTTP API');
 await reg.login({email,password});checks.push('Newly verified email account can log in while membership remains pending');
 fs.writeFileSync(evidenceFile,JSON.stringify({batch:manifest.batch,checks,created,browser_tested:false},null,2));console.log(checks.join('\n'));
}catch(e){fs.writeFileSync(path.join(dir,'api-checks-incomplete.json'),JSON.stringify({checks,created,error:e.message,browser_tested:false},null,2));console.error(e.message);process.exitCode=1;}
