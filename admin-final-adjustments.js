(function(){
  if(window.__fdbAdminFinalAdjustmentsLoaded)return;
  window.__fdbAdminFinalAdjustmentsLoaded=true;

  var API_ROOT='https://api.nofiodobigode.app.br/api/v1';
  var TOKEN_KEY='fdb_admin_session';
  var cache=[];

  var style=document.createElement('style');
  style.textContent=`
    .sidebar{display:flex!important;flex-direction:column!important;overflow:hidden!important}
    .sidebar .menu{flex:1 1 auto!important;min-height:0!important;overflow-y:auto!important;padding-right:4px!important;margin-bottom:10px!important;scrollbar-width:thin!important}
    .sidebarFoot{position:static!important;flex:0 0 auto!important;margin-top:auto!important;background:#161616!important;padding-top:12px!important}
    .userEyeAction{font-size:16px;line-height:1;padding:7px 10px;min-width:38px}
    .userViewBackdrop{position:fixed;inset:0;background:#0009;z-index:120;display:grid;place-items:center;padding:18px}
    .userViewBackdrop[hidden]{display:none!important}
    .userViewCard{width:min(760px,100%);max-height:92dvh;overflow:auto;background:#fff;border-radius:20px;padding:22px;box-shadow:0 30px 90px #0008}
    .userViewHead{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px}
    .userViewHead h2{margin:0}.userViewClose{border:0;background:#eee;border-radius:50%;width:38px;height:38px;font-size:20px;cursor:pointer}
    .userViewGrid{display:grid;grid-template-columns:1fr 1fr;gap:11px}
    .userViewField{border:1px solid #e7e0d4;border-radius:12px;padding:12px;background:#fff}
    .userViewField.wide{grid-column:1/-1}.userViewLabel{font-size:10px;font-weight:900;color:#777;text-transform:uppercase;letter-spacing:.45px}.userViewValue{font-size:14px;font-weight:750;margin-top:5px;word-break:break-word}
    .legalStats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.legalStat{padding:14px;border:1px solid #e7e0d4;border-radius:13px;background:#fff}.legalStat b{display:block;font-size:24px;margin-top:5px}
    .legalPlan{background:#111;color:#fff;border-radius:14px;padding:16px;margin-bottom:14px}.legalPlan strong{color:#efbf55}.legalEmpty{padding:24px;border:1px dashed #d8d2c7;border-radius:13px;text-align:center;color:#777}
    @media(max-width:900px){.sidebar{display:block!important;overflow:visible!important}.sidebar .menu{overflow-x:auto!important;overflow-y:hidden!important}.sidebarFoot{margin-top:12px!important}.legalStats{grid-template-columns:1fr 1fr}}
    @media(max-width:600px){.userViewGrid{grid-template-columns:1fr}.userViewField.wide{grid-column:auto}.legalStats{grid-template-columns:1fr}}
  `;
  document.head.appendChild(style);

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]})}
  function fmtDate(value){if(!value)return '-';var date=new Date(value);return Number.isNaN(date.getTime())?String(value):date.toLocaleString('pt-BR')}
  function field(label,value,wide){return '<div class="userViewField'+(wide?' wide':'')+'"><div class="userViewLabel">'+esc(label)+'</div><div class="userViewValue">'+esc(value==null||value===''?'-':value)+'</div></div>'}
  function token(){return sessionStorage.getItem(TOKEN_KEY)||''}
  async function api(path){var response=await fetch(API_ROOT+path,{headers:{Accept:'application/json',Authorization:'Bearer '+token()}});if(!response.ok)throw new Error('API '+response.status);return response.json()}

  var backdrop=document.createElement('section');
  backdrop.id='userViewBackdrop';
  backdrop.className='userViewBackdrop';
  backdrop.hidden=true;
  backdrop.innerHTML='<div class="userViewCard"><div class="userViewHead"><div><div class="muted">USUÁRIO / KYC</div><h2 id="userViewTitle">Dados do usuário</h2></div><button class="userViewClose" type="button" aria-label="Fechar">×</button></div><div id="userViewBody"></div></div>';
  document.body.appendChild(backdrop);
  backdrop.querySelector('.userViewClose').onclick=function(){backdrop.hidden=true};
  backdrop.addEventListener('click',function(event){if(event.target===backdrop)backdrop.hidden=true});

  async function getUsers(){var payload=await api('/admin/users');cache=payload.data||[];return cache}
  async function openUser(id){
    var body=document.getElementById('userViewBody');body.innerHTML='<div class="notice">Carregando dados do usuário…</div>';backdrop.hidden=false;
    try{
      var users=cache.length?cache:await getUsers();var user=users.find(function(item){return Number(item.id)===Number(id)});
      if(!user){users=await getUsers();user=users.find(function(item){return Number(item.id)===Number(id)})}if(!user)throw new Error('Usuário não encontrado.');
      document.getElementById('userViewTitle').textContent=user.name||'Dados do usuário';
      var subscription=(user.subscriptions||[])[0];var address=[user.address_line,user.address_number,user.address_complement,user.district,user.city,user.state,user.postal_code].filter(Boolean).join(', ');
      body.innerHTML='<div class="userViewGrid">'+field('Nome completo',user.name,true)+field('E-mail',user.email)+field('Telefone / WhatsApp',user.phone)+field('CPF',user.cpf_masked||user.cpf)+field('Documento',user.identity_document||user.document_number)+field('KYC',user.kyc_status)+field('ID / sessão Didit',user.didit_session_id||user.kyc_session_id)+field('Acesso',user.account_status||'active')+field('Risco',user.risk_score)+field('Reputação Bigode',user.reputation_score)+field('Avaliações',user.reputation_reviews_count||user.completed_deals_count)+field('Plano',subscription?(subscription.plan?.name||subscription.status):'Sem plano')+field('Nascimento',user.birth_date)+field('Estado civil',user.marital_status)+field('Profissão',user.occupation)+field('Nacionalidade',user.nationality)+field('Endereço',address,true)+field('Cadastro em',fmtDate(user.created_at))+field('Última atualização',fmtDate(user.updated_at))+'</div>';
    }catch(error){body.innerHTML='<div class="notice error">'+esc(error.message)+'</div>'}
  }

  function addEyes(){
    var table=document.getElementById('usersTable');if(!table)return;
    table.querySelectorAll('tbody tr').forEach(function(row){
      if(row.querySelector('[data-action="view-user-final"]'))return;
      var actionCell=row.lastElementChild;var anyButton=actionCell&&actionCell.querySelector('[data-id]');if(!actionCell||!anyButton)return;
      var button=document.createElement('button');button.type='button';button.className='action userEyeAction';button.dataset.action='view-user-final';button.dataset.id=anyButton.getAttribute('data-id');button.title='Visualizar usuário';button.setAttribute('aria-label','Visualizar usuário');button.textContent='👁';(actionCell.querySelector('.actions')||actionCell).insertBefore(button,(actionCell.querySelector('.actions')||actionCell).firstChild);
    });
  }

  function ensureLegalModule(){
    var menu=document.querySelector('.sidebar .menu');var content=document.querySelector('main.content');if(!menu||!content)return;
    if(!menu.querySelector('[data-screen="legal"]')){
      var button=document.createElement('button');button.dataset.screen='legal';button.textContent='Jurídico';var audit=menu.querySelector('[data-screen="audit"]');menu.insertBefore(button,audit||null);
      button.addEventListener('click',function(){document.querySelectorAll('.screen').forEach(function(screen){screen.classList.remove('on')});document.getElementById('legal')?.classList.add('on');document.querySelectorAll('[data-screen]').forEach(function(item){item.classList.remove('on')});button.classList.add('on');var title=document.getElementById('pageTitle');if(title)title.textContent='Jurídico';loadLegal()});
    }
    if(!document.getElementById('legal')){
      var section=document.createElement('section');section.id='legal';section.className='screen';section.innerHTML='<div class="card"><div class="toolbar"><div><h3>Jurídico • Rede de parceiros</h3><div class="muted">Escritórios assinantes recebem leads qualificados gerados por negociações que solicitaram apoio.</div></div><button class="btn" type="button" id="legalRefresh">Atualizar</button></div><div class="legalPlan"><strong>Modelo B2B por assinatura</strong><div class="muted" style="color:#ddd;margin-top:6px">Mensalidade para participar da rede, receber solicitações compatíveis com região/especialidade e acompanhar conversão dos leads.</div></div><div id="legalStats" class="legalStats"></div><div class="card"><h3>Solicitações de apoio</h3><div id="legalRequests"><div class="legalEmpty">Carregando solicitações…</div></div></div><div class="card"><h3>Parceiros jurídicos</h3><div id="legalPartners"><div class="legalEmpty">Carregando parceiros…</div></div></div></div>';
      content.appendChild(section);section.querySelector('#legalRefresh').onclick=loadLegal;
    }
  }

  function simpleTable(headers,rows){if(!rows.length)return '<div class="legalEmpty">Nenhum registro disponível neste ambiente.</div>';return '<div class="tableWrap"><table><thead><tr>'+headers.map(function(h){return '<th>'+esc(h)+'</th>'}).join('')+'</tr></thead><tbody>'+rows.join('')+'</tbody></table></div>'}
  async function firstAvailable(paths){for(var i=0;i<paths.length;i++){try{return await api(paths[i])}catch(_){}}return null}
  async function loadLegal(){
    ensureLegalModule();var req=document.getElementById('legalRequests'),partners=document.getElementById('legalPartners'),stats=document.getElementById('legalStats');if(!req||!partners||!stats)return;
    req.innerHTML='<div class="legalEmpty">Carregando solicitações…</div>';partners.innerHTML='<div class="legalEmpty">Carregando parceiros…</div>';
    var requests=await firstAvailable(['/admin/legal-support/requests','/admin/legal/requests','/admin/legal-support']);var offices=await firstAvailable(['/admin/legal-partners','/admin/legal/partners']);
    var requestItems=(requests&&(requests.data||requests.requests||requests))||[];var officeItems=(offices&&(offices.data||offices.partners||offices))||[];if(!Array.isArray(requestItems))requestItems=[];if(!Array.isArray(officeItems))officeItems=[];
    var open=requestItems.filter(function(x){return !['closed','completed','cancelled'].includes(String(x.status||''))}).length;var active=officeItems.filter(function(x){return !['inactive','cancelled','suspended'].includes(String(x.status||x.subscription_status||'active'))}).length;
    stats.innerHTML='<div class="legalStat"><span class="muted">Solicitações</span><b>'+requestItems.length+'</b></div><div class="legalStat"><span class="muted">Em atendimento</span><b>'+open+'</b></div><div class="legalStat"><span class="muted">Escritórios ativos</span><b>'+active+'</b></div><div class="legalStat"><span class="muted">Leads convertidos</span><b>'+officeItems.reduce(function(total,x){return total+Number(x.converted_leads||0)},0)+'</b></div>';
    req.innerHTML=simpleTable(['NEGOCIAÇÃO','CLIENTE','PARCELA','PARCEIRO','STATUS'],requestItems.map(function(x){return '<tr><td>'+esc(x.deal_public_id||x.deal_id||'-')+'</td><td>'+esc(x.requester?.name||x.user_name||x.requester_name||'-')+'</td><td>'+esc(x.installment_number||'-')+'</td><td>'+esc(x.partner?.name||x.partner_name||'Aguardando distribuição')+'</td><td><span class="badge">'+esc(x.status||'nova')+'</span></td></tr>'}));
    partners.innerHTML=simpleTable(['ESCRITÓRIO','REGIÃO','PLANO','MENSALIDADE','LEADS','STATUS'],officeItems.map(function(x){return '<tr><td><b>'+esc(x.name||x.office_name||'-')+'</b><div class="muted">'+esc(x.email||'')+'</div></td><td>'+esc(x.region||x.service_region||'-')+'</td><td>'+esc(x.plan?.name||x.plan_name||'-')+'</td><td>'+esc(x.monthly_price!=null?Number(x.monthly_price).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}):'-')+'</td><td>'+esc(x.leads_received||0)+'</td><td><span class="badge '+(String(x.subscription_status||x.status)==='active'?'ok':'')+'">'+esc(x.subscription_status||x.status||'ativo')+'</span></td></tr>'}));
    if(!requests)req.innerHTML='<div class="legalEmpty"><b>Módulo visual pronto.</b><br>O backend ainda precisa publicar os endpoints de solicitações jurídicas para trazer dados reais.</div>';
    if(!offices)partners.innerHTML='<div class="legalEmpty"><b>Rede de escritórios pronta para integração.</b><br>O backend ainda precisa publicar cadastro, assinatura e distribuição de leads dos parceiros jurídicos.</div>';
  }

  document.addEventListener('click',function(event){var button=event.target.closest('[data-action="view-user-final"]');if(!button)return;event.preventDefault();event.stopImmediatePropagation();openUser(Number(button.dataset.id))},true);

  var observer=new MutationObserver(function(){addEyes();ensureLegalModule()});observer.observe(document.documentElement,{childList:true,subtree:true});
  addEyes();ensureLegalModule();
})();
