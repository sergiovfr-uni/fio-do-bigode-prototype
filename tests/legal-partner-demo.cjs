const fs=require('fs'),vm=require('vm'),assert=require('assert');
const root=require('path').resolve(__dirname,'..');
const html=fs.readFileSync(root+'/parceiro-juridico/painel/index.html','utf8'),script=fs.readFileSync(root+'/parceiro-juridico/painel/app.js','utf8');
const store=new Map();let blob;
function boot(){
 const element=id=>({id,hidden:false,value:'',innerHTML:'',textContent:'',events:{},dataset:{},classList:{toggle(){}},addEventListener(k,f){this.events[k]=f},querySelector(){return {focus(){}}},focus(){},showModal(){this.open=true},close(){this.open=false},click(){this.events.click?.()},remove(){}});
 const els=Object.fromEntries([...html.matchAll(/id="([^"]+)"/g)].map(m=>[m[1],element(m[1])]));
 const views=['overview','requests','case','office'].map(id=>els[id]),nav=['overview','requests','office'].map(view=>({...element('nav'),dataset:{view}})),tabs=['summary','documents','payments','history'].map(tab=>({...element('tab'),dataset:{tab}}));
 const docEvents={};const context={document:{getElementById:id=>{assert(els[id],`Unknown HTML id ${id}`);return els[id]},querySelectorAll:s=>s==='.view'?views:s==='[data-view]'?nav:s==='[data-tab]'?tabs:[],addEventListener:(k,f)=>docEvents[k]=f,createElement:()=>element('download'),body:{appendChild(){}}},localStorage:{getItem:k=>store.get(k)||null,setItem:(k,v)=>store.set(k,v)},window:{scrollTo(){}},setTimeout:()=>1,clearTimeout(){},Blob,URL:{createObjectURL:b=>{blob=b;return 'blob:demo'},revokeObjectURL(){}},fetch(){throw Error('Demo must never access real APIs')},console};
 vm.createContext(context);vm.runInContext(script,context);return {els,tabs,nav,openCase(){docEvents.click({target:{closest:s=>s==='[data-open-case]'?{}:null}})},openDoc(id){docEvents.click({target:{closest:s=>s==='[data-document]'?{dataset:{document:id}}:null}})}};
}
(async()=>{
 let app=boot(),e=app.els;e.enterDemo.click();assert(e.entry.hidden&&!e.workspace.hidden);assert(e.metrics.innerHTML.includes('Um caso fictício'));
 app.openCase();const summary=e.caseContent.innerHTML;assert(summary.includes('12.000,00')&&summary.includes('4.000,00')&&summary.includes('8.000,00'));
 e.caseState.value='closed';e.saveStatus.click();assert(e.caseStatus.innerHTML.includes('Encerrado'));assert.strictEqual(e.caseContent.innerHTML,summary,'Status must not alter agreement or financial summary');
 e.note.value='<img src=x onerror=alert(1)>';e.saveNote.click();assert(e.notes.innerHTML.includes('&lt;img'));assert(!e.notes.innerHTML.includes('<img'));
 e.exportDossier.click();let report=await blob.text();assert(report.includes('SEM VALIDADE JURÍDICA'));assert(report.includes('&lt;img'));assert(!report.includes('<img'));assert(report.includes('parcela')||report.includes('Parcela'));
 app.openDoc('contract');assert(e.documentDialog.open);assert(e.documentBody.textContent.includes('CONTRATO DEMONSTRATIVO'));e.downloadDocument.click();assert((await blob.text()).includes('EXEMPLO FICTÍCIO'));
 e.statusFilter.value='analysis';e.statusFilter.events.change();assert(e.requestList.innerHTML.includes('Nenhuma solicitação'));e.statusFilter.value='closed';e.search.value='Alice';e.search.events.input();assert(e.requestList.innerHTML.includes('Ver dossiê'));
 app=boot();e=app.els;e.enterDemo.click();app.openCase();assert(e.caseStatus.innerHTML.includes('Encerrado'));assert(e.notes.innerHTML.includes('&lt;img'));
 e.resetDemo.click();assert(e.resetDialog.open);e.confirmReset.click();assert(e.caseStatus.innerHTML.includes('Recebido'));assert(!e.notes.innerHTML);assert.strictEqual(e.caseContent.innerHTML,summary);
 assert.strictEqual([...store.keys()].length,1);assert([...store.keys()][0]==='fdb_legal_partner_portal_demo_v1');
 console.log('PASS: entry, request access, reconciled amounts, status isolated from agreement, safe notes/report, fictitious document download, filters, persistence, reset and storage isolation; no API calls');
})().catch(e=>{console.error(e);process.exit(1)});
