(()=>{
  const cfg=window.CreditOSConfig||{};
  const form=document.getElementById('creditos-report-form');
  const fileInput=document.getElementById('creditos-report-file');
  const fileName=document.getElementById('creditos-file-name');
  const message=document.getElementById('creditos-import-message');
  const list=document.getElementById('creditos-report-list');
  const review=document.getElementById('creditos-report-review');
  const refreshBtn=document.getElementById('creditos-refresh-reports');
  const providerList=document.getElementById('creditos-provider-list');
  const providerMessage=document.getElementById('creditos-connection-message');
  const refreshConnections=document.getElementById('creditos-refresh-connections');
  let selectedReportId=0;

  async function api(path,options={}){
    const headers={'X-WP-Nonce':cfg.nonce,...(options.headers||{})};
    if(!(options.body instanceof FormData))headers['Content-Type']='application/json';
    const r=await fetch(cfg.restUrl+path,{...options,headers,cache:'no-store'});
    if(r.status===401||r.status===403){const payload=await r.json().catch(()=>({}));if(r.status===401&&cfg.loginUrl){window.location.href=cfg.loginUrl;return null;}throw new Error(payload.message||'Permission denied.');}
    const payload=await r.json().catch(()=>({}));
    if(!r.ok){const err=new Error(payload.message||'CreditOS request could not be completed.');err.status=r.status;err.payload=payload;throw err;}
    return payload;
  }

  function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
  function prettyStatus(v){return String(v||'pending').replaceAll('_',' ').replace(/\b\w/g,m=>m.toUpperCase());}
  function formatDate(v){if(!v)return 'Not provided';const d=new Date(v.replace(' ','T'));return Number.isNaN(d.getTime())?v:d.toLocaleDateString();}
  function uploadResultText(report){const s=report?.parser_status||'pending';if(s==='ready_for_review'||s==='normalized')return 'Report processed successfully. Your credit data is ready for review below.';if(s==='needs_ocr')return 'Report uploaded, but this PDF needs OCR review before CreditOS can read it.';if(s==='needs_review')return report?.error_message||'Report uploaded, but parser review is required.';if(s==='failed')return report?.error_message||'The report uploaded but processing failed.';return 'Report uploaded successfully. CreditOS is processing the report now.';}

  document.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener('click',e=>{const id=(a.getAttribute('href')||'').slice(1);const el=document.getElementById(id);if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'','#'+id);}}));
  fileInput?.addEventListener('change',()=>{fileName.textContent=fileInput.files?.[0]?.name||'No file selected';});
  form?.addEventListener('submit',async e=>{e.preventDefault();const file=fileInput.files?.[0];if(!file){message.className='import-message error';message.textContent='Choose a report first.';return;}const button=form.querySelector('button[type=submit]');button.disabled=true;button.textContent='Processing…';message.className='import-message';message.textContent='Uploading securely, extracting report text, and preparing your CreditOS review…';try{const data=new FormData(form);const result=await api('reports/import',{method:'POST',body:data});if(!result)return;const bad=['needs_ocr','needs_review','failed'].includes(result.report?.parser_status);message.className=bad?'import-message error':'import-message success';message.textContent=uploadResultText(result.report);form.reset();fileName.textContent='No file selected';await loadReports(result.report_id,true);}catch(err){message.className='import-message error';message.textContent=err.message;}finally{button.disabled=false;button.textContent='Import Report →';}});

  function providerCard(p){const live=['ready','connected','active','authorization_pending'].includes(p.status);const connected=['connected','active'].includes(p.status);const label=connected?'Connected':live?'Connect':'Provider setup required';return `<article class="provider-card" data-provider="${esc(p.key)}"><div class="provider-badge">${esc((p.bureau||'multi').toUpperCase())}</div><h3>${esc(p.name)}</h3><p>${esc(p.description||'')}</p><div class="provider-meta"><span>Status</span><strong>${esc(prettyStatus(p.status))}</strong></div><button class="reports-btn ${live&&!connected?'primary':''} connect-provider" type="button" data-provider="${esc(p.key)}" ${connected?'disabled':''}>${esc(label)}</button></article>`;}
  async function loadConnections(){if(!providerList)return;providerList.innerHTML='<div class="empty-state">Loading connection options…</div>';try{const data=await api('credit-connections');if(!data)return;providerList.innerHTML=(data.providers||[]).map(providerCard).join('')||'<div class="empty-state">No credit-data providers are registered yet.</div>';providerList.querySelectorAll('.connect-provider').forEach(btn=>btn.addEventListener('click',()=>startConnection(btn.dataset.provider,btn)));if(providerMessage&&!data.consent_valid){providerMessage.className='import-message error';providerMessage.textContent='Complete CreditOS onboarding and consent before connecting credit data.';}}catch(err){providerList.innerHTML=`<div class="empty-state">${esc(err.message)}</div>`;}}
  async function startConnection(provider,button){if(!provider)return;providerMessage.className='import-message';providerMessage.textContent='Preparing secure provider connection…';button.disabled=true;try{const result=await api(`credit-connections/${encodeURIComponent(provider)}/start`,{method:'POST',body:JSON.stringify({return_url:window.location.href})});if(!result)return;providerMessage.className='import-message success';providerMessage.textContent='Secure authorization started. Redirecting to the approved provider…';if(result.authorization_url)window.location.href=result.authorization_url;}catch(err){providerMessage.className=err.status===503?'import-message':'import-message error';providerMessage.textContent=err.message;button.disabled=false;}}

  function renderList(reports,selectedId=selectedReportId){
    if(!list)return;
    if(!reports?.length){list.innerHTML='<div class="empty-state">No credit reports have been imported yet.</div>';return;}
    list.innerHTML=reports.map(r=>{const active=Number(r.id)===Number(selectedId);return `<div class="report-row ${active?'is-selected':''}" data-report-id="${Number(r.id)}"><div><strong>${esc(r.source_filename||'Credit report')}</strong><small>${esc((r.bureau||'multi').toUpperCase())} · ${esc((r.source_format||'file').toUpperCase())}</small></div><div><span>Report date</span><strong>${esc(formatDate(r.report_date))}</strong></div><div><span>Imported</span><strong>${esc(formatDate(r.imported_at))}</strong></div><div><span>Processing</span><span class="report-status">${esc(prettyStatus(r.parser_status))}</span></div><div><button class="report-review-action" type="button" data-review-report="${Number(r.id)}" ${active?'aria-current="true"':''}>${active?'Viewing':'Review →'}</button></div></div>`;}).join('');
    list.querySelectorAll('[data-review-report]').forEach(btn=>btn.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();selectReport(btn.dataset.reviewReport);}));
    list.querySelectorAll('.report-row[data-report-id]').forEach(row=>row.addEventListener('click',e=>{if(e.target.closest('[data-review-report]'))return;selectReport(row.dataset.reportId);}));
  }

  async function selectReport(id){
    id=Number(id);if(!id)return;
    selectedReportId=id;
    history.replaceState(null,'',`#review-report-${id}`);
    await loadReport(id,true);
  }

  async function loadReports(selectedId=selectedReportId,openSelected=false){
    try{const data=await api('reports');if(!data)return;const reports=data.reports||[];if(selectedId)selectedReportId=Number(selectedId);renderList(reports,selectedReportId);if(openSelected&&selectedReportId)await loadReport(selectedReportId,true);}catch(err){if(list)list.innerHTML=`<div class="empty-state">${esc(err.message)}</div>`;}
  }

  function table(title,headers,rows,fields){if(!rows?.length)return `<div class="empty-state">No ${esc(title.toLowerCase())} records normalized for this report yet.</div>`;return `<h3>${esc(title)}</h3><div class="table-scroll"><table class="review-table"><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${fields.map(f=>`<td>${esc(r[f]??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;}

  async function loadReport(id,scroll=true){
    if(!review)return;id=Number(id);if(!id)return;selectedReportId=id;review.innerHTML='<div class="empty-state">Loading selected report…</div>';
    try{const r=await api('reports/'+id+'?t='+Date.now());if(!r)return;const counts={tradelines:r.tradelines?.length||0,collections:r.collections?.length||0,inquiries:r.inquiries?.length||0,personal:r.personal_information?.length||0};review.innerHTML=`<div class="review-summary"><div class="review-stat"><small>Tradelines</small><strong>${counts.tradelines}</strong></div><div class="review-stat"><small>Collections</small><strong>${counts.collections}</strong></div><div class="review-stat"><small>Inquiries</small><strong>${counts.inquiries}</strong></div><div class="review-stat"><small>Personal info</small><strong>${counts.personal}</strong></div></div><p><strong>${esc(r.source_filename||'Credit report')}</strong> · ${esc((r.bureau||'multi').toUpperCase())} · ${esc(prettyStatus(r.parser_status))}${r.error_message?` · ${esc(r.error_message)}`:''}</p>${table('Tradelines',['Creditor','Bureau','Type','Balance','Limit','Status'],r.tradelines,['creditor_name','bureau','account_type','balance','credit_limit','status'])}${table('Collections',['Collector','Original creditor','Bureau','Balance','Status'],r.collections,['collector_name','original_creditor','bureau','balance','status'])}${table('Inquiries',['Creditor','Bureau','Type','Date'],r.inquiries,['creditor_name','bureau','inquiry_type','inquiry_date'])}${table('Personal Information',['Type','Value','Bureau'],r.personal_information,['info_type','info_value','bureau'])}`;if(scroll)document.getElementById('review')?.scrollIntoView({behavior:'smooth',block:'start'});const data=await api('reports');if(data)renderList(data.reports||[],id);}catch(err){review.innerHTML=`<div class="empty-state">${esc(err.message)}</div>`;}
  }

  function restoreReportFromHash(){const m=window.location.hash.match(/^#review-report-(\d+)$/);if(m){selectedReportId=Number(m[1]);loadReports(selectedReportId,true);return true;}return false;}
  refreshBtn?.addEventListener('click',()=>loadReports(selectedReportId,false));
  refreshConnections?.addEventListener('click',()=>loadConnections());
  window.addEventListener('hashchange',restoreReportFromHash);
  loadConnections();
  if(!restoreReportFromHash())loadReports();
})();
