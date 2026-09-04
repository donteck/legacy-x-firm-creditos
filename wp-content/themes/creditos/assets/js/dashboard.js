(()=>{
  const cfg=window.CreditOSConfig||{};
  const roleButtons=[...document.querySelectorAll('.role-switch button')];
  if(!cfg.canStaff){
    roleButtons.filter(b=>b.dataset.role==='staff').forEach(b=>b.remove());
  }
  roleButtons.forEach(btn=>btn.addEventListener('click',()=>{
    roleButtons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.body.classList.toggle('staff-mode',btn.dataset.role==='staff');
  }));

  const nbtn=document.querySelector('.notif-btn');
  const npanel=document.querySelector('.notif-panel');
  nbtn?.addEventListener('click',()=>{const open=npanel?.classList.toggle('open');npanel?.setAttribute('aria-hidden',String(!open));nbtn.querySelector('.notif-dot')?.remove();});
  document.addEventListener('click',e=>{if(npanel?.classList.contains('open')&&!npanel.contains(e.target)&&!nbtn?.contains(e.target)){npanel.classList.remove('open');npanel.setAttribute('aria-hidden','true');}});

  async function api(path,options={}){
    const r=await fetch(cfg.restUrl+path,{...options,headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce,...(options.headers||{})}});
    if(r.status===401||r.status===403){window.location.href=cfg.loginUrl;return null;}
    if(!r.ok) throw new Error('CreditOS data could not be loaded.');
    return r.json();
  }
  function renderNotifications(items){
    if(!npanel||!Array.isArray(items)||!items.length)return;
    npanel.querySelectorAll('.notif-item').forEach(n=>n.remove());
    items.slice(0,6).forEach(item=>{
      const d=document.createElement('div');d.className='notif-item';
      const s=document.createElement('strong');s.textContent=item.title||'CreditOS update';
      const p=document.createElement('p');p.textContent=item.message||'';
      d.append(s,p);npanel.appendChild(d);
    });
  }
  function hydrate(data){
    if(!data)return;
    const status=document.getElementById('creditos-live-status');
    const summary=document.getElementById('creditos-live-summary');
    if(status){status.style.display='grid';}
    if(summary){
      const openTasks=(data.tasks||[]).filter(t=>t.status!=='done').length;
      const activeDisputes=(data.disputes||[]).filter(d=>!['resolved','closed'].includes(d.status)).length;
      summary.textContent=`${openTasks} open tasks · ${activeDisputes} active dispute cases · ${data.documents_count||0} secured documents`;
    }
    renderNotifications(data.notifications);
  }
  api('dashboard').then(hydrate).catch(err=>{
    const summary=document.getElementById('creditos-live-summary');if(summary)summary.textContent=err.message;
  });
})();
