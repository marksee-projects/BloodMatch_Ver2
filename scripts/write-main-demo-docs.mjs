import fs from 'node:fs';import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..'),privateDir='backend/storage/maintenance/DEMO_MAIN_20261022_B42E',dir=path.join(root,privateDir);
const read=n=>JSON.parse(fs.readFileSync(path.join(dir,n+'.json'))),m=read('manifest'),made=read('created-records'),api=read('api-checks'),mail=read('mail-check');
const escape=v=>String(v??'—').replaceAll('|','/');const table=(head,rows)=>'| '+head.join(' | ')+' |\n| '+head.map(()=>'---').join(' | ')+' |\n'+rows.map(r=>'| '+r.map(escape).join(' | ')+' |').join('\n')+'\n';
const archive=path.join(root,'docs/demo/previous-isolated-20261022');fs.mkdirSync(archive,{recursive:true});
for(const name of ['DEMO_DATA_MANIFEST.md','DEMO_WALKTHROUGH.md','DEMO_COMMANDS.md','DEMO_COVERAGE_CHECKLIST.md'])if(!fs.existsSync(path.join(archive,name))&&fs.existsSync(path.join(root,name)))fs.copyFileSync(path.join(root,name),path.join(archive,name));
const label=id=>m.accounts.find(a=>a.id===id)?.label??(id===m.maps.users[170]?'Registration check':'Synthetic');
const get=id=>m.checks.accounts.find(a=>a.id===id);const r=name=>m.requests[name].id;const c=name=>m.cases[name];
const chat=made.chat_reports[0],chatEvidence=made.chat_report_evidence[0];
const reqRows=m.checks.requests.map(x=>[x.id,Object.entries(m.requests).find(([,y])=>y.id===x.id)?.[0]??'Canceled demand API check',x.urgency,x.request_chapter_id,x.status,`/requests/${x.id}/matches`]);
const reportRows=m.checks.reports.map(x=>[x.id,Object.entries(m.cases).find(([,y])=>y.report_id===x.id)?.[0],x.donor_id,x.match_id,x.status+'/'+x.workflow_state,x.content_version,x.credit_recorded_at?'1':'0',x.hold_started_at&&!x.hold_released_at?'held':'released']);
fs.writeFileSync(path.join(root,'DEMO_DATA_MANIFEST.md'),`# BloodMatch MAIN database demo manifest

**Current target: bloodmatch on 127.0.0.1:3306.** Host Boss-Mark. Batch **${m.batch}**, prepared 22 October 2026 (Asia/Manila). This replaces the earlier isolated-database plan at your explicit request. The old isolated database and backups remain intact; neither cleanup nor restore was executed.

## Access and safety

The records are in the database used by the normal BloodMatch application and phpMyAdmin. The local capture interface **http://127.0.0.1:5174** now also uses **bloodmatch**, not the separate demo database. Its backend is http://127.0.0.1:8011 and mail inbox http://127.0.0.1:8026. Use this interface for dummy documents, protected proof and captured signup mail; the normal server's SMTP/key configuration was not edited.

Only confirmed existing examples **69, 85, 89, 104** were reused. Existing requester 85 received demo donor/profile fields after backup; its password, role, email and original history were preserved. Original Admin **3** was not changed. Seventeen independent synthetic accounts were added. The earlier nine user-confirmed working-email releases remain in place, with their original backup/restore script; those addresses were not reoccupied by this transfer.

The transfer used an explicit main connection, a private full pre-transfer backup and one transaction. Constraints stayed enabled. Primary IDs, FK IDs, snapshot references and logical chat evidence IDs were remapped. No schema change or global matching, follow-up or email worker ran. Existing business rows were checked unchanged except for backed-up demo fields on example 85. **71 imported notifications target only confirmed example accounts; zero new email jobs were imported.** Genuine donations, genuine member profiles and Admin credentials were preserved.

Main HTTP checks passed for example login, requester matching read, Admin dummy PNG decryption, main reported-message evidence, analytics/nonempty CSV and denied outsider proof access. No browser UI behavior was tested. Imported response/donation/timer histories are fixtures; previous isolated service/API results are historical evidence, not proof of a main-database live workflow.

## Accounts

${table(['Main ID','Email','Label','Membership','Blood','Chapter'],m.checks.accounts.map(a=>[a.id,a.email,label(a.id),a.verification_status,a.blood_type,a.chapter_id]))}
Private access files: ${privateDir}/existing-access.json and synthetic-access.json. A readable email/password handoff requested by the user is **${privateDir}/DEMO_LOGIN_ACCOUNTS.local.md**; it remains gitignored and excludes the Admin password. Existing credentials were checked against current hashes; no passwords were replaced. Fresh live signup address: **demo_main_20261022_b42e.fresh-live@example.com** (unused).

Regular Ito User (64) is **A−** and cannot donate to main O+ request 115. A separate **A+ demo request 136** was added for this account's compatible feed. Its profile, blood type, restrictions and password were not changed, and no match notification was sent to account 64. Main requester 85 owns 136; matching notifications were limited to confirmed synthetic donors. Exact visibility checks are recorded privately in visibility-check.json and regular-feed-api-check.json.

## Request starting states and exact screens

${table(['Main request ID','Scenario','Urgency','Chapter','Starting status','Screen'],reqRows)}
Main request **${r('main-live')}** is OPEN with zero donation reports. Use **${r('report-ready')}** for the separate live donation report. Use **${r('cancel-live')}** for live cancellation and demand refresh. Leave main ${r('main-live')} open. Donors for withdrawal, acceptance, cooldown, report review and disposable closure are separate.

## Responses, chat and donations

${table(['Scenario','Donor ID','Request ID','Match ID','Context / conversation'],[
 ['Main near donor',m.maps.users[156],r('main-live'),'suggested','live response remains undone'],
 ['Main farther donor',m.maps.users[157],r('main-live'),'suggested','live response remains undone'],
 ['Withdraw live',c('withdrawal').donor_id,c('withdrawal').request_id,c('withdrawal').match_id,'not accepted'],
 ['Accept live',c('accept-live').donor_id,c('accept-live').request_id,c('accept-live').match_id,'not accepted'],
 ['Report live',c('report-ready').donor_id,c('report-ready').request_id,c('report-ready').match_id,c('report-ready').context_id+' / '+c('report-ready').conversation_id]
 ])}
Pending pair-consent request **${c('chat-pending').id}** is between near/far donors. Reported-message review: **/admin/chat-reports**, case **${chat.id}**, conversation **${chat.conversation_id}**, message **${chatEvidence.message_id}**. Member chat opens on **/chat**. Dummy National ID document IDs: **${c('document-69').document_id}, ${c('document-89').document_id}, ${c('document-104').document_id}** for members 69/89/104. They remain additional clearly marked dummy records; original example documents were not deleted.

${table(['Main report ID','Scenario','Donor','Match','Starting state','Version','Credited units','Report hold'],reportRows)}
Participants open **/donation-reports**; Admin opens **/admin/confirmations** and selects the listed report. Optional dummy proof evidence is **${made.donation_report_evidence[0].id}**. Uncertain report remains version 2 with the older uncertainty explicitly tied to version 1. All synthetic donation notes contain ${m.batch}; the reused marked dummy image shows the original DEMO watermark and is never a real ID/proof.

Configured safety rules remain **42-hour post-donation restriction**, **90-day cooldown**, unresolved-report holds, request expiry and separate **24-hour reminder / 48-hour escalation**. Non-response standby is retired. The reminder/escalation timestamps were transferred unchanged; no global follow-up processing occurred on main records. Accepted offers have zero credit until a valid report is completed.

## Matching and demand

Near donor **${m.maps.users[156]}** is Chapter 2 (~0.81 km); farther **${m.maps.users[157]}** is Chapter 1 (~12.97 km). Ranking is distance, then same chapter for ties, then donor ID; unknown distance sorts last. The hospital is BGHMC in Balanga, hospital 1/locality 26. Its existing locality coordinates (14.683333,120.533333) are not a surveyed entrance. See the [DOH facility directory](https://ntp.doh.gov.ph/resources/facilities/?yiiwp-page=40) for the hospital address. Old 28.63 ms generation and comparator checks came from the isolated preparation; no main matching-speed acceptance claim is made.

The main database includes genuine history, so its demand count differs from the isolated demo. Actual HTTP baseline for Chapter 3/Emergency is **${api.demand.baseline}**. Cancel demo request **${api.demand.cancel_request_id}** in session B; session A should change **${api.demand.baseline} → ${api.demand.expected_after_cancel}** on its next successful refresh, unless other main users change requests concurrently. Source interval is 30000 ms while visible/online. Mounted polling remains browser-unverified.

General request create/material edit/renew/rematch can notify genuine donors in a shared database. The local demo helper blocks these broad routes; use the bounded CLI helper in DEMO_COMMANDS.md. Normal application feature code is unchanged. The prepared cancellation, closure, response, acceptance, chat and report cases remain independent and available for live actions.

## Exhaustive imported-record inventory and recovery

${table(['Main table','Created main IDs'],Object.entries(made).map(([t,rows])=>[t,rows.map(x=>x.id).join(', ')]))}
Existing changed example **85**: ${Object.keys(m.changed_examples[85]??{}).join(', ')}. Every before/after field is in manifest.json; password/role/email are excluded from updates. Full original main schema/data/triggers backup: **${privateDir}/before.json**. Exact created row images: created-records.json. Source→main ID maps and scenario states: manifest.json. Main HTTP evidence: api-checks.json. Relational/chronology checks: checks.json. Local captured transport smoke message: mail-check.json. Private originals from the previous email release remain under backend/storage/maintenance/DEMO_20261022_7C91/.

The captured main email is an explicit loopback transport smoke test, not a claimed automated match/decision workflow. Main verification may append authentication/audit/accounting records; immutable audit/accounting records and dummy image files are retained by cleanup. No genuine original record is removed by the scoped cleanup command; it refuses intervening profile changes, modified fixtures or unrecorded FK links.

See [walkthrough](DEMO_WALKTHROUGH.md), [commands](DEMO_COMMANDS.md), and [coverage](DEMO_COVERAGE_CHECKLIST.md). Earlier isolated-only documents are archived under docs/demo/previous-isolated-20261022/.
`);
const steps=[
['1 — Accounts',`Use /register with the unused fresh-live address above and synthetic details. Show captured OTP at port 8026 and keep email verification distinct from membership. Existing pending members 89/104 and rejected 69 are reused. Fresh main signup remains live; earlier successful signup checks occurred on the isolated source.`],
['2 — Admin verification',`Use /admin/verifications. Open dummy documents ${c('document-89').document_id} and ${c('document-104').document_id}; approve 89 and reject 104 with a DEMO reason live. Use /admin/users for chapter assignment. Replace/resubmit member 69's dummy document in its own session. Admin credentials are unchanged. Admin viewing of the main dummy PNG passed through HTTP; decisions remain live.`],
['3 — Participation',`Compare near ${m.maps.users[156]}, unavailable ${m.maps.users[158]}, incompatible AB+ ${m.maps.users[159]}, recent mutual ${c('mutual').donor_id}, cooldown ${c('cooldown').donor_id} and rejected-report hold ${c('hold-rejected').donor_id}. Show /profile and /donation-reports. Keep the 42-hour post-donation window, 90-day cooldown and report hold distinct. Non-response standby is retired.`],
['4 — Requests',`Show main Routine ${r('main-live')}, Urgent ${r('editable')} and Emergency ${r('cancel-live')}. Show canceled ${r('canceled')}, closed ${r('closed')}, expired ${r('expired-static')} and renewable ${r('expired-renewable')}. Use ${r('close-live')} for live reasoned closure. Main-database broad create/edit/renew/rematch has not been run; use only scoped CLI creation/editing in the commands file while genuine members share the database.`],
['5 — Matching',`Open /requests/${r('main-live')}/matches as requester 85. Show near ${m.maps.users[156]} ahead of farther ${m.maps.users[157]} despite the latter's same-chapter status. Explain distance→chapter tie→ID ordering and locality-level coordinate limits. Main matching data read passed via HTTP; browser ordering/performance remains unverified.`],
['6 — Responses',`As near ${m.maps.users[156]}, offer help on main ${r('main-live')} live. Use dedicated donor ${c('withdrawal').donor_id} to withdraw on request ${c('withdrawal').request_id}. As requester 85, accept dedicated donor ${c('accept-live').donor_id} on ${c('accept-live').request_id}. Acceptance must not create credit. Keep main open and use the separate report-ready case for completion.`],
['7 — Chat',`As far ${m.maps.users[157]}, accept pending chat consent ${c('chat-pending').id} from near. Open requester 85/report-ready donor ${c('report-ready').donor_id}, conversation ${c('report-ready').conversation_id}, to show request-linked coordination and messages/read state. Admin opens /admin/chat-reports case ${chat.id}; bounded evidence is separate from the live coordination pair. Main staff evidence read passed; browser delivery/read indicators remain live.`],
['8 — Donations',`As donor ${c('report-ready').donor_id}, report request ${c('report-ready').request_id}, match ${c('report-ready').match_id}, after a valid occurrence at/after acceptance and no later than now. Use only ${privateDir}/DEMO_ONLY.png for optional proof. Requester 85 acknowledges the exact version. Quantity is two, so one credited unit leaves it open. Separately show report states in the manifest: mutual, uncertainty, clarification, correction and retained rejected hold. No new main donation was completed during migration.`],
['9 — Notifications',`Use /notifications for confirmed example recipients. The imported 24/48-hour fixtures remain unresolved; silence never decides them. Show the loopback transport smoke message in the capture inbox. Live signup mail uses capture through port 5174. Do not run a global worker; previous 2-reminder/1-escalation and 0/0 retry processing evidence is from the isolated source, not a newly executed main worker.`],
['10 — Demand refresh',`Keep session A visible on Home demand or /admin/demand-map, Chapter 3/Emergency. Measured main baseline is ${api.demand.baseline}. In separate requester session B, cancel ${r('cancel-live')}. Expected ${api.demand.baseline}→${api.demand.expected_after_cancel} on a successful focus refresh or next 30-second poll. Concurrent genuine activity can also change the count. Browser automatic refresh remains unverified.`],
['11 — Reports/privacy',`As Admin use /admin/analytics with 1–22 October 2026 and chapter filters, CSV and print; genuine main history is included alongside marked demo history. Main analytics/nonempty CSV returned 200. Anonymous/member analytics returned 401/403 and outsider proof returned 403. Use /admin/audit-logs for marked batch/IDs. Reserve disposable ${m.maps.users[160]} for final deactivation/deletion; do not delete requester 85 or report donors. Browser CSV download, print and closure remain untested.`]
];
fs.writeFileSync(path.join(root,'DEMO_WALKTHROUGH.md'),`# MAIN database demo walkthrough

Use **bloodmatch**, with the local capture interface **http://127.0.0.1:5174** and inbox **http://127.0.0.1:8026**. These ports now connect to the main database. Use separate browser profiles/private sessions for requester, donor and Admin. Existing passwords remain unchanged; private access files are identified in the manifest.

The eleven numbers below follow the user's eleven demo scenario groups. No UI behavior passed browser testing. Imported fixture states are not proof that the corresponding main user workflow works. Keep main request **${r('main-live')}** open; it currently has zero reports.

${steps.map(([h,t])=>'## '+h+'\n\n'+t).join('\n\n')}

Only clearly marked dummy files may be uploaded. No cleanup/restore runs automatically. Use [DEMO_COMMANDS.md](DEMO_COMMANDS.md) for scoped main-database recovery.
`);
fs.writeFileSync(path.join(root,'DEMO_COVERAGE_CHECKLIST.md'),`# MAIN database coverage checklist

Ready means fixtures are staged for the stated live actions; **it does not mean browser acceptance passed**. All source-only checks are distinguished from checks actually repeated against main.

${table(['Objective','Status','Main checks / remaining limits'],[
['1 Accounts','Ready','Existing pending/rejected examples retained; main login checked. Fresh captured signup left live; earlier OTP end-to-end check was isolated.'],
['2 Verification','Ready','Main dummy PNG decrypted through Admin HTTP; pending live decisions preserved.'],
['3 Donor participation','Ready','Independent availability, compatibility, post-donation/cooldown and report-hold states transferred; originals protected.'],
['4 Requests','Partial',`${m.checks.requests.length} marked main fixtures present, including A+ #136 for Regular Ito User. Scoped CLI creation exercised with normal validation and example-only matching; broad UI create/edit/rematch/renew excluded. No UI acceptance claim.`],
['5 Matching','Partial','Main requester matching endpoint passed; distance/tie source retained, entrance precision and browser/performance unverified.'],
['6 Responses','Ready','Separate response/withdraw/accept/report-ready fixtures; main starts OPEN with zero reports/credit. Live actions undone.'],
['7 Chat','Ready','Remapped evidence references checked; Admin main report read passed. Live consent/read indicators not browser-tested.'],
['8 Donations','Ready','Eight independent reports; chronology violations zero; outsider main proof denied. Source fixture transitions are not main workflow tests.'],
['9 Notifications','Partial','71 notifications scoped to examples; zero email jobs imported; loopback smoke captured. Main automated 24/48h processing not run; historical fixture checks retained.'],
['10 Demand','Partial',`Main aggregate measured ${api.demand.baseline}; live cancellation should yield ${api.demand.expected_after_cancel}. Source interval 30000 ms; mounted refresh untested.`],
['11 Reports/privacy','Partial','Main Admin analytics/nonempty CSV 200; anonymous/member/outsider denial checks passed. Browser download/print and disposable closure untested.']
 ])}
