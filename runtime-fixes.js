(function(){
  if(window.__fdbRuntimeFixesLoaded)return;
  window.__fdbRuntimeFixesLoaded=true;

  var style=document.createElement('style');
  style.textContent=`
    .fdbSignatureLocked{display:none!important}
    .badge.fdbOverdue{background:#fde9e7!important;color:#a43b32!important}
  `;
  document.head.appendChild(style);

  function configureSignatureGate(){
    var code=document.getElementById('electronicSignatureCode');
    var canvas=document.getElementById('electronicSignatureCanvas');
    if(!code||!canvas||code.__fdbRuntimeGate)return;
    code.__fdbRuntimeGate=true;
    code.setAttribute('autocomplete','one-time-code');

    var label=canvas.previousElementSibling;
    var clearButton=canvas.nextElementSibling;
    var consent=clearButton&&clearButton.nextElementSibling;
    var confirmButton=consent&&consent.nextElementSibling;
    var gated=[label,canvas,clearButton,consent,confirmButton].filter(Boolean);

    var hint=document.createElement('div');
    hint.className='muted';
    hint.id='electronicSignatureGateHint';
    hint.textContent='Digite os 6 números enviados por e-mail para liberar o campo de assinatura.';
    code.insertAdjacentElement('afterend',hint);

    function update(){
      var digits=String(code.value||'').replace(/\D/g,'').slice(0,6);
      if(code.value!==digits)code.value=digits;
      var ready=digits.length===6;
      gated.forEach(function(element){element.classList.toggle('fdbSignatureLocked',!ready)});
      hint.classList.toggle('hidden',ready);
      if(ready&&typeof window.initElectronicSignatureCanvas==='function'){
        setTimeout(function(){try{window.initElectronicSignatureCanvas()}catch(_){}},0);
      }
    }
    code.addEventListener('input',update);
    update();
  }

  function enforceHomeOrder(){
    var home=document.getElementById('home');
    var campaigns=document.getElementById('campaignArea');
    var categories=document.getElementById('homeCategories');
    var listings=document.getElementById('homeListings');
    var partners=document.getElementById('homePartnersSection');
    if(!home||!campaigns||!categories||!listings||!partners)return;

    var campaignTitle=campaigns.previousElementSibling;
    var categoryTitle=categories.previousElementSibling;
    if(!campaignTitle||!categoryTitle)return;
    campaignTitle.textContent='Publicidade';

    var desired=[campaignTitle,campaigns,partners,categoryTitle,categories,listings];
    var current=Array.prototype.slice.call(home.children);
    var positions=desired.map(function(node){return current.indexOf(node)});
    var alreadyOrdered=positions.every(function(pos,i){return pos>=0&&(i===0||pos===positions[i-1]+1)});
    if(alreadyOrdered&&positions[positions.length-1]===current.length-1)return;
    desired.forEach(function(node){home.appendChild(node)});
  }

  function parseBrazilianDate(text){
    var match=String(text||'').match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if(!match)return null;
    var date=new Date(Number(match[3]),Number(match[2])-1,Number(match[1]));
    return isNaN(date.getTime())?null:date;
  }

  function decorateInstallmentActions(){
    var today=new Date();
    today.setHours(0,0,0,0);
    document.querySelectorAll('[id^="installmentRow"] button').forEach(function(button){
      var label=(button.textContent||'').trim().toLowerCase();
      if(label!=='adiantar/pagar parcela'&&label!=='enviar comprovante'&&label!=='antecipar parcela')return;
      var row=button.closest('[id^="installmentRow"]');
      if(!row)return;
      var due=parseBrazilianDate(row.textContent);
      if(!due)return;
      due.setHours(0,0,0,0);
      if(due>today){button.textContent='Antecipar parcela'}
      else if(label==='antecipar parcela'){button.textContent='Enviar comprovante'}
    });
  }

  function decorateOverdue(){
    document.querySelectorAll('.badge').forEach(function(badge){
      if((badge.textContent||'').trim().toLowerCase()==='em atraso')badge.classList.add('fdbOverdue');
    });
  }

  var scheduled=false;
  function apply(){
    scheduled=false;
    configureSignatureGate();
    enforceHomeOrder();
    decorateInstallmentActions();
    decorateOverdue();
  }
  function schedule(){
    if(scheduled)return;
    scheduled=true;
    setTimeout(apply,0);
  }

  var observer=new MutationObserver(schedule);
  observer.observe(document.documentElement,{childList:true,subtree:true});
  apply();
})();
