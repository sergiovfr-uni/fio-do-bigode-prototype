(function(){
  if(window.__fdbAdminLegalLoaded)return;
  window.__fdbAdminLegalLoaded=true;

  var API_ROOT='https://api.nofiodobigode.app.br/api/v1';
  var TOKEN_KEY='fdb_admin_session';

  var style=document.createElement('style');
  style.textContent=`
    .legalGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
    .legalMetric{background:#fff;border:1px solid #e7e0d4;border-radius:13px;padding:13px}.legalMetric b{display:block;font-size:22px;margin-top:5px}
    .legalStatusNew{background:#fff1c7;color:#825b00}.legalStatusAssigned{background:#e8f0ff;color:#345c9c}.legalStatusProgress{background:#eaf5ec;color:#25743b}.legalStatusClosed{background:#ececec;color:#555}
    @media(max-width:900px){.legalGrid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.legalGrid{grid-template-columns:1fr}}
  `;
  document.head.appendChild(style);

  function token(){return sessionStorage.getItem(TOKEN_KEY)||''}
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]})}
  function date(v){if(!v)return '-';var d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleString('pt-BR')}
  async function request(path,opt){
    opt=opt||{};var headers={Accept:'application/json',Authorization:'Bearer '+token()};if(opt.body)headers['Content-Type']='application/json';
    var response=await fetch(API_ROOT+path,Object.assign({},opt,{headers:headers}));var body={};try{body=await response.json()}catch(_){}
    if(!response.ok)throw new Error(body.message||('API '+response.status));return body;
  }

  function badge(status){
    var labels={new:'Nova',assigned:'Encaminhada',in_progress:'Em atendimento',waiting_response:'Aguardando retorno',completed:'Concluída',cancelled:'Cancelada',declined:'Recusada'};
    var cls=status==='new'?'legalStatusNew':status==='assigned'?'legalStatusAssigned':['in_progress','waiting_response'].includes(status)?'legalStatusProgress':'legalStatusClosed';
    return '<span class="badge '+cls+'">'+esc(labels[status]||status||'-')+'</span>';
  }

  function installScreen(){
    var menu=document.querySelector('.menu');var content=document.querySelector('main.content');if(!menu||!content||document.getElementById('legal'))return;
    var btn=document.createElement('button');btn.type='button';btn.dataset.screen='legal';btn.textContent='Jurídico';
    var audit=[...menu.querySelectorAll('button')].find(function(x){return x.dataset.screen==='audit'});menu.insertBefore(btn,audit||null);
    var section=document.createElement('section');section.id='legal';section.className='screen';section.innerHTML='<div class="legalGrid" id="legalMetrics"></div><div class="card"><div class="toolbar"><div><h3>Solicitações de apoio jurídico</h3><div class="muted">Fila operacional vinculada às negociações em inadimplência.</div></div><button class="btn ghost" id="legalRefresh">Atualizar</button></div><div id="legalRequests"><div class="empty">Carregando...</div></div></div><div class="card"><div class="toolbar"><div><h3>Parceiros jurídicos</h3><div class="muted">Profissionais e escritórios habilitados para receber encaminhamentos.</div></div><button class="btn" id="legalNewPartner">Novo parceiro</button></div><div id="legalPartners"><div class="empty">Carregando...</div></div></div>';
    content.appendChild(section);

    btn.addEventListener('click',function(){document.querySelectorAll('.screen').forEach(function(s){s.classList.remove('on')});section.classList.add('on');document.querySelectorAll('[data-screen]').forEach(function(x){x.classList.remove('on')});btn.classList.add('on');var title=document.getElementById('pageTitle');if(title)title.textContent='Jurídico';load()});
    document.getElementById('legalRefresh').onclick=load;
    document.getElementById('legalNewPartner').onclick=newPartner;
  }

  async function load(){
    var requestsBox=document.getElementById('legalRequests'),partnersBox=document.getElementById('legalPartners'),metrics=document.getElementById('legalMetrics');if(!requestsBox||!partnersBox)return;
    requestsBox.innerHTML='<div class="empty">Atualizando solicitações…</div>';partnersBox.innerHTML='<div class="empty">Atualizando parceiros…</div>';
    try{
      var results=await Promise.all([request('/admin/legal-support-requests'),request('/admin/legal-partners')]);
      var requests=results[0].data||results[0]||[],partners=results[1].data||results[1]||[];
      var counts={new:0,assigned:0,in_progress:0,completed:0};requests.forEach(function(x){if(counts[x.status]!==undefined)counts[x.status]++});
      metrics.innerHTML=[['Novas',counts.new],['Encaminhadas',counts.assigned],['Em atendimento',counts.in_progress],['Concluídas',counts.completed]].map(function(x){return '<div class="legalMetric"><span class="muted">'+x[0]+'</span><b>'+x[1]+'</b></div>'}).join('');
      requestsBox.innerHTML=requests.length?'<div class="tableWrap"><table><thead><tr><th>SOLICITAÇÃO</th><th>NEGOCIAÇÃO</th><th>SOLICITANTE</th><th>PARCEIRO</th><th>STATUS</th><th>AÇÕES</th></tr></thead><tbody>'+requests.map(function(x){return '<tr><td>#'+esc(x.id)+'<div class="subtle">'+esc(date(x.requested_at||x.created_at))+'</div></td><td>'+esc(x.deal_public_id||x.deal?.public_id||x.deal_id)+'<div class="subtle">Parcela '+esc(x.installment_number||x.installment?.number||x.installment_id)+'</div></td><td>'+esc(x.requested_by_name||x.requested_by?.name||'-')+'</td><td>'+esc(x.legal_partner_name||x.legal_partner?.name||'Não atribuído')+'</td><td>'+badge(x.status)+'</td><td><button class="action" data-legal-request="'+esc(x.id)+'">Acompanhar</button></td></tr>'}).join('')+'</tbody></table></div>':'<div class="empty">Nenhuma solicitação de apoio jurídico.</div>';
      partnersBox.innerHTML=partners.length?'<div class="tableWrap"><table><thead><tr><th>PARCEIRO</th><th>CONTATO</th><th>REGIÃO</th><th>ESPECIALIDADE</th><th>STATUS</th></tr></thead><tbody>'+partners.map(function(x){return '<tr><td><b>'+esc(x.name)+'</b><div class="subtle">'+esc(x.oab||'')+'</div></td><td>'+esc(x.email||'-')+'<div class="subtle">'+esc(x.phone||'')+'</div></td><td>'+esc([x.city,x.state].filter(Boolean).join(' / ')||'-')+'</td><td>'+esc(Array.isArray(x.specialties)?x.specialties.join(', '):(x.specialties||'-'))+'</td><td>'+badge(x.active?'in_progress':'cancelled')+'</td></tr>'}).join('')+'</tbody></table></div>':'<div class="empty">Nenhum parceiro jurídico cadastrado.</div>';
    }catch(error){
      metrics.innerHTML='';requestsBox.innerHTML='<div class="notice error"><b>Módulo Jurídico aguardando publicação do backend.</b><div class="muted">'+esc(error.message)+'</div></div>';partnersBox.innerHTML='<div class="empty">A interface administrativa está pronta; os endpoints operacionais precisam estar disponíveis na API.</div>';
    }
  }

  async function newPartner(){
    var name=prompt('Nome do parceiro jurídico / escritório:');if(!name)return;var email=prompt('E-mail operacional:');if(!email)return;
    try{await request('/admin/legal-partners',{method:'POST',body:JSON.stringify({name:name,email:email,active:true})});load()}catch(error){alert(error.message)}
  }

  document.addEventListener('click',async function(event){
    var button=event.target.closest('[data-legal-request]');if(!button)return;var id=button.dataset.legalRequest;
    try{var detail=await request('/admin/legal-support-requests/'+id);alert('Solicitação #'+id+'\nStatus: '+(detail.status||detail.data?.status||'-')+'\nUse a API administrativa para atribuição e acompanhamento até a tela de detalhe ser publicada.')}catch(error){alert(error.message)}
  });

  installScreen();
})();