Backups and scoped cleanup/restore are prepared. No cleanup/restore was executed. Admin row and protected original business rows were verified unchanged, except backed-up demo profile fields on example 85. No global worker or external SMTP delivery ran. Main schema and constraints are unchanged.
`);
fs.writeFileSync(path.join(root,'DEMO_COMMANDS.md'),`# MAIN database preparation and scoped recovery

Run from the repository root in PowerShell. Target is **bloodmatch / 127.0.0.1:3306**, batch **${m.batch}**. These commands never drop or recreate the main database.

## Review / preparation / verification

The transfer has already been applied. Rerunning apply detects the batch and creates no duplicates.

\`\`\`powershell
C:/xampp/php/php.exe database/prepare_main_demo.php review
C:/xampp/php/php.exe database/prepare_main_demo.php apply ${m.batch}
C:/xampp/php/php.exe database/finalize_main_demo.php
node scripts/check-main-demo.mjs
node scripts/write-main-demo-docs.mjs
\`\`\`

Finalization verifies imported IDs/references and prepares private access files. It never resets original passwords. HTTP verification signs into only approved examples/Admin and reads only the listed demo evidence and authorized analytics. The normal application’s audit/presence may continue changing concurrently.

## Local capture interface to MAIN

Servers were left running. UI http://127.0.0.1:5174 ; inbox http://127.0.0.1:8026 . Unlike the original version of these scripts, the starter now selects **bloodmatch**. It applies SMTP/key overrides only to its child process; .env and the normal application's configuration are unchanged.

\`\`\`powershell
powershell -ExecutionPolicy Bypass -File scripts/stop-system-demo.ps1
powershell -ExecutionPolicy Bypass -File scripts/start-system-demo.ps1
C:/xampp/php/php.exe database/check_main_demo_mail.php
\`\`\`

The last command is an idempotent local transport smoke check, not a global queued worker or workflow proof. Keep the demo key and existing dummy files; their location is recorded privately. Real document keys/files are not changed.

## Scoped request creation/edit helper

Broad normal request creation/material edits/renewal/rematching can select genuine donors in the shared main database. The local capture helper refuses these broad routes; product feature code and normal application routes remain unchanged. The helper below uses the normal payload validator, fixture repository and per-donor scoped matching, only for requester 85 and confirmed demo donors. It does not prove UI creation/edit works.

For a bounded edit, create a private payload file and run:

\`\`\`powershell
@{ quantity_units = 3 } | ConvertTo-Json | Set-Content -Encoding ASCII ${privateDir}/edit.json
C:/xampp/php/php.exe database/main_demo_request.php edit ${r('editable')} ${privateDir}/edit.json
\`\`\`

For creation, save a complete normal request payload (required_blood_type, quantity_units, facility_name, hospital_id, location_id, urgency, future needed_datetime) as create.json in that same private directory, then use:

\`\`\`powershell
C:/xampp/php/php.exe database/main_demo_request.php create 0 ${privateDir}/create.json
\`\`\`

Do not use a global worker or general seeder. Successful bounded live actions are logged in scoped-request-actions.jsonl; they may require reviewed cleanup beyond the initial transfer inventory.

## Scoped cleanup — PREPARED, NOT EXECUTED

Stop the demo interface first. This removes only imported business rows listed in the main batch's created-records.json and restores the backed-up changed fields on example 85. It refuses modified fixtures, intervening changes to those example fields or unrecorded FK-linked business records. It preserves immutable audit/accounting records, dummy files and backups. A later live action may make cleanup require review; the command will stop instead of cascading into unknown history.

\`\`\`powershell
C:/xampp/php/php.exe database/prepare_main_demo.php cleanup ${m.batch}
\`\`\`

## Restore original example fields only — PREPARED, NOT EXECUTED

This restores only the backed-up original profile fields changed on example 85. It leaves imported fixtures in place and refuses intervening edits. It does not reverse donation credit or overwrite any password/Admin row.

\`\`\`powershell
C:/xampp/php/php.exe database/prepare_main_demo.php restore-examples ${m.batch}
\`\`\`

The full private main before.json is an emergency backup, not permission for a global restore over genuine activity. Resetting main fixtures after completed live donations requires reviewed scoped changes; no whole-main-database reset command is provided. The isolated database's old DROP/restore command applies only to that old isolated database and must not be repurposed for main.

The previous nine working-email releases remain independently backed up. Their optional guarded restore is still database/release_demo_emails.php restore DEMO_20261022_7C91; it refuses reused addresses. It has not been executed.
`);
console.log('Updated all four deliverables for the MAIN database; old isolated documents archived.');
