import React, { useState } from 'react';
import { SafeAreaView, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';

type Screen = 'login' | 'home' | 'deals' | 'new' | 'wallet' | 'profile' | 'agreement';

type NavProps = { current: Screen; go: (s: Screen) => void };

const gold = '#C88A05';
const ink = '#111111';
const muted = '#71717A';
const line = '#E7E2DA';

function Card({ children, dark = false }: { children: React.ReactNode; dark?: boolean }) {
  return <View style={[styles.card, dark && styles.darkCard]}>{children}</View>;
}

function Button({ title, onPress, secondary = false }: { title: string; onPress: () => void; secondary?: boolean }) {
  return <TouchableOpacity style={[styles.button, secondary && styles.secondaryButton]} onPress={onPress}><Text style={[styles.buttonText, secondary && styles.secondaryButtonText]}>{title}</Text></TouchableOpacity>;
}

function BottomNav({ current, go }: NavProps) {
  const item = (screen: Screen, icon: string, label: string) => (
    <TouchableOpacity style={styles.navItem} onPress={() => go(screen)}>
      <Text style={styles.navIcon}>{icon}</Text>
      <Text style={[styles.navLabel, current === screen && styles.navActive]}>{label}</Text>
    </TouchableOpacity>
  );
  return <View style={styles.nav}>
    {item('home','⌂','Início')}
    {item('deals','▤','Negociações')}
    <TouchableOpacity style={styles.plus} onPress={() => go('new')}><Text style={styles.plusText}>＋</Text></TouchableOpacity>
    {item('wallet','▣','Carteira')}
    {item('profile','○','Perfil')}
  </View>;
}

function Login({ go }: { go:(s:Screen)=>void }) {
  return <ScrollView contentContainerStyle={styles.authWrap}>
    <View style={styles.hero}>
      <Text style={styles.mustache}>〰</Text>
      <Text style={styles.brand}>FIO DO BIGODE</Text>
      <Text style={styles.heroText}>Combinou. Registrou.\nTá no Fio do Bigode.</Text>
    </View>
    <Text style={styles.title}>Boas-vindas!</Text>
    <Text style={styles.subtitle}>Entre para registrar seus acordos com segurança.</Text>
    <TextInput style={styles.input} value="joaosilva@email.com" />
    <TextInput style={styles.input} value="123456" secureTextEntry />
    <Button title="Entrar" onPress={() => go('home')} />
  </ScrollView>;
}

function Home({ go }: { go:(s:Screen)=>void }) {
  return <ScrollView contentContainerStyle={styles.content}>
    <View style={styles.row}><View><Text style={styles.title}>Olá, João! 👋</Text><Text style={styles.subtitle}>Seus acordos em um só lugar.</Text></View><Text>🔔</Text></View>
    <Card dark><Text style={styles.darkLabel}>A receber</Text><Text style={styles.darkMetric}>R$ 4.500,00</Text><Text style={styles.darkLabel}>2 negociações ativas</Text></Card>
    <View style={styles.twoCols}><Card><Text style={styles.label}>A pagar</Text><Text style={styles.metricRed}>R$ 1.200</Text></Card><Card><Text style={styles.label}>Em atraso</Text><Text style={styles.metricRed}>R$ 800</Text></Card></View>
    <Text style={styles.section}>Conta digital integrada</Text>
    <TouchableOpacity onPress={() => go('wallet')}>
      <Card dark><View style={styles.row}><View><Text style={styles.bankBrand}>〰 BIGODE BANK</Text><Text style={styles.darkLabel}>BaaS demonstrativo</Text></View><Text style={styles.badgeGold}>BaaS</Text></View><Text style={[styles.darkLabel,{marginTop:14}]}>Saldo disponível</Text><Text style={styles.darkMetric}>R$ 2.350,00</Text><Text style={styles.darkLabel}>PIX • Receber • Pagar • Extrato</Text></Card>
    </TouchableOpacity>
    <Text style={styles.section}>Próximo compromisso</Text>
    <TouchableOpacity onPress={() => go('agreement')}><Card><View style={styles.row}><View><Text style={styles.cardTitle}>Honda CG 160 2022</Text><Text style={styles.subtitle}>João da Silva • 10/09/2026</Text></View><Text style={styles.amount}>R$ 1.000</Text></View></Card></TouchableOpacity>
    <Text style={styles.section}>Parceiro em destaque</Text>
    <Card><Text style={styles.adTag}>PUBLICIDADE</Text><Text style={styles.cardTitle}>🏍️ Proteção para sua moto</Text><Text style={styles.subtitle}>Seguro e assistência para quem negocia com tranquilidade.</Text><Text style={styles.adCta}>CONHECER →</Text></Card>
  </ScrollView>;
}

function Deals({ go }: { go:(s:Screen)=>void }) {
  const deals = [
    ['Honda CG 160 2022','João da Silva','Em andamento','R$ 7.000'],
    ['Notebook Dell Inspiron','Maria Santos','A pagar','R$ 1.200'],
    ['iPhone 15 Pro','Carlos Andrade','Em atraso','R$ 800'],
    ['Sofá retrátil','Pedro Lima','Quitado','R$ 0'],
  ];
  return <ScrollView contentContainerStyle={styles.content}><Text style={styles.title}>Negociações</Text><Text style={styles.subtitle}>Acompanhe todos os seus acordos.</Text>{deals.map((d,i)=><TouchableOpacity key={i} onPress={() => go('agreement')}><Card><View style={styles.row}><View style={{flex:1}}><Text style={styles.cardTitle}>{d[0]}</Text><Text style={styles.subtitle}>{d[1]}</Text></View><Text style={styles.badge}>{d[2]}</Text></View><View style={[styles.row,{marginTop:12}]}><Text style={styles.label}>Saldo</Text><Text style={styles.amount}>{d[3]}</Text></View></Card></TouchableOpacity>)}</ScrollView>;
}

function NewAgreement({ go }: { go:(s:Screen)=>void }) {
  const [useBaas,setUseBaas] = useState(true);
  return <ScrollView contentContainerStyle={styles.content}><Text style={styles.title}>Novo acordo</Text><Text style={styles.subtitle}>Comece descrevendo o que está negociando.</Text><TextInput style={styles.input} value="Honda CG 160 2022" /><TextInput style={styles.input} value="R$ 12.000,00" /><TextInput style={styles.input} value="Entrada: R$ 3.000,00" /><TextInput style={styles.input} value="9 parcelas de R$ 1.000,00" />
    <Text style={styles.section}>Como deseja receber?</Text>
    <TouchableOpacity onPress={()=>setUseBaas(false)}><Card><View style={styles.row}><View><Text style={styles.cardTitle}>Controle manual</Text><Text style={styles.subtitle}>Comprovante + confirmação entre usuários</Text></View><Text>{!useBaas?'●':'○'}</Text></View></Card></TouchableOpacity>
    <TouchableOpacity onPress={()=>setUseBaas(true)}><Card dark><View style={styles.row}><View style={{flex:1}}><Text style={styles.bankBrand}>〰 BIGODE BANK</Text><Text style={styles.darkLabel}>Receber pela Conta Fio do Bigode</Text></View><Text style={styles.badgeGold}>{useBaas?'●':'○'}</Text></View><Text style={[styles.darkLabel,{marginTop:12}]}>✓ PIX integrado  ✓ Conciliação automática\n✓ Identificação da parcela  ✓ Histórico financeiro</Text></Card></TouchableOpacity>
    <Button title="Continuar" onPress={() => go('agreement')} />
  </ScrollView>;
}

function Wallet() {
  return <ScrollView contentContainerStyle={styles.content}><Text style={styles.title}>Carteira</Text><Text style={styles.subtitle}>Conta digital integrada ao Fio do Bigode.</Text><Card dark><View style={styles.row}><Text style={styles.bankBrand}>〰 BIGODE BANK</Text><Text style={styles.badgeGold}>BaaS</Text></View><Text style={[styles.darkLabel,{marginTop:16}]}>Saldo disponível</Text><Text style={styles.darkMetric}>R$ 2.350,00</Text><Text style={styles.darkLabel}>Agência 0001 • Conta 12345-6</Text></Card><View style={styles.quickRow}><View style={styles.quick}><Text style={styles.quickIcon}>◉</Text><Text>PIX</Text></View><View style={styles.quick}><Text style={styles.quickIcon}>↓</Text><Text>Receber</Text></View><View style={styles.quick}><Text style={styles.quickIcon}>↑</Text><Text>Pagar</Text></View></View><Text style={styles.section}>Últimas movimentações</Text><Card><Text style={styles.cardTitle}>+ R$ 1.000,00</Text><Text style={styles.subtitle}>Parcela Honda CG 160 • 08/09</Text></Card><Card><Text style={styles.cardTitle}>- R$ 350,00</Text><Text style={styles.subtitle}>PIX enviado • 07/09</Text></Card><Text style={styles.demo}>Ambiente demonstrativo — instituição financeira fictícia.</Text></ScrollView>;
}

function Agreement() {
  return <ScrollView contentContainerStyle={styles.content}><Text style={styles.title}>Honda CG 160 2022</Text><Text style={styles.badge}>Em andamento</Text><Card><View style={styles.row}><Text style={styles.label}>Valor total</Text><Text style={styles.amount}>R$ 12.000</Text></View><View style={styles.row}><Text style={styles.label}>Saldo devedor</Text><Text style={styles.amount}>R$ 7.000</Text></View><Text style={[styles.subtitle,{marginTop:10}]}>2 de 9 parcelas pagas</Text></Card><Text style={styles.section}>Próxima parcela</Text><Card dark><Text style={styles.darkLabel}>3ª parcela • 10/09/2026</Text><Text style={styles.darkMetric}>R$ 1.000,00</Text><Text style={styles.bankBrand}>〰 BIGODE BANK</Text><Text style={styles.darkLabel}>Cobrança PIX vinculada • conciliação automática</Text></Card><Button title="Gerar PIX para pagamento" onPress={()=>{}} /><Button title="Documentos do acordo" onPress={()=>{}} secondary /></ScrollView>;
}

function Profile() {
  return <ScrollView contentContainerStyle={styles.content}><Text style={styles.title}>Meu perfil</Text><Card dark><Text style={styles.bankBrand}>João Silva</Text><Text style={styles.darkLabel}>Identidade verificada ✓</Text><Text style={styles.stars}>★★★★★ 4,8</Text></Card><View style={styles.twoCols}><Card><Text style={styles.centerMetric}>14</Text><Text style={styles.centerLabel}>Acordos</Text></Card><Card><Text style={styles.centerMetric}>12</Text><Text style={styles.centerLabel}>Quitados</Text></Card></View><Card><Text>✓ CPF verificado</Text></Card><Card><Text>✓ Celular verificado</Text></Card><Card><Text>✓ E-mail verificado</Text></Card></ScrollView>;
}

export default function App() {
  const [screen,setScreen] = useState<Screen>('login');
  const showNav = screen !== 'login';
  return <SafeAreaView style={styles.safe}><StatusBar style="dark"/><View style={styles.app}>{screen==='login'&&<Login go={setScreen}/>} {screen==='home'&&<Home go={setScreen}/>} {screen==='deals'&&<Deals go={setScreen}/>} {screen==='new'&&<NewAgreement go={setScreen}/>} {screen==='wallet'&&<Wallet/>} {screen==='profile'&&<Profile/>} {screen==='agreement'&&<Agreement/>}{showNav&&<BottomNav current={screen} go={setScreen}/>}</View></SafeAreaView>;
}

const styles = StyleSheet.create({
  safe:{flex:1,backgroundColor:'#fff'}, app:{flex:1,backgroundColor:'#fff'}, content:{padding:20,paddingBottom:110}, authWrap:{padding:20},
  hero:{backgroundColor:'#222',marginHorizontal:-20,marginTop:-20,paddingVertical:84,paddingHorizontal:20,alignItems:'center',marginBottom:24}, mustache:{fontSize:58,color:'#D5A12D'},brand:{color:'#fff',fontWeight:'800',letterSpacing:3,fontSize:20},heroText:{color:'#eee',textAlign:'center',marginTop:24,lineHeight:22},
  title:{fontSize:27,fontWeight:'800',color:ink,marginBottom:4},subtitle:{fontSize:13,color:muted,lineHeight:19},input:{borderWidth:1,borderColor:line,borderRadius:12,padding:14,fontSize:16,marginTop:12,backgroundColor:'#fff'},
  button:{backgroundColor:ink,borderRadius:12,padding:15,alignItems:'center',marginTop:12},buttonText:{color:'#fff',fontWeight:'800'},secondaryButton:{backgroundColor:'#fff',borderWidth:1,borderColor:line},secondaryButtonText:{color:ink},
  card:{borderWidth:1,borderColor:line,borderRadius:16,padding:15,marginTop:10,flex:1},darkCard:{backgroundColor:'#222',borderColor:'#222'},row:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',gap:10},twoCols:{flexDirection:'row',gap:10},label:{fontSize:12,color:muted},metricRed:{fontSize:21,fontWeight:'800',color:'#C43E3E',marginTop:4},darkLabel:{fontSize:13,color:'#CCC'},darkMetric:{fontSize:25,fontWeight:'800',color:'#fff',marginVertical:4},section:{fontSize:17,fontWeight:'800',marginTop:22,marginBottom:2},cardTitle:{fontSize:15,fontWeight:'800',color:ink},amount:{fontSize:15,fontWeight:'800'},badge:{fontSize:11,backgroundColor:'#E8F5EC',color:'#287A3E',paddingHorizontal:8,paddingVertical:5,borderRadius:20},badgeGold:{fontSize:11,backgroundColor:'#FFF2CD',color:'#8A6200',paddingHorizontal:8,paddingVertical:5,borderRadius:20,overflow:'hidden'},bankBrand:{fontSize:16,fontWeight:'800',color:'#fff'},stars:{color:'#D5A12D',fontSize:16,marginTop:8},
  adTag:{fontSize:10,color:muted,letterSpacing:1,fontWeight:'700',marginBottom:8},adCta:{color:gold,fontWeight:'800',marginTop:12},quickRow:{flexDirection:'row',gap:10,marginTop:12},quick:{flex:1,borderWidth:1,borderColor:line,borderRadius:14,padding:14,alignItems:'center'},quickIcon:{fontSize:24,marginBottom:5},demo:{fontSize:11,color:muted,textAlign:'center',marginTop:20},centerMetric:{fontSize:24,fontWeight:'800',textAlign:'center'},centerLabel:{fontSize:12,color:muted,textAlign:'center'},
  nav:{position:'absolute',left:0,right:0,bottom:0,backgroundColor:'#fff',borderTopWidth:1,borderTopColor:line,flexDirection:'row',justifyContent:'space-around',alignItems:'center',paddingTop:8,paddingBottom:14},navItem:{minWidth:58,alignItems:'center'},navIcon:{fontSize:20,color:'#555'},navLabel:{fontSize:10,color:muted,marginTop:2},navActive:{color:ink,fontWeight:'800'},plus:{width:52,height:52,borderRadius:26,backgroundColor:ink,alignItems:'center',justifyContent:'center',marginTop:-28},plusText:{color:'#fff',fontSize:28}
});
