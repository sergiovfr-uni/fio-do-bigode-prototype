const API_ROOT='https://api.nofiodobigode.app.br/api/v1';
const TOKEN_KEY='fdb_admin_session';
let token=sessionStorage.getItem(TOKEN_KEY)||'';
let challengeId='';
let modalConfig=null;
let store={dashboard:{},users:[],deals:[],listings:[],installments:[],wallets:[],plans:[],advertisers:[],campaigns:[],audit:[]};

const $=id=>document.getElementById(id);
const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
const brl=value=>Number(value||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const labels={verified:'Verificado',pending:'Pendente',review:'Em análise',rejected:'Reprovado',expired:'Expirado',active:'Ativo',blocked:'Bloqueado',suspended:'Suspenso',published:'Publicado',draft:'Rascunho',archived:'Arquivado',paid:'Pago',paid_off:'Quitada',receipt_submitted:'Comprovante enviado',inactive:'Inativo',cancelled:'Cancelado',closed:'Concluído',proposal_sent:'Proposta enviada',counteroffer:'Contraproposta',overdue:'Em atraso',trial:'Teste gratuito'};
const badge=status=>{const key=String(status??'');const type=['verified','active','published','paid','paid_off','closed'].includes(key)?'ok':['rejected','blocked','cancelled','archived'].includes(key)?'red':'blue';return `<span class="badge ${type}">${escapeHtml(labels[key]||key||'-')}</span>`};
const table=(headers,rows)=>`<div class="tableWrap">${rows.length?`<table><thead><tr>${headers.map(h=>`<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table>`:'<div class="empty">Nenhum registro encontrado.</div>'}</div>`;
const actions=items=>`<div class="actions">${items.filter(Boolean).join('')}</div>`;
const action=(label,name,id,className='')=>`<button type="button" class="action ${className}" data-action="${escapeHtml(name)}" data-id="${escapeHtml(id)}">${escapeHtml(label)}</button>`;
const asDate=value=>value?new Date(value).toLocaleDateString('pt-BR'):'-';
const asDateTime=value=>value?new Date(value).toLocaleString('pt-BR'):'-';
const localDateTime=value=>{if(!value)return '';const date=new Date(value);const offset=date.getTimezoneOffset()*60000;return new Date(date-offset).toISOString().slice(0,16)};

async function request(path,options={}){
  const headers={Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(token?{Authorization:`Bearer ${token}`}:{})};
  const response=await fetch(API_ROOT+path,{...options,headers:{...headers,...(options.headers||{})}});
  let payload={};try{payload=await response.json()}catch(_){}
  if(!response.ok){const first=payload.errors?Object.values(payload.errors).flat()[0]:null;const error=new Error(first||payload.message||`Falha na API (${response.status})`);error.status=response.status;throw error}
  return payload;
}

function setBusy(busy){$('authButton').disabled=busy;$('refreshButton').disabled=busy;$('loading').hidden=!busy}
function showError(message){$('errorNotice').textContent=message;$('errorNotice').hidden=!message}
function toast(message,error=false){$('toast').textContent=message;$('toast').className=`toast${error?' error':''}`;$('toast').hidden=false;clearTimeout(toast.timer);toast.timer=setTimeout(()=>$('toast').hidden=true,3500)}
function resetAuth(){challengeId='';$('credentialsStep').hidden=false;$('codeStep').hidden=true;$('restartButton').hidden=true;$('authButton').textContent='Entrar com segurança';$('authMessage').textContent='';$('password').value='';$('code').value='';$('code').required=false;$('email').focus()}
function showAuth(){token='';sessionStorage.removeItem(TOKEN_KEY);$('shell').hidden=true;$('auth').hidden=false;resetAuth()}
function showPanel(user){$('auth').hidden=true;$('shell').hidden=false;$('adminName').textContent=user.name;$('adminEmail').textContent=user.email}

$('togglePassword').addEventListener('click',()=>{const input=$('password');input.type=input.type==='password'?'text':'password';$('togglePassword').setAttribute('aria-label',input.type==='password'?'Mostrar senha':'Ocultar senha')});
$('restartButton').addEventListener('click',resetAuth);
$('loginForm').addEventListener('submit',async event=>{
  event.preventDefault();$('authMessage').textContent='';setBusy(true);
  try{
    if(!challengeId){
      const result=await request('/admin/auth/login',{method:'POST',body:JSON.stringify({email:$('email').value.trim(),password:$('password').value})});
      challengeId=result.challenge_id;$('credentialsStep').hidden=true;$('codeStep').hidden=false;$('restartButton').hidden=false;$('codeNotice').textContent=`Código enviado para ${result.masked_email}. Ele expira em 5 minutos.`;$('authButton').textContent='Confirmar código e entrar';$('code').required=true;$('code').focus();
    }else{
      const result=await request('/admin/auth/2fa/verify',{method:'POST',body:JSON.stringify({challenge_id:challengeId,code:$('code').value})});
      token=result.token;sessionStorage.setItem(TOKEN_KEY,token);showPanel(result.user);await loadData();
    }
  }catch(error){$('authMessage').textContent=error.message}finally{setBusy(false)}
});

async function loadData(){
  setBusy(true);showError('');$('apiStatus').textContent='Atualizando…';$('apiStatus').className='badge';
  try{
    const paths=['/admin/dashboard','/admin/users','/admin/deals','/admin/listings','/admin/installments','/admin/wallets','/admin/plans','/admin/advertisers','/admin/campaigns','/admin/audit-logs'];
    const [dashboard,users,deals,listings,installments,wallets,plans,advertisers,campaigns,audit]=await Promise.all(paths.map(path=>request(path).catch(error=>{error.message=`${path}: ${error.message}`;throw error})));
    store={dashboard,users:users.data||[],deals:deals.data||[],listings:listings.data||[],installments:installments.data||[],wallets:wallets.data||[],plans:plans||[],advertisers:advertisers||[],campaigns:campaigns.data||[],audit:audit.data||[]};
    render();$('apiStatus').textContent='API conectada';$('apiStatus').className='badge ok';
  }catch(error){
    if(error.status===401||error.status===403){showAuth();$('authMessage').textContent=error.status===403?'Seu acesso administrativo não está autorizado.':'Sua sessão expirou. Entre novamente.';return}
    $('apiStatus').textContent='API indisponível';$('apiStatus').className='badge red';showError(`Não foi possível carregar os dados reais: ${error.message}`);
  }finally{setBusy(false)}
}

function render(){
  const d=store.dashboard;
  $('metrics').innerHTML=[['Usuários',d.users],['KYC pendente',d.kyc_pending],['Classificados ativos',d.listings_active],['Negociações ativas',d.deals_active],['Volume wallet',brl(d.wallet_volume)],['Parcelas abertas',d.installments_open],['Impressões',d.ad_impressions],['CTR',`${Number(d.ad_ctr||0).toFixed(2)}%`]].map(item=>`<div class="card"><div class="muted">${escapeHtml(item[0])}</div><div class="metric">${escapeHtml(item[1])}</div></div>`).join('');
  $('summary').innerHTML=`<p><b>KYC verificados:</b> ${escapeHtml(d.kyc_verified||0)} &nbsp; • &nbsp; <b>Negociações abertas:</b> ${escapeHtml(d.deals_open||0)} &nbsp; • &nbsp; <b>Parcelas vencidas:</b> ${escapeHtml(d.installments_overdue||0)} &nbsp; • &nbsp; <b>Cliques em campanhas:</b> ${escapeHtml(d.ad_clicks||0)}</p>`;

  $('usersTable').innerHTML=table(['USUÁRIO','KYC','ACESSO','PLANO','ÍNDICES','AÇÕES'],store.users.map(x=>{
    const subscription=(x.subscriptions||[])[0];
    const plan=subscription?`${subscription.plan?.name||'-'}<div class="subtle">${subscription.status==='trial'?`até ${asDate(subscription.trial_ends_at)}`:labels[subscription.status]||subscription.status}</div>`:'Sem plano';
    return `<tr><td><b>${escapeHtml(x.name)}</b><div class="subtle">${escapeHtml(x.email)} • ${escapeHtml(x.phone||'-')}</div></td><td>${badge(x.kyc_status)}</td><td>${badge(x.account_status||'active')}</td><td>${plan}</td><td>Risco ${escapeHtml(x.risk_score??'-')} • Bigode ${escapeHtml(x.reputation_score??'-')}</td><td>${actions([action('Editar','edit-user',x.id),action('Recuperar senha','password-reset',x.id),action((x.account_status||'active')==='active'?'Bloquear':'Ativar','user-status',x.id,(x.account_status||'active')==='active'?'danger':'ok'),x.kyc_status!=='verified'?action('Liberar KYC','kyc-retry',x.id):'',action('Estender trial','extend-trial',x.id),action('Alterar plano','assign-plan',x.id)])}</td></tr>`;
  }));

  $('dealsTable').innerHTML=table(['ID','PARTES','VALOR','STATUS','AÇÕES'],store.deals.map(x=>`<tr><td>${escapeHtml(x.public_id)}</td><td>${escapeHtml(x.seller?.name||'-')} → ${escapeHtml(x.buyer?.name||'-')}</td><td>${escapeHtml(brl(x.total_amount))}</td><td>${badge(x.status)}</td><td>${actions([action('Detalhes','deal-details',x.id),...(['paid_off','closed','cancelled'].includes(x.status)?[]:[action(x.status==='suspended'?'Reativar':'Suspender','deal-status',x.id),action('Cancelar','cancel-deal',x.id,'danger')])])}</td></tr>`));

  $('listingsTable').innerHTML=table(['ANÚNCIO','VENDEDOR','VALOR','STATUS','AÇÕES'],store.listings.map(x=>`<tr><td><b>${escapeHtml(x.title)}</b><div class="subtle">${escapeHtml(x.category)}</div></td><td>${escapeHtml(x.seller?.name||'-')}</td><td>${escapeHtml(brl(x.price))}${x.accepts_trade?'<div class="badge">Vem na catira</div>':''}</td><td>${badge(x.status)}</td><td>${actions([action('Editar','edit-listing',x.id),action(x.status==='published'?'Arquivar':'Publicar','listing-status',x.id,x.status==='published'?'danger':'ok')])}</td></tr>`));

  $('installmentsTable').innerHTML=table(['NEGOCIAÇÃO','PARCELA','VENCIMENTO','VALOR','STATUS','AÇÕES'],store.installments.map(x=>`<tr><td>${escapeHtml(x.deal_public_id)}</td><td>${escapeHtml(x.number||'-')}</td><td>${escapeHtml(asDate(x.due_date))}</td><td>${escapeHtml(brl(x.amount))}</td><td>${badge(x.status)}</td><td>${actions([x.receipt_document_id?action('Ver comprovante','view-receipt',x.id):'',...(x.status==='paid'?[]:[action('Alterar vencimento','installment-date',x.id),x.receipt_document_id?action('Rejeitar comprovante','reject-receipt',x.id,'danger'):'',x.receipt_document_id?action('Conciliar pagamento','reconcile-installment',x.id,'ok'):''])])}</td></tr>`));

  $('walletsTable').innerHTML=table(['USUÁRIO','SALDO','TRANSAÇÕES','STATUS','AÇÕES'],store.wallets.map(x=>`<tr><td><b>${escapeHtml(x.name)}</b><div class="subtle">${escapeHtml(x.email)}</div></td><td>${escapeHtml(brl(x.balance))}</td><td>${escapeHtml(x.transactions)}</td><td>${badge(x.status)}</td><td>${actions([action('Lançar ajuste','adjust-wallet',x.id),action(x.status==='active'?'Suspender':'Ativar','wallet-status',x.id,x.status==='active'?'danger':'ok')])}</td></tr>`));

  $('plansTable').innerHTML=table(['PLANO','MENSALIDADE','LIMITES','ASSINANTES','STATUS','AÇÕES'],store.plans.map(x=>`<tr><td><b>${escapeHtml(x.name)}</b><div class="subtle">${escapeHtml(x.slug)}</div></td><td>${escapeHtml(brl(x.monthly_price))}</td><td>${escapeHtml(x.active_listing_limit)} anúncios • ${escapeHtml(x.direct_deal_limit??x.active_deal_limit??'-')} negociações</td><td>${escapeHtml(x.subscribers_count||0)} <div class="subtle">${escapeHtml(x.trials_count||0)} em teste</div></td><td>${badge(x.active?'active':'inactive')}</td><td>${actions([action('Editar','edit-plan',x.id),action(x.active?'Desativar':'Ativar','plan-status',x.id,x.active?'danger':'ok')])}</td></tr>`));

  $('advertisersTable').innerHTML=table(['PARCEIRO','CONTATO','STATUS','AÇÕES'],store.advertisers.map(x=>`<tr><td><b>${escapeHtml(x.name)}</b><div class="subtle">${escapeHtml(x.document||'-')}</div></td><td>${escapeHtml(x.contact_email||'-')}</td><td>${badge(x.active?'active':'inactive')}</td><td>${actions([action('Editar','edit-advertiser',x.id),action(x.active?'Desativar':'Ativar','advertiser-status',x.id,x.active?'danger':'ok')])}</td></tr>`));

  $('campaignsTable').innerHTML=table(['CAMPANHA','PARCEIRO','PERÍODO','MÉTRICAS','STATUS','AÇÕES'],store.campaigns.map(x=>{const ctr=Number(x.impressions)>0?(Number(x.clicks)/Number(x.impressions)*100).toFixed(2):'0.00';return `<tr><td><b>${escapeHtml(x.name)}</b><div class="subtle">${escapeHtml(x.headline)}</div></td><td>${escapeHtml(x.advertiser)}</td><td>${escapeHtml(asDate(x.starts_at))} a ${escapeHtml(asDate(x.ends_at))}</td><td>${escapeHtml(x.impressions||0)} imp. • ${escapeHtml(x.clicks||0)} cliques • ${escapeHtml(ctr)}%</td><td>${badge(x.active?'active':'inactive')}</td><td>${actions([action('Editar','edit-campaign',x.id),action(x.active?'Desativar':'Ativar','campaign-status',x.id,x.active?'danger':'ok')])}</td></tr>`}));

  $('auditTable').innerHTML=table(['DATA','ADMINISTRADOR','AÇÃO','REGISTRO','JUSTIFICATIVA'],store.audit.map(x=>`<tr><td>${escapeHtml(asDateTime(x.created_at))}</td><td>${escapeHtml(x.admin?.name||'-')}<div class="subtle">${escapeHtml(x.admin?.email||'')}</div></td><td>${escapeHtml(x.action)}</td><td>${escapeHtml(x.entity_type)} #${escapeHtml(x.entity_id)}</td><td class="auditReason">${escapeHtml(x.reason||'-')}</td></tr>`));
}

function fieldTemplate(field){
  const wide=field.wide?' wide':'';const required=field.required?' required':'';const help=field.help?`<div class="subtle">${escapeHtml(field.help)}</div>`:'';
  if(field.type==='checkbox') return `<div class="field${wide}"><label><input id="op_${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" type="checkbox" ${field.value?'checked':''}> ${escapeHtml(field.label)}</label>${help}</div>`;
  if(field.type==='select') return `<div class="field${wide}"><label for="op_${escapeHtml(field.name)}">${escapeHtml(field.label)}</label><select id="op_${escapeHtml(field.name)}" name="${escapeHtml(field.name)}"${required}>${(field.options||[]).map(option=>`<option value="${escapeHtml(option.value)}" ${String(option.value)===String(field.value)?'selected':''}>${escapeHtml(option.label)}</option>`).join('')}</select>${help}</div>`;
  if(field.type==='textarea') return `<div class="field${wide}"><label for="op_${escapeHtml(field.name)}">${escapeHtml(field.label)}</label><textarea id="op_${escapeHtml(field.name)}" name="${escapeHtml(field.name)}"${required}>${escapeHtml(field.value||'')}</textarea>${help}</div>`;
  if(field.type==='file') return `<div class="field${wide}"><label for="op_${escapeHtml(field.name)}">${escapeHtml(field.label)}</label><input id="op_${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" type="file" ${field.accept?`accept="${escapeHtml(field.accept)}"`:''}${required}><img id="preview_${escapeHtml(field.name)}" class="preview" alt="Prévia da imagem" hidden>${help}</div>`;
  return `<div class="field${wide}"><label for="op_${escapeHtml(field.name)}">${escapeHtml(field.label)}</label><input id="op_${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" type="${escapeHtml(field.type||'text')}" value="${escapeHtml(field.value??'')}" ${field.accept?`accept="${escapeHtml(field.accept)}"`:''}${field.readonly?' readonly':''}${required}>${help}</div>`;
}

function openForm({title,intro='',submitLabel='Salvar',fields,onSubmit}){
  modalConfig={fields,onSubmit};$('modalTitle').textContent=title;$('modalIntro').textContent=intro;$('modalIntro').hidden=!intro;$('modalFields').innerHTML=fields.map(fieldTemplate).join('');$('modalError').textContent='';$('modalSubmit').hidden=false;$('modalSubmit').textContent=submitLabel;$('operationModal').hidden=false;
  fields.filter(field=>field.type==='file').forEach(field=>$(`op_${field.name}`).addEventListener('change',event=>{const preview=$(`preview_${field.name}`),file=event.target.files?.[0];if(!file){preview.hidden=true;return}preview.src=URL.createObjectURL(file);preview.hidden=false}));
  const first=$('modalFields').querySelector('input:not([type=checkbox]),select,textarea');if(first)setTimeout(()=>first.focus(),0);
}
function openDetails(title,html){modalConfig=null;$('modalTitle').textContent=title;$('modalIntro').hidden=true;$('modalFields').innerHTML=`<div class="wide">${html}</div>`;$('modalError').textContent='';$('modalSubmit').hidden=true;$('operationModal').hidden=false}
function closeModal(){modalConfig=null;$('operationModal').hidden=true;$('modalSubmit').hidden=false;$('operationForm').reset();$('modalError').textContent=''}
$('modalClose').addEventListener('click',closeModal);$('modalCancel').addEventListener('click',closeModal);$('operationModal').addEventListener('click',event=>{if(event.target===$('operationModal'))closeModal()});
$('operationForm').addEventListener('submit',async event=>{
  event.preventDefault();if(!modalConfig)return;$('modalError').textContent='';$('modalSubmit').disabled=true;
  try{
    const payload={};
    for(const field of modalConfig.fields){const element=$(`op_${field.name}`);if(field.type==='file'){const file=element.files?.[0];if(file)payload[field.name]=await fileToDataUrl(file);continue}if(field.type==='checkbox'){payload[field.name]=element.checked;continue}payload[field.name]=element.value===''?null:element.value}
    await modalConfig.onSubmit(payload);closeModal();toast('Operação concluída.');await loadData();
  }catch(error){$('modalError').textContent=error.message;toast(error.message,true)}finally{$('modalSubmit').disabled=false}
});
const fileToDataUrl=file=>new Promise((resolve,reject)=>{const reader=new FileReader();reader.onerror=()=>reject(new Error('Não foi possível ler o arquivo.'));reader.onload=()=>{const image=new Image();image.onerror=()=>reject(new Error('A imagem selecionada é inválida.'));image.onload=()=>{const limit=1600,scale=Math.min(1,limit/Math.max(image.width,image.height));const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(image.width*scale));canvas.height=Math.max(1,Math.round(image.height*scale));canvas.getContext('2d').drawImage(image,0,0,canvas.width,canvas.height);let result=canvas.toDataURL('image/webp',.82);if(result.length>880000)result=canvas.toDataURL('image/jpeg',.68);if(result.length>900000){reject(new Error('A imagem continua muito grande. Escolha um arquivo menor.'));return}resolve(result)};image.src=reader.result};reader.readAsDataURL(file)});
const mutate=(path,method,payload)=>request(path,{method,body:JSON.stringify(payload)});
async function downloadAuthenticated(path,fallbackName){const response=await fetch(API_ROOT+path,{headers:{Accept:'application/octet-stream',Authorization:`Bearer ${token}`}});if(!response.ok){let message=`Falha ao baixar (${response.status})`;try{const payload=await response.json();message=payload.message||message}catch(_){}throw new Error(message)}const blob=await response.blob(),url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=fallbackName||'arquivo';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),30000)}
const reasonField=(label='Justificativa')=>({name:'reason',label,type:'textarea',required:true,wide:true,help:'Obrigatória para a trilha de auditoria.'});
const planOptions=()=>store.plans.filter(x=>x.active).map(x=>({value:x.id,label:`${x.name} — ${brl(x.monthly_price)}`}));
const sellerOptions=()=>store.users.filter(x=>x.kyc_status==='verified'&&(x.account_status||'active')==='active').map(x=>({value:x.id,label:`${x.name} — ${x.email}`}));
const advertiserOptions=()=>store.advertisers.filter(x=>x.active).map(x=>({value:x.id,label:x.name}));

