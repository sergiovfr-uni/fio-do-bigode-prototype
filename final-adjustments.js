(function(){
  if(window.__fdbFinalAdjustmentsLoaded)return;
  window.__fdbFinalAdjustmentsLoaded=true;

  var style=document.createElement('style');
  style.textContent=`
    .partnerSection{margin-top:18px}
    .partnerIntro{font-size:12px;color:#666;line-height:1.45;margin:-2px 0 10px}
    .partnerCarousel{display:flex;gap:12px;overflow-x:auto;padding:2px 0 8px;scroll-snap-type:x mandatory;scrollbar-width:none}
    .partnerCarousel::-webkit-scrollbar{display:none}
    .partnerCard{flex:0 0 82%;max-width:330px;scroll-snap-align:start;border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;box-shadow:0 5px 16px #00000008}
    .partnerMedia{height:145px;background:linear-gradient(135deg,#171717,#d49a13);display:grid;place-items:center;overflow:hidden}
    .partnerMedia img{width:100%;height:100%;object-fit:cover}
    .partnerInitial{width:72px;height:72px;border-radius:50%;display:grid;place-items:center;background:#111;color:#efbf55;font-size:29px;font-weight:900}
    .partnerBody{padding:13px}
    .partnerPlatform{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;color:#8b6410}
    .partnerName{display:block;font-size:15px;margin:4px 0 6px}
    .partnerText{font-size:12px;color:#666;line-height:1.4;min-height:34px}
    .partnerLink{font-size:11px;font-weight:900;color:#8b6410;margin-top:8px}
    .partnerEmpty{flex:0 0 88%;border:1px dashed #d7b354;border-radius:18px;padding:16px;background:#fffaf0;color:#665b42;font-size:12px;line-height:1.45}
  `;
  document.head.appendChild(style);

  function safeText(value){return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]})}

  function arrangeHome(){
    var home=document.getElementById('home');
    var campaigns=document.getElementById('campaignArea');
    var categories=document.getElementById('homeCategories');
    if(!home||!campaigns||!categories)return false;
    var campaignTitle=campaigns.previousElementSibling;
    var categoryTitle=categories.previousElementSibling;
    if(!campaignTitle||!categoryTitle)return false;

    campaignTitle.textContent='Publicidade';

    var partnerSection=document.getElementById('homePartnersSection');
    if(!partnerSection){
      partnerSection=document.createElement('div');
      partnerSection.id='homePartnersSection';
      partnerSection.className='partnerSection';
      partnerSection.innerHTML='<div class="section">Quem está com a gente</div><div class="partnerIntro">Influenciadores, criadores e comunidades que ajudam a divulgar o Fio do Bigode. Toque no card para visitar o perfil ou publicação do parceiro.</div><div id="homePartners" class="partnerCarousel"><div class="partnerEmpty"><b>Quem está com a gente</b><br>Este espaço fica sempre visível. Os parceiros divulgadores ativos aparecerão aqui.</div></div>';
      home.insertBefore(partnerSection,categoryTitle);
      loadPartners();
    }

    // Reordena apenas uma vez. O código anterior movia os mesmos nós a cada
    // MutationObserver e criava um loop que deixava os botões da tela inicial sem resposta.
    if(!home.__fdbHomeOrdered){
      home.appendChild(campaignTitle);
      home.appendChild(campaigns);
      home.appendChild(partnerSection);
      home.appendChild(categoryTitle);
      home.appendChild(categories);
      home.__fdbHomeOrdered=true;
    }
    return true;
  }

  async function getPartners(){
    var urls=['https://api.nofiodobigode.app.br/api/v1/community-partners','https://fio-do-bigode-prototype-production.up.railway.app/api/v1/community-partners'];
    var lastError=null;
    for(var i=0;i<urls.length;i++){
      try{
        var response=await fetch(urls[i],{headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('HTTP '+response.status);
        var data=await response.json();
        var items=Array.isArray(data)?data:(Array.isArray(data.data)?data.data:[]);
        if(items.length)return items;
      }catch(error){lastError=error}
    }
    throw lastError||new Error('Parceiros indisponíveis');
  }

  function partnerCard(item){
    var image=item.post_image_url||item.featured_image_url||item.avatar_url||item.image_url||'';
    var target=item.post_url||item.profile_url||item.url||'#';
    var text=item.post_text||item.audience_label||item.description||'Conheça quem está ajudando a espalhar o Fio do Bigode.';
    var platform=item.platform||'parceiro';
    var name=item.name||'Parceiro Fio do Bigode';
    var external=target&&target!=='#';
    return '<a class="partnerCard" href="'+safeText(target)+'" '+(external?'target="_blank" rel="noopener noreferrer"':'')+'><div class="partnerMedia">'+(image?'<img src="'+safeText(image)+'" alt="'+safeText(name)+'">':'<span class="partnerInitial">'+safeText(String(name).charAt(0))+'</span>')+'</div><div class="partnerBody"><span class="partnerPlatform">'+safeText(platform)+'</span><b class="partnerName">'+safeText(name)+'</b><div class="partnerText">'+safeText(text)+'</div><div class="partnerLink">'+(external?'Ver perfil / publicação →':'Perfil em configuração')+'</div></div></a>';
  }

  async function loadPartners(){
    var area=document.getElementById('homePartners');if(!area)return;
    try{
      var items=await getPartners();
      var active=items.filter(function(item){return item.active!==false&&item.status!=='inactive'});
      if(!active.length){area.innerHTML='<div class="partnerEmpty"><b>Quem está com a gente</b><br>Parceiros em breve. Os divulgadores ativos cadastrados no Admin aparecerão automaticamente aqui.</div>';return}
      area.innerHTML=active.map(partnerCard).join('');
    }catch(_){
      area.innerHTML='<div class="partnerEmpty"><b>Quem está com a gente</b><br>Não foi possível consultar os parceiros agora. Este espaço continuará visível e tentará novamente ao abrir a Home.</div>';
    }
  }

  function hookHome(){
    if(typeof window.go!=='function'||window.go.__fdbPartners)return;
    var previous=window.go;
    window.go=function(id){
      var result=previous.apply(this,arguments);
      if(id==='home')setTimeout(function(){arrangeHome();loadPartners()},80);
      return result;
    };
    window.go.__fdbPartners=true;
  }

  var initialized=arrangeHome();
  hookHome();
  if(!initialized){
    var observer=new MutationObserver(function(){
      if(arrangeHome())observer.disconnect();
      hookHome();
    });
    observer.observe(document.documentElement,{childList:true,subtree:true});
  }
})();