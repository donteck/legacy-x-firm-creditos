(()=>{
  const cfg=window.CreditOSConfig||{};
  const form=document.getElementById('creditos-report-form');
  const fileInput=document.getElementById('creditos-report-file');
  const fileName=document.getElementById('creditos-file-name');
  const message=document.getElementById('creditos-import-message');
  const list=document.getElementById('creditos-report-list');
  const review=document.getElementById('creditos-report-review');
  const refreshBtn=document.getElementById('creditos-refresh-reports');

  async function api(path,options={}){
    const headers={'X-WP-Nonce':cfg.nonce,...(options.headers||{})};
    if(!(options.body instanceof FormData))headers['Content-Type']='application/json';
    const r=await fetch(cfg.restUrl+path,{...options,headers});
    if(r.status===401||r.status===403){const payload=await r.json().catch(()=>({}));if(r.status===401&&cfg.loginUrl){window.location.href=cfg.loginUrl;return null;}throw new Error(payload.message||'Permission denied.');}
    const payload=await r.json().catch(()=>({}));
    if(!r.ok)throw new Error(payload.message||'CreditOS request could not be completed.');
    return payload;
  }

  function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
  function prettyStatus(v){return String(v||'pending').replaceAll('_',' ').replace(/\b\w/g,m=>m.toUpperCase());}
  function formatDate(v){if(!v)return 'Not provided';const d=new Date(v.replace(' ','T'));return Number.isNaN(d.getTime())?v:d.toLocaleDateString();}

  fileInput?.addEventListener('change',()=>{fileName.textContent=fileInput.files?.[0]?.name||'No file selected';});

  form?.addEventListener('submit',async e=>{
    e.preventDefault();
    const file=fileInput.files?.[0];
    if(!file){message.className='import-message error';message.textContent='Choose a report first.';return;}
    const button=form.querySelector('button[type=submit]');
    button.disabled=true;button.textContent='Importing…';message.className='import-message';message.textContent='Securely importing your report…';
    try{
      const data=new FormData(form);
      const result=await api('reports/import',{method:'POST',body:data});
      if(!result)return;
      message.className='import-message success';
      message.textContent=result.report?.parser_status==='normalized'?'Report imported and normalized. It is ready for review.':'Report imported successfully. It is queued for structured review.';
      form.reset();fileName.textContent='No file selected';
      await loadReports(result.report_id);
    }catch(err){message.className='import-message error';message.textContent=err.message;}
    finally{button.disabled=false;button.textContent='Import Report →';}
  });

  function renderList(reports,selectedId){
    if(!list)return;
    if(!reports?.length){list.innerHTML='<div class="empty-state">No credit reports have been imported yet.</div>';return;}
    list.innerHTML=reports.map(r=>`<button class="report-row" type="button" data-report-id="${Number(r.id)}" aria-label="Review ${esc(r.source_filename||'credit report')}"><div><strong>${esc(r.source_filename||'Credit report')}</strong><small>${esc((r.bureau||'multi').toUpperCase())} · ${esc((r.source_format||'file').toUpperCase())}</small></div><div><span>Report date</span><strong>${esc(formatDate(r.report_date))}</strong></div><div><span>Imported</span><strong>${esc(formatDate(r.imported_at))}</strong></div><div><span>Processing</span><span class="report-status">${esc(prettyStatus(r.parser_status))}</span></div><div><span>${Number(r.id)===Number(selectedId)?'Viewing':'Review →'}</span></div></button>`).join('');
    list.querySelectorAll('[data-report-id]').forEach(btn=>btn.addEventListener('click',()=>loadReport(btn.dataset.reportId)));
  }

  async function loadReports(selectedId){
    try{const data=await api('reports');if(!data)return;renderList(data.reports||[],selectedId);if(selectedId)await loadReport(selectedId);}
    catch(err){if(list)list.innerHTML=`<div class="empty-state">${esc(err.message)}</div>`;}
  }

  function table(title,headers,rows,fields){
    if(!rows?.length)return `<div class="empty-state">No ${esc(title.toLowerCase())} records normalized for this report yet.</div>`;
    return `<h3>${esc(title)}</h3><div class="table-scroll"><table class="review-table"><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${fields.map(f=>`<td>${esc(r[f]??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
  }

  async function loadReport(id){
    if(!review)return;
    review.innerHTML='<div class="empty-state">Loading selected report…</div>';
    try{
      const r=await api('reports/'+id);if(!r)return;
      const counts={tradelines:r.tradelines?.length||0,collections:r.collections?.length||0,inquiries:r.inquiries?.length||0,personal:r.personal_information?.length||0};
      review.innerHTML=`<div class="review-summary"><div class="review-stat"><small>Tradelines</small><strong>${counts.tradelines}</strong></div><div class="review-stat"><small>Collections</small><strong>${counts.collections}</strong></div><div class="review-stat"><small>Inquiries</small><strong>${counts.inquiries}</strong></div><div class="review-stat"><small>Personal info</small><strong>${counts.personal}</strong></div></div><p><strong>${esc(r.source_filename||'Credit report')}</strong> · ${esc(prettyStatus(r.parser_status))}${r.error_message?` · ${esc(r.error_message)}`:''}</p>${table('Tradelines',['Creditor','Bureau','Type','Balance','Limit','Status'],r.tradelines,['creditor_name','bureau','account_type','balance','credit_limit','status'])}${table('Collections',['Collector','Original creditor','Bureau','Balance','Status'],r.collections,['collector_name','original_creditor','bureau','balance','status'])}${table('Inquiries',['Creditor','Bureau','Type','Date'],r.inquiries,['creditor_name','bureau','inquiry_type','inquiry_date'])}${table('Personal Information',['Type','Value','Bureau'],r.personal_information,['info_type','info_value','bureau'])}`;
      document.getElementById('review')?.scrollIntoView({behavior:'smooth',block:'start'});
      const data=await api('reports');if(data)renderList(data.reports||[],id);
    }catch(err){review.innerHTML=`<div class="empty-state">${esc(err.message)}</div>`;}
  }

  refreshBtn?.addEventListener('click',()=>loadReports());
  loadReports();
})();
