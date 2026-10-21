import fs from 'node:fs';import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..'),dir=path.join(root,'backend/storage/maintenance/DEMO_20261022_7C91');
const read=name=>JSON.parse(fs.readFileSync(path.join(dir,name+'.json')));
const m=read('manifest'),i=read('final-inventory'),s=read('prepared'),b=read('baseline'),api=read('api-checks'),safety=read('safety-checks'),release=read('released-emails-backup');
const safe=v=>String(v??'—').replaceAll('|','/').replaceAll('\n',' ');
const table=(head,rows)=>'| '+head.join(' | ')+' |\n| '+head.map(()=>'---').join(' | ')+' |\n'+rows.map(r=>'| '+r.map(safe).join(' | ')+' |').join('\n')+'\n';
const label=id=>m.accounts.find(a=>a.id===id)?.label??'Registration API example';
const reqLabel=id=>Object.entries(m.requests).find(([,r])=>r.id===id)?.[0]??'Demand API check (canceled)';
const changes=[];
for(const [name,t]of Object.entries(s.tables)){
 const keys=i.tables[name].primary_keys;
 if(!keys.length)continue;
 const key=r=>JSON.stringify(Object.keys(keys[0]).map(k=>r[t.columns.indexOf(k)]));
 const prior=new Map(b.tables[name].rows.map(r=>[key(r),r]));
 const made=[],changed=[];
 for(const row of t.rows){const k=key(row),old=prior.get(k),id=JSON.parse(k).join('/');if(!old)made.push(id);else if(JSON.stringify(old)!==JSON.stringify(row))changed.push(id+' ('+t.columns.filter((c,n)=>row[n]!==old[n]).join(', ')+')');}
 if(made.length||changed.length)changes.push([name,made.length?made.join(', '):'—',changed.join('; ')||'—']);
}
// Session keys and throttle keys are operational identifiers; keep their exact
// values in the private snapshot instead of exposing them in reviewable docs.
for(const row of changes)if(['user_presence','auth_throttle'].includes(row[0]))row[1]='Exact keys retained in private final-inventory.json; '+i.tables[row[0]].count+' rows';
const accountRows=i.accounts.map(a=>[a.id,a.email,label(a.id),a.verification_status,a.account_status,a.blood_type,a.chapter_id]);
const requestRows=i.requests.map(r=>[r.id,reqLabel(r.id),r.urgency,r.request_chapter_id,r.status,r.closure_reason??'',`/requests/${r.id}/matches`]);
const reportRows=i.reports.map(r=>{const c=Object.entries(m.cases).find(([,c])=>c.report_id===r.id);return[r.id,c?.[0],r.donor_id,c?.[1].request_id,r.status+'/'+r.workflow_state,'v'+r.content_version,r.credit_recorded_at?'1':'0',r.hold_started_at&&!r.hold_released_at?'held':'released'];});
const inventory=table(['Table','Created IDs / keys after copied baseline','Changed copied IDs (columns)'],changes);
let manifest=`# BloodMatch demonstration data manifest

Prepared 22 October 2026 (Asia/Manila). Batch: **${m.batch}**. This is demonstration preparation and API/service evidence, not browser acceptance.

## Database and isolation

- Application source: **127.0.0.1:3306 / bloodmatch**, host Boss-Mark, MariaDB 10.4.32. The normal connection resolved to 3306 even though the application supports port fallback. Preparation uses an explicit source connection; the demo HTTP router rejects any database/port other than the isolated target.
- Demo target: **127.0.0.1:3306 / ${m.database}**. Existing table definitions, constraints and triggers were copied, with reference data and five allowlisted account rows. No schema migrations, validation changes, clock changes or production feature changes were made.
- The four existing member examples are established by tests/phase5.ps1. Other pre-existing Gmail/member accounts and volunteer accounts were excluded from the demo copy. The existing designated Admin row is copied unchanged for authorized local access; its credentials were not replaced.
- UI: http://127.0.0.1:5174 . Backend: http://127.0.0.1:8011 . Mail inbox: http://127.0.0.1:8026 . All listeners bind to loopback. Use 127.0.0.1 for demo; the original localhost session and the demo use separate PHP session storage.
- All recurring workers are disabled for this demo process. SMTP is captured on 127.0.0.1:1026. OTP uses the existing Mailer. One membership email was processed by an explicitly scoped worker with a custom local capture transport; the production TLS SMTP transport was not exercised. No global worker was run.
- Current configured restrictions: **42 hours after donation**, **90-day donation cooldown**, independent unresolved-report holds. **Non-response standby is retired**. Report reminder/escalation uses original claim time at **24 / 48 hours**, without silent confirmation/rejection.

## Accounts

${table(['ID','Email','Demo label','Membership','Account','Blood','Chapter'],accountRows)}
Use the existing account passwords. Matching saved credentials were verified locally without changing hashes. Existing access is in ignored private storage at backend/storage/maintenance/${m.batch}/existing-access.json; additional synthetic access is in synthetic-credentials.jsonl in the same directory. These private files contain secrets and must not be committed, printed during the presentation or included in the paper. This manifest intentionally contains no credentials or tokens.

The additional accounts separate live matching, withdrawal, acceptance, report states and disposable closure. The fresh-registration API-check account is intentionally membership-pending. For a fresh live signup use **demo_20261022_7c91.fresh-live@example.com**, which has not been registered. Its mail will be captured locally. Account 160 is reserved for live deactivation/deletion and has no donation reports; its donor enrollment was removed in the isolated copy.

## Requests and screens

All requests belong to existing example requester **85**. Open each path on the demo UI origin.

${table(['ID','Scenario','Urgency','Chapter','Starting status','Closure reason','Screen'],requestRows)}
Main **105** remains OPEN, with no donation reports. Leave it open after the matching/response segment. Use request **114** for the later live report segment. Request **107** is reserved for cancellation and the two-session demand change. Request **${m.requests['close-live'].id}** is separate live closure; **${m.requests['expired-static'].id}** is a separately service-expired example, while **110** remains available for live renewal.

Venue: Bataan General Hospital and Medical Center, directory hospital 1 / locality 26, Balanga. The hospital's Manahan Street, Tenejero, Balanga address is supported by the [DOH directory](https://ntp.doh.gov.ph/resources/facilities/?yiiwp-page=40). Stored coordinates **14.683333, 120.533333** are the existing locality reference, not a surveyed entrance. Approximate distance demonstration is usable; entrance-level geolocation remains unverified.

Matching code orders known distance ascending, unknown distance last, same-chapter first only when distances tie, then donor ID ascending. Comparator tie/unknown checks passed. Near donor **156** is Chapter 2 (~0.81 km); farther donor **157** is Chapter 1 (~12.97 km). This demonstrates that chapter membership does not override proximity. Report-ready donor 161 is another suggested candidate; reserve that donor for request 114. Stored distance is rounded to two decimals; do not interpret it as travel distance. One small-fixture generation took ${m.checks.matching_elapsed_ms} ms; this is not a load/performance acceptance claim.

## Responses, chat, donations and notifications

${table(['Case','Donor','Request','Match','Context/conversation','Starting action'],[
 ['Live withdrawal',m.cases.withdrawal.donor_id,m.cases.withdrawal.request_id,m.cases.withdrawal.match_id,'none','Withdraw live on /responses'],
 ['Live acceptance',m.cases['accept-live'].donor_id,m.cases['accept-live'].request_id,m.cases['accept-live'].match_id,'not accepted','Owner Accept & chat live'],
 ['Live report',m.cases['report-ready'].donor_id,114,m.cases['report-ready'].match_id,m.cases['report-ready'].context_id+'/'+m.cases['report-ready'].conversation_id,'Accepted, no report/credit; report live'],
 ['Chat consent pending',157,'pair 156/157','—',m.cases['chat-pending'].id,'Recipient accepts live'],
 ['Reported message',api.created.reported_chat.reporter_id,'separate pair','—',api.created.reported_chat.conversation_id,'Admin /admin/chat-reports: case '+api.created.reported_chat.report_id]
 ])}

${table(['Report','Scenario','Donor','Request','Starting state','Version','Credit units','Report hold'],reportRows)}
Reports open from **/donation-reports** for participants or **/admin/confirmations** for Admin; select the listed ID. Report 4 has dummy proof evidence **2** on version 2 while its previous uncertainty remains explicitly acknowledged on version 1. This is deliberate: proof changes do not erase a dispute or imply renewed agreement. Clarification is report 5; completed-correction request is attached to report 6; rejected report 7 keeps its hold until an explicit justified no-donation release.

Report 8 has its 24-hour reminder. Report 9 has the reminder plus 48-hour escalation. First scoped pass returned reminded=2, escalated=1; repeat returned 0/0. Both remain unresolved. Notification screens: **/notifications**, bell flyout and the local capture inbox. The scoped follow-up never queries the genuine report pool.

Dummy documents **49 / 50 / 51** belong to members **69 / 89 / 104**. They use freshly encrypted, clearly marked fake raster images. All three decrypted successfully, and the source image was visually inspected. The same marked image was processed as optional proof through multipart HTTP. No real identification files were copied.

## Authorized release of working emails in the application database

You identified the following nine accounts for email reuse. Their original working emails are absent from users and pending_registrations. Only these test profiles were changed to deactivated and given reserved released-ID-demo addresses. Password hashes and all request, donation and chat history were preserved. This is a reversible email release, not destructive history deletion.

${table(['Application ID','Freed email'],release.before_users.map(u=>[u.id,u.email]))}

Affected application records: users **137, 138, 139, 140, 141, 142, 143, 144, 147**; changed columns email, account_status, deactivated_at, updated_at. Changed mail-job IDs: **${release.after_jobs.filter((j,n)=>JSON.stringify(j)!==JSON.stringify(release.before_jobs[n])).map(j=>j.id).join(', ')||'none'}**. Exact before/after rows are in released-emails-backup.json. Created append-only demo.email.apply audit IDs: **${safety.release_audit_ids?.join(', ')??'see safety-checks.json'}**. No cleanup/restore was executed.

Safety checks: all business tables match their original fingerprint after accounting for the authorized release; all nine password hashes match their backup; all nine working emails are free; demo table definitions match the copied schema. The original open application session independently changed audit/presence rows while polling after deactivation. Those concurrent records were retained; a blanket whole-database unchanged claim would be false.

## Exhaustive record inventory and backups

The following lists every created or changed demo record relative to the copied baseline. Foreign-key references identify child history, notifications, evidence, outbox and audit records as part of this batch even where the schema has no batch column. Reference tables were copied unchanged; exact row inventories are in the private baseline and snapshot.

${inventory}
The complete local inventory is backend/storage/maintenance/${m.batch}/final-inventory.json. The exact restorable prepared database (DDL, rows excluding generated columns, triggers) is prepared.json; baseline.json records the initial copied state; source-account-backup.json backs up the original allowlisted examples and Admin row; released-emails-backup.json backs up the nine released application accounts and their mail jobs. These backups contain private data and remain gitignored. manifest.json, api-checks.json, demand-api-check.json and safety-checks.json preserve detailed verification evidence.

New encrypted dummy files are enumerated with table/record IDs and SHA-256 hashes in final-inventory.json. A rolled-back preparation attempt left three extra encrypted dummy files; their exact paths are retained under prior_attempt_files in manifest.json. They are unused and retained deliberately. No automatic file cleanup occurs. The source image is backend/storage/maintenance/${m.batch}/DEMO_ONLY.png. The demo-only document key lives outside the repository in the OS temporary directory; keep it while using the saved demo snapshot. No key contents are included here.

See [DEMO_WALKTHROUGH.md](DEMO_WALKTHROUGH.md), [DEMO_COMMANDS.md](DEMO_COMMANDS.md), and [DEMO_COVERAGE_CHECKLIST.md](DEMO_COVERAGE_CHECKLIST.md).
`;
fs.writeFileSync(path.join(root,'DEMO_DATA_MANIFEST.md'),manifest);
const rows=[
['1 Accounts','Ready','Existing pending 89/104 and rejected 69; fresh registration + captured OTP + login tested through HTTP. Live fresh signup remains unused.'],
['2 Admin verification','Ready','Dummy documents encrypted/decrypted; rejection service created decision history; Admin credentials unchanged and staff chat evidence API readable. Live pending decisions retained.'],
['3 Donor participation','Ready','Available, unavailable, incompatible, post-donation, cooldown and rejected-report hold fixtures present; window/hold services checked.'],
['4 Requests','Ready','Routine/Urgent/Emergency, edit/cancel/close, canceled/closed, separate expired/renewable records. Scoped expiry service and HTTP create/cancel exercised. Edit/renew/close UI remains live.'],
['5 Matching','Partial','Compatible near/far fixtures, current ranking/ties checked; small generation 28.63 ms. Hospital reference is locality-level; exact entrance and browser ordering unverified.'],
['6 Responses','Ready','Dedicated active offers for withdrawal and acceptance; accepted report-ready offer has zero report/credit. Response/acceptance services exercised.'],
['7 Chat','Ready','Pending consent plus accepted request context, messages/read state; separate selected-message report created through APIs and readable by Admin.'],
['8 Donations','Ready','Independent report-ready, mutual, uncertain, clarification, correction, rejected-hold and cooldown fixtures. Multipart dummy proof persisted; outsider proof request denied.'],
['9 Notifications','Ready','OTP and membership-decision email captured locally; scoped 24h/48h processor checked, retry 0/0, unresolved status preserved. Production SMTP not tested.'],
['10 Demand refresh','Partial','Source interval 30000 ms, API create/cancel aggregate 0→1→0 verified. Request 107 remains for second-session 1→0. Mounted automatic refresh not browser-tested.'],
['11 Reports/privacy','Partial','Admin analytics/CSV endpoints 200; member analytics/staff queue 403; anonymous analytics 401; private proof denial. Browser CSV download, print and disposable closure remain live/unverified.']
];
fs.writeFileSync(path.join(root,'DEMO_COVERAGE_CHECKLIST.md'),`# Demo coverage checklist

22 October 2026. Ready means **data is staged for the live demonstration with the stated service/API checks**. It does not mean browser acceptance passed. Numbers map to the eleven scenario groups in the preparation request; older manuscript proposals in the repository use different numbering and are not silently substituted.

${table(['Objective / scenario group','Status','Checks actually performed and limits'],rows)}
## Browser blocker

Computer-use browser creation failed and inventory returned no connected browsers or apps. No rendered UI behavior passed this session. The frontend and local capture HTTP servers respond, but this is not evidence of React rendering, navigation, button behavior, downloads, print layout or automatic mounted polling. Manual live steps are in the walkthrough.

## Safety and repeatability

- Explicit application source and isolated demo database verified before writes.
- Existing fixture origins and exact account allowlist checked. User separately identified nine working emails for reversible release.
- No password replacement, Admin mutation, real donation edits, schema changes, global workers or external email sends.
- No demo orphan foreign-key references found; foreign keys enabled; response ≤ acceptance ≤ occurrence ≤ claim chronology check found zero violations.
- Schema-copy comparison passed for every demo table; source business-table normalization passed, with concurrent original-app audit/presence drift recorded separately.
- Re-running preparation and API preparation commands skipped already-recorded fixtures without duplicates. PHP/Node syntax checks passed.
- Cleanup/restore commands were prepared and reviewed from source; **neither was executed**, as requested. Database restore needs the retained dummy files and demo key. Original-email restore refuses reused addresses or intervening profile/mail changes.
`);
console.log('Wrote manifest and coverage checklist with exhaustive record inventory; no secrets included.');
