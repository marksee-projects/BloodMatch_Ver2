// Local-only SMTP capture and isolated Vite proxy. No outbound networking.
import net from 'node:net';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const root=path.resolve(import.meta.dirname,'..');
const dir=path.join(root,'backend/storage/maintenance/DEMO_20261022_7C91/mail');fs.mkdirSync(dir,{recursive:true});
net.createServer(socket=>{
 socket.write('220 localhost BloodMatch DEMO capture\r\n');let buffer='',data=false,lines=[];
 socket.on('data',chunk=>{buffer+=chunk;let i;while((i=buffer.indexOf('\r\n'))>=0){const line=buffer.slice(0,i);buffer=buffer.slice(i+2);
  if(data){if(line==='.') {fs.writeFileSync(path.join(dir,`${Date.now()}-${Math.random().toString(16).slice(2)}.eml`),lines.join('\r\n'));data=false;lines=[];socket.write('250 Captured locally\r\n');}else lines.push(line.startsWith('..')?line.slice(1):line);continue;}
  if(/^EHLO|^HELO/i.test(line))socket.write('250-localhost\r\n250 SIZE 15000000\r\n');
  else if(/^MAIL FROM:|^RCPT TO:|^RSET|^NOOP/i.test(line))socket.write('250 OK\r\n');
  else if(/^DATA/i.test(line)){data=true;socket.write('354 End with dot\r\n');}
  else if(/^QUIT/i.test(line)){socket.end('221 Bye\r\n');}else socket.write('502 Unsupported\r\n');
 }});
}).listen(1026,'127.0.0.1');
const esc=s=>s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
http.createServer((req,res)=>{const files=fs.readdirSync(dir).filter(f=>f.endsWith('.eml')).sort().reverse();const name=new URL(req.url,'http://localhost').searchParams.get('file');res.setHeader('Cache-Control','no-store');res.setHeader('Content-Type','text/html;charset=utf-8');res.setHeader('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'");res.end(`<title>BloodMatch DEMO local mail capture</title><h1>DEMO — captured locally</h1><p>No external delivery. Refresh to see new messages.</p>${files.map(f=>`<p><a href="/?file=${encodeURIComponent(f)}">${esc(f)}</a></p>`).join('')}${name&&files.includes(name)?'<pre style="white-space:pre-wrap">'+esc(fs.readFileSync(path.join(dir,name),'utf8'))+'</pre>':''}`);}).listen(8026,'127.0.0.1');
const {createServer}=await import(pathToFileURL(path.join(root,'frontend/node_modules/vite/dist/node/index.js')));
const {default:react}=await import(pathToFileURL(path.join(root,'frontend/node_modules/@vitejs/plugin-react/dist/index.js')));
const vite=await createServer({configFile:false,plugins:[react()],root:path.join(root,'frontend'),server:{host:'127.0.0.1',port:5174,strictPort:true,proxy:{'/api':{target:'http://127.0.0.1:8011',changeOrigin:false}}}});await vite.listen();
console.log('Demo UI http://127.0.0.1:5174; capture http://127.0.0.1:8026; SMTP 127.0.0.1:1026');
