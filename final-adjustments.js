(function(){
  if(window.__fdbFinalAdjustmentsLoaded)return;
  window.__fdbFinalAdjustmentsLoaded=true;

  var style=document.createElement('style');
  style.textContent=`
    .fdbSignatureLocked{display:none!important}
    .badge.fdbOverdue{background:#fde9e7!important;color:#a43b32!important}
    .partnerSection{margin-top:18px}
    .partnerIntro{font-size:12px;color:#666;line-height:1.45;margin:-2px 0 10px}
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
    .partnerEmpty{flex:0 0 88%;border:1px dashed #d7b354;border-radius:18px;padding:16px;background:#fffaf0;color:#665b42;font-size:12px;line-height:1.45}
  `;
  document.head.appendChild(style);

  function safeText(value){
    return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#39;'}[ch]});
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
      if(ready&&typeof window.initElectronicSignatureCanvas==='function')setTimeout(function(){try{window.initElectronicSignatureCanvas()}catch(_){}},0);
    }
    code.addEventListener('input',update);update();
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
    if(campaignTitle.nextElementSibling===campaigns){home.insertBefore(campaignTitle,categoryTitle);home.insertBefore(campaigns,categoryTitle)}
    if(!document.getElementById('homePartnersSection')){
      var section=document.createElement('div');
      section.id='homePartnersSection';section.className='partnerSection';
      section.innerHTML='<div class="section">Quem está com a gente</div><div class="partnerIntro">Criadores, influenciadores e comunidades que apoiam o Fio do Bigode. Toque para conhecer e seguir seus perfis.</div><div id="homePartners" class="partnerCarousel"><div class="partnerEmpty">Carregando perfis parceiros…</div></div>';
      home.insertBefore(section,categoryTitle);loadPartners();
    }
  }

  async function getPartners(){
    var urls=[];
    if(typeof window.API==='string'&&window.API)urls.push(window.API+'/v1/community-partners');
    urls.push('https://fio-do-bigode-prototype-production.up.railway.app/api/v1/community-partners');
    urls.push('https://api.nofiodobigode.app.br/api/v1/community-partners');
    var lastError=null;
    for(var i=0;i<urls.length;i++){
      try{var response=await fetch(urls[i],{headers:{Accept:'application/json'}});if(!response.ok)throw new Error('HTTP '+response.status);var data=await response.json();if(Array.isArray(data))return data;if(Array.isArray(data.data))return data.data}catch(error){lastError=error}
    }
    throw lastError||new Error('Parceiros indisponíveis');
  }

  async function loadPartners(){
    var area=document.getElementById('homePartners');if(!area)return;
    try{
      var items=await getPartners();
      if(!items.length){area.innerHTML='<div class="partnerEmpty"><b>Novos parceiros em breve.</b><br>Os perfis divulgadores cadastrados aparecerão aqui para a comunidade conhecer e seguir.</div>';return}
      area.innerHTML=items.map(function(item){
        var image=item.post_image_url||item.featured_image_url||item.avatar_url||'';
        var target=item.post_url||item.profile_url||'#';
        var text=item.post_text||item.audience_label||item.description||'Conheça quem ajuda a espalhar o Fio do Bigode.';
        var platform=item.platform||'parceiro';
        return '<a class="partnerCard" href="'+safeText(target)+'" target="_blank" rel="noopener noreferrer"><div class="partnerMedia">'+(image?'<img src="'+safeText(image)+'" alt="">':'<span class="partnerInitial">'+safeText(String(item.name||'P').charAt(0))+'</span>')+'</div><div class="partnerBody"><span class="partnerPlatform">'+safeText(platform)+'</span><b class="partnerName">'+safeText(item.name||'Parceiro Fio do Bigode')+'</b><div class="partnerText">'+safeText(text)+'</div><div class="partnerLink">Ver perfil / publicação →</div></div></a>';
      }).join('');
    }catch(_){area.innerHTML='<div class="partnerEmpty"><b>Quem está com a gente</b><br>Não foi possível atualizar os perfis agora. A área permanecerá visível e tentará carregar novamente ao abrir a Home.</div>'}
  }

  function decorateOverdue(){
    document.querySelectorAll('.badge').forEach(function(badge){if((badge.textContent||'').trim().toLowerCase()==='em atraso')badge.classList.add('fdbOverdue')});
  }

  function decorateRating(){
    var box=document.getElementById('dealClosingRating');
    if(!box||box.__fdbBilateral)return;
    box.__fdbBilateral=true;
    var title=box.querySelector('h3');if(title)title.textContent='Avaliação entre comprador e vendedor';
    var note=document.createElement('div');note.className='muted';note.style.marginBottom='8px';note.textContent='Cada parte avalia a outra com 1 a 5 bigodinhos. A sua avaliação não revela a nota da outra parte antes do fechamento.';title&&title.insertAdjacentElement('afterend',note);
  }

  function patchRatingSubmit(){
    if(typeof window.submitDealRating!=='function'||window.submitDealRating.__fdbResilient)return;
    var original=window.submitDealRating;
    var patched=async function(){
      try{return await original.apply(this,arguments)}catch(error){throw error}
    };
    patched.__fdbResilient=true;window.submitDealRating=patched;
    var oldApi=window.apiFetch;
    if(typeof oldApi!=='function'||oldApi.__fdbRatingResilient)return;
    var wrapped=async function(path,opt){
      try{return await oldApi(path,opt)}catch(error){
        var isRating=String(path||'').match(/\/v1\/deals\/\d+\/ratings$/)&&String((opt||{}).method||'GET').toUpperCase()==='POST';
        if(isRating&&window.activeDeal){
          try{
            var check=await oldApi('/v1/deals/'+window.activeDeal.id+'/ratings');
            if(check&&check.my_rating){
              return {message:'Avaliação registrada. O fechamento documental está sendo atualizado.',rating:check.my_rating,recovered:true};
            }
          }catch(_){}
        }
        throw error;
      }
    };
    wrapped.__fdbRatingResilient=true;window.apiFetch=wrapped;
  }

  function refreshHomePartnersOnHome(){
    if(typeof window.go!=='function'||window.go.__fdbPartners)return;
    var previous=window.go;window.go=function(id){var result=previous.apply(this,arguments);if(id==='home')setTimeout(loadPartners,50);return result};window.go.__fdbPartners=true;
  }

  var observer=new MutationObserver(function(){configureSignatureGate();arrangeHome();decorateRating();decorateOverdue();patchRatingSubmit();refreshHomePartnersOnHome()});
  observer.observe(document.documentElement,{childList:true,subtree:true});
  configureSignatureGate();arrangeHome();decorateRating();decorateOverdue();patchRatingSubmit();refreshHomePartnersOnHome();
})();
