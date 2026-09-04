document.addEventListener('DOMContentLoaded',function(){
  if(!document.body.classList.contains('creditos-dashboard')) return;
  var main=document.querySelector('.main');
  if(!main||document.querySelector('.creditos-app-footer')) return;
  var year=new Date().getFullYear();
  var footer=document.createElement('footer');
  footer.className='creditos-app-footer';
  footer.innerHTML='<div class="creditos-app-footer-inner">'+
    '<div><div class="creditos-app-footer-brand"><span class="creditos-app-footer-mark">C</span><span>CreditOS</span></div><div class="creditos-app-footer-copy">Legacy X Firm Credit Operating Solutions · Personal &amp; Business Credit Intelligence, Management &amp; Automation</div></div>'+
    '<div class="creditos-app-footer-links"><a href="/">Main Site</a><a href="#overview">Dashboard</a><a href="#documents">Account</a><span>© '+year+' Legacy X Firm</span></div>'+
  '</div>';
  main.appendChild(footer);
});
