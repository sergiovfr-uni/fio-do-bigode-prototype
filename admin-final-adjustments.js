(function(){
  if(window.__fdbAdminFinalAdjustmentsLoaded)return;
  window.__fdbAdminFinalAdjustmentsLoaded=true;

  var API_ROOT='https://api.nofiodobigode.app.br/api/v1';
  var TOKEN_KEY='fdb_admin_session';
  var cache=[];

  var style=document.createElement('style');
  style.textContent=`
    .userEyeAction{font-size:16px;line-height:1;padding:7px 10px;min-width:38px}
    .userViewBackdrop{position:fixed;inset:0;background:#0009;z-index:120;display:grid;place-items:center;padding:18px}
    .userViewBackdrop[hidden]{display:none!important}
    .userViewCard{width:min(760px,100%);max-height:92dvh;overflow:auto;background:#fff;border-radius:20px;padding:22px;box-shadow:0 30px 90px #0008}
    .userViewHead{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px}
    .userViewHead h2{margin:0}.userViewClose{border:0;background:#eee;border-radius:50%;width:38px;height:38px;font-size:20px;cursor:pointer}
    .userViewGrid{display:grid;grid-template-columns:1fr 1fr;gap:11px}
    .userViewField{border:1px solid #e7e0d4;border-radius:12px;padding:12px;background:#fff}
    .userViewField.wide{grid-column:1/-1}.userViewLabel{font-size:10px;font-weight:900;color:#777;text-transform:uppercase;letter-spacing:.45px}.userViewValue{font-size:14px;font-weight:750;margin-top:5px;word-break:break-word}
    @media(max-width:600px){.userViewGrid{grid-template-columns:1fr}.userViewField.wide{grid-column:auto}}
  `;
  document.head.appendChild(style);

  var backdrop=document.createElement('section');
  backdrop.id='userViewBackdrop';
  backdrop.className='userViewBackdrop';
  backdrop.hidden=true;
  backdrop.innerHTML='<div class="userViewCard"><div class="userViewHead"><div><div class="muted">USUÁRIO / KYC</div><h2 id="userViewTitle">Dados do usuário</h2></div><button class="userViewClose" type="button" aria-label="Fechar">×</button></div><div id="userViewBody"></div></div>';
  document.body.appendChild(backdrop);
  backdrop.querySelector('.userViewClose').onclick=function(){backdrop.hidden=true};
  backdrop.addEventListener('click',function(event){if(event.target===backdrop)backdrop.hidden=true});

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]})}
  function fmtDate(value){if(!value)return '-';var date=new Date(value);return Number.isNaN(date.getTime())?String(value):date.toLocaleString('pt-BR')}
  function field(label,value,wide){return '<div class="userViewField'+(wide?' wide':'')+'"><div class="userViewLabel">'+esc(label)+'</div><div class="userViewValue">'+esc(value==null||value===''?'-':value)+'</div></div>'}

  async function getUsers(){
    var token=sessionStorage.getItem(TOKEN_KEY)||'';
    if(!token)throw new Error('Sessão administrativa não encontrada.');
    var response=await fetch(API_ROOT+'/admin/users',{headers:{Accept:'application/json',Authorization:'Bearer '+token}});
    if(!response.ok)throw new Error('Não foi possível consultar os dados do usuário.');
    var payload=await response.json();
    cache=payload.data||[];
    return cache;
  }

  async function openUser(id){
    var body=document.getElementById('userViewBody');
    body.innerHTML='<div class="notice">Carregando dados do usuário…</div>';
    backdrop.hidden=false;
    try{
      var users=cache.length?cache:await getUsers();
      var user=users.find(function(item){return Number(item.id)===Number(id)});
      if(!user){users=await getUsers();user=users.find(function(item){return Number(item.id)===Number(id)})}
      if(!user)throw new Error('Usuário não encontrado.');
      document.getElementById('userViewTitle').textContent=user.name||'Dados do usuário';
      var subscription=(user.subscriptions||[])[0];
      var address=[user.address_line,user.address_number,user.address_complement,user.district,user.city,user.state,user.postal_code].filter(Boolean).join(', ');
      body.innerHTML='<div class="userViewGrid">'
        +field('Nome completo',user.name,true)
        +field('E-mail',user.email)
        +field('Telefone / WhatsApp',user.phone)
        +field('CPF',user.cpf_masked||user.cpf)
        +field('Documento',user.identity_document||user.document_number)
        +field('KYC',user.kyc_status)
        +field('ID / sessão Didit',user.didit_session_id||user.kyc_session_id)
        +field('Acesso',user.account_status||'active')
        +field('Risco',user.risk_score)
        +field('Reputação Bigode',user.reputation_score)
        +field('Avaliações',user.reputation_reviews_count||user.completed_deals_count)
        +field('Plano',subscription?(subscription.plan?.name||subscription.status):'Sem plano')
        +field('Nascimento',user.birth_date)
        +field('Estado civil',user.marital_status)
        +field('Profissão',user.occupation)
        +field('Nacionalidade',user.nationality)
        +field('Endereço',address,true)
        +field('Cadastro em',fmtDate(user.created_at))
        +field('Última atualização',fmtDate(user.updated_at))
        +'</div>';
    }catch(error){body.innerHTML='<div class="notice error">'+esc(error.message)+'</div>'}
  }

  function addEyes(){
    var table=document.getElementById('usersTable');
    if(!table)return;
    table.querySelectorAll('tbody tr').forEach(function(row){
      if(row.querySelector('[data-action="view-user-final"]'))return;
      var actionCell=row.lastElementChild;
      var anyButton=actionCell&&actionCell.querySelector('[data-id]');
      if(!actionCell||!anyButton)return;
      var id=anyButton.getAttribute('data-id');
      var actions=actionCell.querySelector('.actions')||actionCell;
      var button=document.createElement('button');
      button.type='button';
      button.className='action userEyeAction';
      button.dataset.action='view-user-final';
      button.dataset.id=id;
      button.title='Visualizar usuário';
      button.setAttribute('aria-label','Visualizar usuário');
      button.textContent='👁';
      actions.insertBefore(button,actions.firstChild);
    });
  }

  document.addEventListener('click',function(event){
    var button=event.target.closest('[data-action="view-user-final"]');
    if(!button)return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openUser(Number(button.dataset.id));
  },true);

  var observer=new MutationObserver(addEyes);
  observer.observe(document.documentElement,{childList:true,subtree:true});
  addEyes();
})();
