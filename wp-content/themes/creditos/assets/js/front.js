(()=>{
  const cfg=window.CreditOSConfig||{};

  // Mobile navigation is generated from the desktop navigation so every menu item stays connected.
  const menuButton=document.querySelector('.mobile-menu-btn');
  if(menuButton){
    const overlay=document.createElement('div');
    overlay.setAttribute('aria-hidden','true');
    Object.assign(overlay.style,{position:'fixed',inset:'0',zIndex:'450',display:'none',background:'rgba(31,24,48,.28)',backdropFilter:'blur(14px)',padding:'14px'});
    const panel=document.createElement('div');
    Object.assign(panel.style,{marginLeft:'auto',width:'min(390px,100%)',height:'100%',overflow:'auto',background:'rgba(255,255,255,.98)',borderRadius:'24px',padding:'20px',boxShadow:'0 30px 100px rgba(58,40,105,.22)'});
    const closeMenu=document.createElement('button');
    closeMenu.type='button';closeMenu.textContent='×';closeMenu.setAttribute('aria-label','Close menu');
    Object.assign(closeMenu.style,{float:'right',width:'44px',height:'44px',border:'0',borderRadius:'12px',background:'#f4efff',fontSize:'24px',cursor:'pointer'});
    const links=document.createElement('div');
    Object.assign(links.style,{clear:'both',display:'grid',gap:'8px',paddingTop:'22px'});
    [...document.querySelectorAll('.nav-links a,.nav-actions a')].forEach(a=>{
      const c=a.cloneNode(true);
      Object.assign(c.style,{display:'block',padding:'13px 14px',border:'1px solid rgba(100,80,150,.13)',borderRadius:'13px',background:'#fff',fontWeight:'850',fontSize:'13px'});
      c.addEventListener('click',()=>hideMenu());
      links.appendChild(c);
    });
    panel.append(closeMenu,links);overlay.appendChild(panel);document.body.appendChild(overlay);
    function showMenu(){overlay.style.display='block';overlay.setAttribute('aria-hidden','false');menuButton.setAttribute('aria-expanded','true');document.body.style.overflow='hidden';}
    function hideMenu(){overlay.style.display='none';overlay.setAttribute('aria-hidden','true');menuButton.setAttribute('aria-expanded','false');document.body.style.overflow='';}
    menuButton.addEventListener('click',showMenu);closeMenu.addEventListener('click',hideMenu);overlay.addEventListener('click',e=>{if(e.target===overlay)hideMenu();});
  }

  const modal=document.getElementById('creditosOnboarding');
  if(!modal) return;
  const steps=[...modal.querySelectorAll('.onboard-step')];
  const bars=[...modal.querySelectorAll('.onboard-progress i')];
  const close=modal.querySelector('.onboard-close');
  const next=document.getElementById('onboardNext');
  const back=document.getElementById('onboardBack');
  const choices=[...modal.querySelectorAll('.choice')];
  const consent=document.getElementById('consentCheck');
  const finalLink=modal.querySelector('[href*="dashboard"]');
  let step=0, journey='';

  const goals=()=>[...modal.querySelectorAll('.goal-option input:checked')].map(i=>i.value);
  function render(){
    steps.forEach((s,i)=>s.classList.toggle('active',i===step));
    bars.forEach((b,i)=>b.classList.toggle('active',i<=step));
    back.style.visibility=step===0||step===3?'hidden':'visible';
    next.style.display=step===3?'none':'inline-flex';
    if(step===3){
      document.getElementById('summaryJourney').textContent=journey||'Personal';
      document.getElementById('summaryGoals').textContent=goals().length;
      if(finalLink) finalLink.href=cfg.loggedIn?cfg.dashboardUrl:cfg.loginUrl;
    }
  }
  function openModal(e){if(e)e.preventDefault();modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';render();}
  function closeModal(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
  async function savePayload(payload){
    const r=await fetch(cfg.restUrl+'onboarding',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},body:JSON.stringify(payload)});
    if(!r.ok){const e=await r.json().catch(()=>({}));throw new Error(e.message||'Unable to save onboarding.');}
    return r.json();
  }
  async function persist(){
    const payload={journey,goals:goals(),consented:!!consent?.checked};
    if(!cfg.loggedIn){localStorage.setItem('creditos_pending_onboarding',JSON.stringify(payload));return true;}
    await savePayload(payload);localStorage.removeItem('creditos_pending_onboarding');return true;
  }

  // If a prospect completed onboarding before login, persist it after authentication.
  if(cfg.loggedIn){
    const pending=localStorage.getItem('creditos_pending_onboarding');
    if(pending){try{const payload=JSON.parse(pending);savePayload(payload).then(()=>localStorage.removeItem('creditos_pending_onboarding')).catch(()=>{});}catch(e){localStorage.removeItem('creditos_pending_onboarding');}}
  }

  // Connect every front-page CTA and remove dead '#' links.
  const scrollTargets={
    'browse learning':'resources',
    'browse resources':'resources',
    'explore insights':'resources',
    'personal creditos':'roadmaps',
    'business creditos':'roadmaps',
    'funding readiness':'features',
    'creditos ai':'features',
    'dispute center':'features',
    'crm':'features',
    'privacy':'company-info',
    'terms':'company-info',
    'security':'company-info',
    'pricing':'start',
    'contact':'start'
  };
  const externalTargets={
    'about legacy x firm':'https://legacyxfirm.us/'
  };
  document.querySelectorAll('a').forEach(a=>{
    const href=(a.getAttribute('href')||'').trim();
    if(href!=='#') return;
    const text=(a.textContent||'').trim().toLowerCase();
    const externalKey=Object.keys(externalTargets).find(k=>text.includes(k));
    if(externalKey){a.href=externalTargets[externalKey];return;}
    const key=Object.keys(scrollTargets).find(k=>text.includes(k));
    if(key){
      a.href='#'+scrollTargets[key];
      return;
    }
    a.href='#start';
  });

  // Add a stable destination for legal/company footer links without creating dead pages.
  const footer=document.querySelector('footer');
  if(footer && !document.getElementById('company-info')) footer.id='company-info';

  document.querySelectorAll('a.btn,button.btn').forEach(el=>{
    const t=(el.textContent||'').toLowerCase();
    if(t.includes('get started')||t.includes('start your creditos')||t.includes('request a guided demo')) el.addEventListener('click',openModal);
  });
  document.querySelectorAll('.audience-card a[href="#start"]').forEach(a=>a.addEventListener('click',openModal));

  choices.forEach(c=>c.addEventListener('click',()=>{choices.forEach(x=>x.classList.remove('selected'));c.classList.add('selected');journey=c.dataset.journey;}));
  next?.addEventListener('click',async()=>{
    if(step===0&&!journey){choices[0]?.focus();return;}
    if(step===2&&!consent?.checked){consent?.focus();return;}
    if(step===2){next.disabled=true;next.textContent='Saving…';try{await persist();step++;render();}catch(err){alert(err.message);}finally{next.disabled=false;next.textContent='Continue →';}return;}
    if(step<3){step++;render();}
  });
  back?.addEventListener('click',()=>{if(step>0){step--;render();}});close?.addEventListener('click',closeModal);
  modal.addEventListener('click',e=>{if(e.target===modal)closeModal();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('open'))closeModal();});
})();
