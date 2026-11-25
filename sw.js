const VERSION = 'lab-logbook-mobile-v1';
const STATIC_CACHE = VERSION + '-static';
let activeUser = 'guest';
const DB_NAME = 'lab-logbook-offline';
const STORE = 'pending-posts';

function openDB() {
  return new Promise((resolve,reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, {keyPath:'id', autoIncrement:true});
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}
async function queuePost(request, user) {
  const fd = await request.clone().formData();
  const entries = [];
  for (const [k,v] of fd.entries()) entries.push([k,v]);
  const db = await openDB();
  await new Promise((resolve,reject) => {
    const tx = db.transaction(STORE,'readwrite');
    tx.objectStore(STORE).add({url:request.url, method:'POST', entries, user, createdAt:Date.now()});
    tx.oncomplete=resolve; tx.onerror=()=>reject(tx.error);
  });
  db.close();
}
async function getQueue(user) {
  const db=await openDB();
  const rows=await new Promise((resolve,reject)=>{
    const req=db.transaction(STORE).objectStore(STORE).getAll();
    req.onsuccess=()=>resolve(req.result.filter(x=>x.user===user));
    req.onerror=()=>reject(req.error);
  });
  db.close(); return rows;
}
async function removeQueue(id) {
  const db=await openDB();
  await new Promise((resolve,reject)=>{
    const tx=db.transaction(STORE,'readwrite'); tx.objectStore(STORE).delete(id);
    tx.oncomplete=resolve; tx.onerror=()=>reject(tx.error);
  }); db.close();
}
async function flush(user) {
  if (!user || user==='guest' || !self.navigator.onLine) return;
  const rows=(await getQueue(user)).sort((a,b)=>a.id-b.id);
  for (const row of rows) {
    const fd=new FormData();
    for (const [k,v] of row.entries) fd.append(k,v);
    try {
      const res=await fetch(row.url,{method:'POST',body:fd,credentials:'include',redirect:'follow'});
      if (!res.ok || /login\.php/i.test(res.url)) break;
      await removeQueue(row.id);
    } catch (_) { break; }
  }
}
self.addEventListener('install',e=>e.waitUntil((async()=>{
  const c=await caches.open(STATIC_CACHE);
  await c.addAll(['/mobile/offline.html','/mobile/mobile.js','/mobile/manifest.webmanifest']);
  await self.skipWaiting();
})()));
self.addEventListener('activate',e=>e.waitUntil((async()=>{
  const keys=await caches.keys();
  await Promise.all(keys.filter(k=>k.startsWith('lab-logbook-mobile-') && k!==STATIC_CACHE && !k.includes('-pages-')).map(k=>caches.delete(k)));
  await self.clients.claim();
})()));
self.addEventListener('message',e=>{
  const d=e.data||{};
  if(d.type==='SET_USER') activeUser=String(d.user||'guest');
  if(d.type==='FLUSH_QUEUE') { activeUser=String(d.user||activeUser); e.waitUntil(flush(activeUser)); }
});
self.addEventListener('fetch',e=>{
  const req=e.request, url=new URL(req.url);
  if(url.origin!==location.origin) return;
  if(req.method==='POST') {
    e.respondWith((async()=>{
      if(self.navigator.onLine) {
        try { return await fetch(req.clone()); } catch (_) {}
      }
      if(activeUser==='guest') return new Response('<!doctype html><meta charset="utf-8"><h2>Login diperlukan</h2><p>Login harus dilakukan saat terhubung ke server. Silakan kembali setelah online.</p>',{headers:{'Content-Type':'text/html; charset=utf-8'},status:503});
      try {
        await queuePost(req,activeUser);
        return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><h2>Disimpan dalam antrean offline</h2><p>Data formulir tersimpan pada perangkat ini. Data akan dikirim ke server setelah koneksi kembali dan akun yang sama aktif.</p><p><a href="javascript:history.back()">Kembali</a></p>',{headers:{'Content-Type':'text/html; charset=utf-8'},status:200});
      } catch (_) {
        return new Response('Gagal menyimpan antrean offline. Jangan tutup halaman; salin data terlebih dahulu.',{status:507});
      }
    })());
    return;
  }
  if(req.method!=='GET') return;
  const isPage=req.mode==='navigate' || (req.headers.get('accept')||'').includes('text/html');
  if(isPage) {
    e.respondWith((async()=>{
      const cacheName=VERSION+'-pages-'+activeUser;
      try {
        const res=await fetch(req);
        if(res.ok && (res.headers.get('content-type')||'').includes('text/html')) {
          const c=await caches.open(cacheName); c.put(req,res.clone());
        }
        return res;
      } catch (_) {
        const c=await caches.open(cacheName);
        return (await c.match(req)) || (await caches.match('/mobile/offline.html'));
      }
    })());
  } else if(url.pathname.startsWith('/assets/') || url.pathname.startsWith('/mobile/')) {
    e.respondWith((async()=>{
      const c=await caches.open(STATIC_CACHE);
      const hit=await c.match(req); if(hit) return hit;
      try { const res=await fetch(req); if(res.ok)c.put(req,res.clone()); return res; }
      catch(_){ return new Response('',{status:504}); }
    })());
  }
});
