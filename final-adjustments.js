(function(){
  if(window.__fdbFinalAdjustmentsLoaded)return;
  window.__fdbFinalAdjustmentsLoaded=true;

  var style=document.createElement('style');
  style.textContent=`
    .fdbSignatureLocked{display:none!important}
    .partnerSection{margin-top:18px}
    .partnerCarousel{display:flex;gap:12px;overflow-x:auto;padding:2px 0 8px;scroll-snap-type:x mandatory;scrollbar-width:none}
    .partnerCarousel::-webkit-scrollbar{display:none}
    .partnerCard{flex:0 0 78%;max-width:310px;scroll-snap-align:start;border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;box-shadow:0 5px 16px #00000008}
    .partnerMedia{height:145px;background:linear-gradient(135deg,#171717,#d49a13);display:grid;place-items:center;overflow:hidden}
    .partnerMedia img{width:100%;height:100%;object-fit:cover}
    .partnerInitial{width:68px;height:68px;border-radius:50%;display:grid;place-items:center;background:#111;color:var(--g2);font-size:28px;font-weight:900}
    .partnerBody{padding:13px}
    .partnerPlatform{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;color:#8b6410}
    .partnerName{display:block;font-size:15px;margin:4px 0 6px}
    .partnerText{font-size:12px;color:#666;line-height:1.4;min-height:34px}
    .partnerLink{font-size:11px;font-weight:900;color:#8b6410;margin-top:8px}
  `;
  document.head.appendChild(style);

  function safeText(value){
    return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]});
  }

  function configureSignatureGate(){
    var code=document.getElementById('electronicSignatureCode');
    var canvas=document.getElementById('electronicSignatureCanvas');
    if(!code||!canvas||code.__fdbFinalGate)return;
    code.__fdbFinalGate=true;
    code.setAttribute('autocomplete','one-time-code');
    code.setAttribute('aria-describedby','electronicSignatureGateHint');

    var label=canvas.previousElementSibling;
    var clearButton=canvas.nextElementSibling;
    var consent=clearButton&&clearButton.nextElementSibling;
    var confirmButton=consent&&consent.nextElementSibling;
    var gated=[label,canvas,clearButton,consent,confirmButton].filter(Boolean);

    var hint=document.createElement('div');
    hint.id='electronicSignatureGateHint';
    hint.className='muted';
    hint.textContent='Digite os 6 números enviados por e-mail para liberar o campo de assinatura.';
    code.insertAdjacentElement('afterend',hint);

    function update(){
      var digits=String(code.value||'').replace(/\D/g,'');
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

  function arrangeHome(){
    var home=document.getElementById('home');
    var campaigns=document.getElementById('campaignArea');
    var categories=document.getElementById('homeCategories');
    if(!home||!campaigns||!categories)return;

    var campaignTitle=campaigns.previousElementSibling;
    var categoryTitle=categories.previousElementSibling;
    if(!campaignTitle||!categoryTitle)return;
    campaignTitle.textContent='Publicidade';

    if(campaignTitle.nextElementSibling!==campaigns)return;
    home.insertBefore(campaignTitle,categoryTitle);
    home.insertBefore(campaigns,categoryTitle);

    if(!document.getElementById('homePartnersSection')){
      var section=document.createElement('div');
      section.id='homePartnersSection';
      section.className='partnerSection';
      section.innerHTML='<div class="section">Quem está com a gente</div><div id="homePartners" class="partnerCarousel"><div class="card muted" style="flex:0 0 78%">Carregando parceiros...</div></div>';
      home.insertBefore(section,categoryTitle);
      loadPartners();
    }
  }

  async function loadPartners(){
    var area=document.getElementById('homePartners');
    var section=document.getElementById('homePartnersSection');
    if(!area||!section)return;
    try{
      var response=await fetch('https://api.nofiodobigode.app.br/api/v1/community-partners',{headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Falha ao carregar parceiros');
      var items=await response.json();
      if(!Array.isArray(items)||!items.length){section.classList.add('hidden');return;}
      area.innerHTML=items.map(function(item){
        var image=item.post_image_url||item.featured_image_url||item.avatar_url||'';
        var target=item.post_url||item.profile_url||'#';
        var text=item.post_text||item.audience_label||item.description||'Conheça quem ajuda a espalhar o Fio do Bigode.';
        var platform=item.platform||'parceiro';
        return '<a class="partnerCard" href="'+safeText(target)+'" target="_blank" rel="noopener noreferrer">'
          +'<div class="partnerMedia">'+(image?'<img src="'+safeText(image)+'" alt="">':'<span class="partnerInitial">'+safeText(String(item.name||'P').charAt(0))+'</span>')+'</div>'
          +'<div class="partnerBody"><span class="partnerPlatform">'+safeText(platform)+'</span><b class="partnerName">'+safeText(item.name||'Parceiro Fio do Bigode')+'</b><div class="partnerText">'+safeText(text)+'</div><div class="partnerLink">Ver publicação/perfil →</div></div></a>';
      }).join('');
    }catch(_){
      section.classList.add('hidden');
    }
  }

  function decorateRating(){
    var box=document.getElementById('dealClosingRating');
    if(!box||box.__fdbBilateral)return;
    box.__fdbBilateral=true;
    var title=box.querySelector('h3');
    if(title)title.textContent='Avaliação entre comprador e vendedor';
    var note=document.createElement('div');
    note.className='muted';
    note.style.marginBottom='8px';
    note.textContent='Cada parte avalia a outra com 1 a 5 bigodinhos. A sua avaliação não revela a nota da outra parte antes do fechamento.';
    title&&title.insertAdjacentElement('afterend',note);
  }

  var observer=new MutationObserver(function(){
    configureSignatureGate();
    arrangeHome();
    decorateRating();
  });
  observer.observe(document.documentElement,{childList:true,subtree:true});
  configureSignatureGate();
  arrangeHome();
  decorateRating();
})();
