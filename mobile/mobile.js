(() => {
  const user = document.querySelector('meta[name="lab-user"]')?.content || 'guest';
  const isOnline = () => navigator.onLine;
  let bar;
  function showBar() {
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'lab-mobile-status';
      bar.style.cssText = 'position:sticky;top:0;z-index:99999;padding:9px 12px;text-align:center;font:600 13px system-ui;background:#e8f1fb;color:#17365d;border-bottom:1px solid #c6d8eb';
      document.body.prepend(bar);
    }
    bar.textContent = isOnline()
      ? 'Terhubung • perubahan tersimpan ke server'
      : 'OFFLINE • data formulir akan diantrekan di perangkat dan dikirim saat koneksi kembali';
    bar.style.background = isOnline() ? '#e8f1fb' : '#fff1d6';
    bar.style.color = isOnline() ? '#17365d' : '#704900';
  }
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
      try {
        const reg = await navigator.serviceWorker.register('/sw.js', {scope: '/'});
        const send = () => {
          const worker = navigator.serviceWorker.controller || reg.active;
          if (worker) worker.postMessage({type:'SET_USER', user});
          if (isOnline() && worker) worker.postMessage({type:'FLUSH_QUEUE', user});
        };
        send();
        navigator.serviceWorker.addEventListener('controllerchange', send);
        window.addEventListener('online', () => { showBar(); send(); });
        window.addEventListener('offline', showBar);
        navigator.serviceWorker.addEventListener('message', e => {
          if (e.data?.type === 'QUEUE_UPDATED') showBar();
        });
        showBar();
      } catch (e) {
        console.warn('Offline worker tidak aktif', e);
      }
    });
  }
  window.addEventListener('online', showBar);
  window.addEventListener('offline', showBar);
})();
