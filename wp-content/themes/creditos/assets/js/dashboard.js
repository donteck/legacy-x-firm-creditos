(()=>{
  const cfg=window.CreditOSConfig||{};
  const roleButtons=[...document.querySelectorAll('.role-switch button')];
  if(!cfg.canStaff){roleButtons.filter(b=>b.dataset.role==='staff').forEach(b=>b.remove());}
  roleButtons.forEach(btn=>btn.addEventListener('click',()=>{roleButtons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.body.classList.toggle('staff-mode',btn.dataset.role==='staff');}));

  const modeButtons=[...document.querySelectorAll('.mode-switch button')];
  modeButtons.forEach(btn=>btn.addEventListener('click',()=>{modeButtons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.body.dataset.creditosMode=(btn.textContent||'combined').trim().toLowerCase();}));

  const nbtn=document.querySelector('.notif-btn');
  const npanel=document.querySelector('.notif-panel');
  nbtn?.addEventListener('click',()=>{const open=npanel?.classList.toggle('open');npanel?.setAttribute('aria-hidden',String(!open));nbtn.querySelector('.notif-dot')?.remove();});
  document.addEventListener('click',e=>{if(npanel?.classList.contains('open')&&!npanel.contains(e.target)&&!nbtn?.contains(e.target)){npanel.classList.remove('open');npanel.setAttribute('aria-hidden','true');}});

  async function api(path,options={}){
    const r=await fetch(cfg.restUrl+path,{...options,headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce,...(options.headers||{})}});
    if(r.status===401||r.status===403){window.location.href=cfg.loginUrl;return null;}
    const payload=await r.json().catch(()=>({}));
    if(!r.ok) throw new Error(payload.message||'CreditOS request could not be completed.');
    return payload;
  }
  function renderNotifications(items){
    if(!npanel||!Array.isArray(items)||!items.length)return;
    npanel.querySelectorAll('.notif-item').forEach(n=>n.remove());
    items.slice(0,6).forEach(item=>{const d=document.createElement('div');d.className='notif-item';const s=document.createElement('strong');s.textContent=item.title||'CreditOS update';const p=document.createElement('p');p.textContent=item.message||'';d.append(s,p);npanel.appendChild(d);});
  }
  function hydrate(data){
    if(!data)return;
    const status=document.getElementById('creditos-live-status');
    const summary=document.getElementById('creditos-live-summary');
    if(status)status.style.display='grid';
    if(summary){const openTasks=(data.tasks||[]).filter(t=>t.status!=='done').length;const activeDisputes=(data.disputes||[]).filter(d=>!['resolved','closed'].includes(d.status)).length;summary.textContent=`${openTasks} open tasks · ${activeDisputes} active dispute cases · ${data.documents_count||0} secured documents`;}
    renderNotifications(data.notifications);
  }
  async function refresh(){try{hydrate(await api('dashboard'));}catch(err){const summary=document.getElementById('creditos-live-summary');if(summary)summary.textContent=err.message;}}

  // Convert all dashboard text actions into working navigation instead of dead links.
  const actionTargets={
    'view all roadmaps':'roadmaps','manage goals':'health','view full analysis':'health','open full dispute center':'disputes','open secure vault':'documents','open crm workspace':'business','open ai center':'ai','manage plan':'documents'
  };
  document.querySelectorAll('a').forEach(a=>{
    if(a.getAttribute('href')!=='#')return;
    const text=(a.textContent||'').trim().toLowerCase();
    const key=Object.keys(actionTargets).find(k=>text.includes(k));
    if(key){a.addEventListener('click',e=>{e.preventDefault();document.getElementById(actionTargets[key])?.scrollIntoView({behavior:'smooth',block:'start'});});}
  });
  document.querySelectorAll('.next-action .btn').forEach(btn=>btn.addEventListener('click',()=>{const staff=document.body.classList.contains('staff-mode');document.getElementById(staff?'disputes':'health')?.scrollIntoView({behavior:'smooth'});}));

  // Quick-access cards execute real database actions where the v0.2 API supports them.
  document.querySelectorAll('.quick').forEach(card=>{
    card.style.cursor='pointer';card.tabIndex=0;
    async function run(){
      const label=(card.querySelector('strong')?.textContent||'').toLowerCase();
      if(label.includes('create a task')){
        const title=window.prompt('Task title');if(!title)return;
        try{await api('tasks',{method:'POST',body:JSON.stringify({title,priority:'normal'})});await refresh();alert('Task saved to CreditOS.');}catch(err){alert(err.message);}return;
      }
      if(label.includes('dispute')){
        const title=window.prompt('Dispute review title');if(!title)return;
        const bureau=window.prompt('Bureau or furnisher (optional)')||'';
        try{await api('disputes',{method:'POST',body:JSON.stringify({title,bureau})});await refresh();alert('Draft dispute review created. Human review is required before correspondence is sent.');}catch(err){alert(err.message);}return;
      }
      if(label.includes('funding')){document.getElementById('roadmaps')?.scrollIntoView({behavior:'smooth'});return;}
      if(label.includes('ai')){alert('CreditOS AI workspace is prepared in the interface. Provider credentials and approval controls will be connected in the AI integration phase.');}
    }
    card.addEventListener('click',run);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});
  });

  refresh();
})();
