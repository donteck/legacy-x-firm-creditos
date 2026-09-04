(()=>{
  const cfg=window.CreditOSConfig||{};
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
  function openModal(e){
    if(e)e.preventDefault();
    modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';render();
  }
  function closeModal(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
  async function persist(){
    const payload={journey,goals:goals(),consented:!!consent?.checked};
    if(!cfg.loggedIn){
      localStorage.setItem('creditos_pending_onboarding',JSON.stringify(payload));
      return true;
    }
    const r=await fetch(cfg.restUrl+'onboarding',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},body:JSON.stringify(payload)});
    if(!r.ok){const e=await r.json().catch(()=>({}));throw new Error(e.message||'Unable to save onboarding.');}
    localStorage.removeItem('creditos_pending_onboarding');
    return true;
  }

  document.querySelectorAll('a.btn,button.btn').forEach(el=>{
    const t=(el.textContent||'').toLowerCase();
    if(t.includes('get started')||t.includes('start your creditos')||t.includes('request a guided demo')) el.addEventListener('click',openModal);
  });
  choices.forEach(c=>c.addEventListener('click',()=>{choices.forEach(x=>x.classList.remove('selected'));c.classList.add('selected');journey=c.dataset.journey;}));
  next?.addEventListener('click',async()=>{
    if(step===0&&!journey){choices[0]?.focus();return;}
    if(step===2&&!consent?.checked){consent?.focus();return;}
    if(step===2){
      next.disabled=true;next.textContent='Saving…';
      try{await persist();step++;render();}
      catch(err){alert(err.message);}
      finally{next.disabled=false;next.textContent='Continue →';}
      return;
    }
    if(step<3){step++;render();}
  });
  back?.addEventListener('click',()=>{if(step>0){step--;render();}});
  close?.addEventListener('click',closeModal);
  modal.addEventListener('click',e=>{if(e.target===modal)closeModal();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('open'))closeModal();});
})();