document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(!button)return;handleAction(button.dataset.action,Number(button.dataset.id||0),button.dataset)});
async function handleAction(name,id,dataset={}){
  const user=store.users.find(x=>x.id===id),plan=store.plans.find(x=>x.id===id),listing=store.listings.find(x=>x.id===id),deal=store.deals.find(x=>x.id===id),installment=store.installments.find(x=>x.id===id),wallet=store.wallets.find(x=>x.id===id),advertiser=store.advertisers.find(x=>x.id===id),campaign=store.campaigns.find(x=>x.id===id);
  if(name==='edit-user')openForm({title:'Editar usuário',fields:[{name:'name',label:'Nome',value:user.name,required:true},{name:'email',label:'E-mail',type:'email',value:user.email,required:true},{name:'phone',label:'Telefone',value:user.phone,required:true}],onSubmit:data=>mutate(`/admin/users/${id}`,'PUT',data)});
  if(name==='user-status'){const active=(user.account_status||'active')==='active';openForm({title:active?'Bloquear usuário':'Ativar usuário',intro:active?'O usuário perderá as sessões ativas. O histórico será preservado.':'O usuário poderá voltar a acessar a conta.',submitLabel:active?'Bloquear':'Ativar',fields:[reasonField()],onSubmit:data=>mutate(`/admin/users/${id}/status`,'POST',{status:active?'blocked':'active',...data})})}
  if(name==='kyc-retry')openForm({title:'Liberar nova tentativa KYC',intro:'A sessão incompleta atual será encerrada e o usuário poderá iniciar outra validação.',submitLabel:'Liberar tentativa',fields:[reasonField()],onSubmit:data=>mutate(`/admin/users/${id}/kyc-retry`,'POST',data)});
  if(name==='password-reset')openForm({title:'Enviar recuperação de senha',intro:`Um link válido por 30 minutos será enviado para ${user.email}.`,submitLabel:'Enviar link',fields:[reasonField()],onSubmit:data=>mutate(`/admin/users/${id}/password-reset`,'POST',data)});
  if(name==='extend-trial')openForm({title:'Estender teste gratuito',fields:[{name:'days',label:'Dias adicionais',type:'number',value:30,required:true},reasonField()],onSubmit:data=>mutate(`/admin/users/${id}/trial`,'POST',data)});
  if(name==='assign-plan')openForm({title:'Alterar plano do usuário',intro:'A assinatura atual será encerrada e substituída pela selecionada.',fields:[{name:'plan_id',label:'Plano',type:'select',options:planOptions(),required:true},{name:'status',label:'Tipo de assinatura',type:'select',options:[{value:'active',label:'Ativa'},{value:'trial',label:'Teste'}],required:true},{name:'days',label:'Vigência em dias',type:'number',value:30,required:true},reasonField()],onSubmit:data=>mutate(`/admin/users/${id}/plan`,'POST',data)});

  if(name==='new-plan'||name==='edit-plan'){const x=plan||{},fields=[{name:'name',label:'Nome',value:x.name,required:true},{name:'slug',label:'Identificador',value:x.slug,required:true,readonly:!!plan,help:'Letras minúsculas, números e hífen.'},{name:'monthly_price',label:'Mensalidade',type:'number',value:x.monthly_price??0,required:true},{name:'active_listing_limit',label:'Limite de anúncios',type:'number',value:x.active_listing_limit??1,required:true},{name:'direct_deal_limit',label:'Limite de negociações',type:'number',value:x.direct_deal_limit??1,required:true}];if(!plan)fields.push({name:'active',label:'Plano ativo',type:'checkbox',value:true});openForm({title:plan?'Editar plano':'Novo plano',fields,onSubmit:data=>mutate(plan?`/admin/plans/${id}`:'/admin/plans',plan?'PUT':'POST',data)})}
  if(name==='plan-status')openForm({title:plan.active?'Desativar plano':'Ativar plano',intro:'Assinaturas existentes são preservadas.',fields:[reasonField()],onSubmit:data=>mutate(`/admin/plans/${id}/status`,'POST',{active:!plan.active,...data})});

  if(name==='new-listing'||name==='edit-listing'){const x=listing||{};const fields=[];if(!listing)fields.push({name:'seller_id',label:'Vendedor verificado',type:'select',options:sellerOptions(),required:true});fields.push({name:'category',label:'Categoria',value:x.category,required:true},{name:'title',label:'Título',value:x.title,required:true},{name:'description',label:'Descrição',type:'textarea',value:x.description,required:true,wide:true},{name:'cover_image',label:listing?'Substituir imagem (opcional)':'Imagem principal',type:'file',accept:'image/*',required:!listing,wide:true},{name:'price',label:'Valor',type:'number',value:x.price,required:true},{name:'accepts_trade',label:'Vem na catira',type:'checkbox',value:x.accepts_trade});if(!listing)fields.push({name:'status',label:'Publicação',type:'select',options:[{value:'draft',label:'Salvar como rascunho'},{value:'published',label:'Publicar agora'}]});openForm({title:listing?'Editar anúncio':'Novo anúncio',fields,onSubmit:data=>mutate(listing?`/admin/listings/${id}`:'/admin/listings',listing?'PUT':'POST',data)})}
  if(name==='listing-status'){const publish=listing.status!=='published';openForm({title:publish?'Publicar anúncio':'Arquivar anúncio',intro:publish?'O anúncio ficará visível nos classificados.':'O anúncio sairá da vitrine, mas o histórico será preservado.',fields:[reasonField()],onSubmit:data=>mutate(`/admin/listings/${id}/status`,'POST',{status:publish?'published':'archived',...data})})}

  if(name==='deal-status'){const suspended=deal.status==='suspended';openForm({title:suspended?'Reativar negociação':'Suspender negociação',intro:'Documentos e histórico não serão removidos.',fields:[...(suspended?[{name:'status',label:'Retomar como',type:'select',options:[{value:'active',label:'Ativa'},{value:'overdue',label:'Em atraso'}]}]:[]),reasonField()],onSubmit:data=>mutate(`/admin/deals/${id}/status`,'POST',{status:suspended?data.status:'suspended',reason:data.reason})})}
  if(name==='cancel-deal')openForm({title:'Cancelar negociação',intro:'O cancelamento preserva contratos, documentos e comprovantes.',submitLabel:'Cancelar negociação',fields:[reasonField()],onSubmit:data=>mutate(`/admin/deals/${id}/status`,'POST',{status:'cancelled',...data})});
  if(name==='deal-details'){try{const details=await request(`/admin/deals/${id}/details`);const docs=(details.documents||[]).map(doc=>`<tr><td>${escapeHtml(labels[doc.type]||doc.type)}</td><td>${escapeHtml(doc.original_name)}</td><td>${escapeHtml(asDateTime(doc.created_at))}</td><td><button class="action" data-action="download-deal-doc" data-id="${escapeHtml(doc.id)}" data-deal="${escapeHtml(id)}">Baixar</button></td></tr>`);const events=(details.events||[]).slice(0,20).map(event=>`<tr><td>${escapeHtml(asDateTime(event.created_at))}</td><td>${escapeHtml(event.type)}</td></tr>`);openDetails(`Negociação ${details.deal.public_id}`,`<div class="notice"><b>${escapeHtml(details.deal.seller?.name||'-')} → ${escapeHtml(details.deal.buyer?.name||'-')}</b><br>${escapeHtml(brl(details.deal.total_amount))} • ${badge(details.deal.status)}</div><h3>Documentos</h3>${table(['TIPO','ARQUIVO','DATA',''],docs)}<h3>Últimos eventos</h3>${table(['DATA','EVENTO'],events)}`)}catch(error){toast(error.message,true)}}
  if(name==='download-deal-doc'){try{await downloadAuthenticated(`/admin/deals/${dataset.deal}/documents/${id}`,'documento')}catch(error){toast(error.message,true)}}

  if(name==='installment-date')openForm({title:'Alterar vencimento',fields:[{name:'due_date',label:'Novo vencimento',type:'date',value:String(installment.due_date||'').slice(0,10),required:true},reasonField()],onSubmit:data=>mutate(`/admin/installments/${id}`,'PUT',data)});
  if(name==='reject-receipt')openForm({title:'Rejeitar comprovante',intro:'O documento permanece no histórico de auditoria e o comprador poderá enviar outro.',submitLabel:'Rejeitar',fields:[reasonField()],onSubmit:data=>mutate(`/admin/installments/${id}/reject-receipt`,'POST',data)});
  if(name==='reconcile-installment')openForm({title:'Conciliar pagamento',intro:`Será creditado ${brl(installment.amount)} na carteira do vendedor.`,submitLabel:'Confirmar conciliação',fields:[{name:'external_payment_id',label:'Referência externa',value:''},reasonField()],onSubmit:data=>mutate(`/admin/installments/${id}/reconcile`,'POST',data)});
  if(name==='view-receipt'){try{await downloadAuthenticated(`/admin/installments/${id}/receipt`,`comprovante-parcela-${installment.number}`)}catch(error){toast(error.message,true)}}

  if(name==='adjust-wallet')openForm({title:'Lançar ajuste na carteira',intro:'O saldo será alterado por uma transação rastreável.',fields:[{name:'direction',label:'Operação',type:'select',options:[{value:'credit',label:'Crédito'},{value:'debit',label:'Débito'}]},{name:'amount',label:'Valor',type:'number',required:true},reasonField()],onSubmit:data=>mutate(`/admin/wallets/${id}/adjust`,'POST',data)});
  if(name==='wallet-status'){const active=wallet.status==='active';openForm({title:active?'Suspender carteira':'Ativar carteira',fields:[reasonField()],onSubmit:data=>mutate(`/admin/wallets/${id}/status`,'POST',{status:active?'suspended':'active',...data})})}

  if(name==='new-advertiser'||name==='edit-advertiser'){const x=advertiser||{};openForm({title:advertiser?'Editar parceiro':'Novo parceiro',fields:[{name:'name',label:'Nome da empresa',value:x.name,required:true},{name:'document',label:'CNPJ/Documento',value:x.document},{name:'contact_email',label:'E-mail de contato',type:'email',value:x.contact_email},{name:'active',label:'Parceiro ativo',type:'checkbox',value:x.active??true}],onSubmit:data=>mutate(advertiser?`/admin/advertisers/${id}`:'/admin/advertisers',advertiser?'PUT':'POST',data)})}
  if(name==='advertiser-status')openForm({title:advertiser.active?'Desativar parceiro':'Ativar parceiro',intro:advertiser.active?'Todas as campanhas do parceiro também serão desativadas.':'As campanhas precisam ser reativadas individualmente.',fields:[reasonField()],onSubmit:data=>mutate(`/admin/advertisers/${id}/status`,'POST',{active:!advertiser.active,...data})});

  if(name==='new-campaign'||name==='edit-campaign'){const x=campaign||{},fields=[{name:'advertiser_id',label:'Parceiro',type:'select',options:advertiserOptions(),value:x.advertiser_id,required:true},{name:'name',label:'Nome interno',value:x.name,required:true},{name:'headline',label:'Chamada',value:x.headline,required:true,wide:true},{name:'cta',label:'Texto do botão',value:x.cta||'Conhecer oferta',required:true},{name:'target_url',label:'Link HTTPS',type:'url',value:x.target_url},{name:'media_path',label:campaign?'Substituir mídia (opcional)':'Imagem/banner',type:'file',accept:'image/*',required:!campaign,wide:true},{name:'placement',label:'Posição',type:'select',options:[{value:'home',label:'Home'}],value:x.placement||'home'},{name:'priority',label:'Prioridade',type:'number',value:x.priority??100,required:true},{name:'starts_at',label:'Início',type:'datetime-local',value:localDateTime(x.starts_at)||localDateTime(new Date()),required:true},{name:'ends_at',label:'Término',type:'datetime-local',value:localDateTime(x.ends_at)||localDateTime(new Date(Date.now()+30*86400000)),required:true}];if(!campaign)fields.push({name:'active',label:'Campanha ativa',type:'checkbox',value:true});openForm({title:campaign?'Editar campanha':'Nova campanha',fields,onSubmit:data=>mutate(campaign?`/admin/campaigns/${id}`:'/admin/campaigns',campaign?'PUT':'POST',data)})}
  if(name==='campaign-status')openForm({title:campaign.active?'Desativar campanha':'Ativar campanha',fields:[reasonField()],onSubmit:data=>mutate(`/admin/campaigns/${id}/status`,'POST',{active:!campaign.active,...data})});
}

document.querySelectorAll('[data-screen]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.screen').forEach(screen=>screen.classList.remove('on'));$(button.dataset.screen).classList.add('on');document.querySelectorAll('[data-screen]').forEach(item=>item.classList.remove('on'));button.classList.add('on');$('pageTitle').textContent=button.textContent}));
$('refreshButton').addEventListener('click',loadData);
$('logoutButton').addEventListener('click',async()=>{try{await request('/admin/auth/logout',{method:'POST'})}catch(_){}showAuth()});

(async()=>{if(!token){showAuth();return}try{const user=await request('/admin/auth/me');showPanel(user);await loadData()}catch(_){showAuth()}})();
