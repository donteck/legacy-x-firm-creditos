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

  // Create concrete destinations for navigation items represented by quick-access cards.
  document.querySelectorAll('.quick').forEach(card=>{
    const label=(card.querySelector('strong')?.textContent||'').toLowerCase();
    if(label.includes('create a task')) card.id='tasks';
    if(label.includes('funding readiness')) card.id='funding';
  });
  document.querySelector('a[href="#reports"]')?.setAttribute('href','#health');

  function go(id){const el=document.getElementById(id);if(el){el.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'','#'+id);}}

  // Every dashboard text link now has a live destination.
  const actionTargets={
    'view all roadmaps':'roadmaps','manage goals':'health','view full analysis':'health','open full dispute center':'disputes','open secure vault':'documents','open crm workspace':'business','open ai center':'ai','manage plan':'documents'
  };
  document.querySelectorAll('a').forEach(a=>{
    if((a.getAttribute('href')||'').trim()!=='#')return;
    const text=(a.textContent||'').trim().toLowerCase();
    const key=Object.keys(actionTargets).find(k=>text.includes(k));
    a.href='#'+(key?actionTargets[key]:'overview');
  });

  document.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener('click',e=>{
    const id=(a.getAttribute('href')||'').slice(1);if(!id)return;
    const el=document.getElementById(id);if(el){e.preventDefault();go(id);}
  }));

  const helpBtn=[...document.querySelectorAll('.icon-btn')].find(b=>(b.getAttribute('aria-label')||'').toLowerCase()==='help');
  helpBtn?.addEventListener('click',()=>{go('ai');setTimeout(()=>alert('CreditOS Help: use Roadmaps for guided steps, Disputes for correction workflows, Documents for your secure vault, and AI Center for guided assistance.'),250);});

  const search=document.querySelector('.search input[type="search"]');
  const searchMap={dashboard:'overview',overview:'overview',roadmap:'roadmaps',personal:'roadmaps',business:'business',report:'health',credit:'health',health:'health',dispute:'disputes',task:'tasks',document:'documents',vault:'documents',ai:'ai',funding:'funding',crm:'business',billing:'documents',plan:'documents'};
  search?.addEventListener('keydown',e=>{
    if(e.key!=='Enter')return;
    e.preventDefault();
    const q=(search.value||'').trim().toLowerCase();
    if(!q)return;
    const key=Object.keys(searchMap).find(k=>q.includes(k));
    if(key){go(searchMap[key]);return;}
    alert('No matching CreditOS section was found. Try: roadmap, credit report, dispute, task, document, AI, funding, CRM, or billing.');
  });

  document.querySelectorAll('.next-action .btn').forEach(btn=>btn.addEventListener('click',()=>{const staff=document.body.classList.contains('staff-mode');go(staff?'disputes':'health');}));

  document.querySelectorAll('.attention').forEach(card=>{
    card.style.cursor='pointer';card.tabIndex=0;
    const run=()=>{const t=(card.textContent||'').toLowerCase();if(t.includes('follow-up'))go('disputes');else if(t.includes('business'))go('business');else go('funding');};
    card.addEventListener('click',run);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});
  });

  document.querySelectorAll('.roadmap-card').forEach(card=>{card.style.cursor='pointer';card.tabIndex=0;const run=()=>go('roadmaps');card.addEventListener('click',run);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});});
  document.querySelectorAll('.goal-journey').forEach(card=>{card.style.cursor='pointer';card.tabIndex=0;const run=()=>go((card.textContent||'').toLowerCase().includes('business')?'business':'health');card.addEventListener('click',run);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});});

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
      if(label.includes('funding')){go('funding');return;}
      if(label.includes('ai')){go('ai');alert('CreditOS AI workspace is prepared in the interface. Provider credentials and approval controls will be connected in the AI integration phase.');}
    }
    card.addEventListener('click',run);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});
  });

  const userBlock=document.querySelector('.topbar .user');
  if(userBlock){userBlock.style.cursor='pointer';userBlock.tabIndex=0;const run=()=>go('overview');userBlock.addEventListener('click',run);userBlock.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();run();}});}

  refresh();
})();
