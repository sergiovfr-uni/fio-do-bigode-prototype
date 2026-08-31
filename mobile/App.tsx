import { StatusBar } from 'expo-status-bar';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  BackHandler,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { WebView } from 'react-native-webview';
import type { WebViewNavigation } from 'react-native-webview';
import type { WebViewMessageEvent } from 'react-native-webview';

const FALLBACK_APP_URL = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html';
const APP_URL = process.env.EXPO_PUBLIC_APP_URL || FALLBACK_APP_URL;

const HOMOLOGATION_UX_FIXES = `
(function () {
  if (window.__fdbUxFixes061) return true;
  window.__fdbUxFixes061 = true;

  var style = document.createElement('style');
  style.textContent = [
    '.appModalBackdrop{place-items:center!important;align-items:center!important;justify-items:center!important;padding:max(18px,env(safe-area-inset-top,0px)) 18px max(18px,env(safe-area-inset-bottom,0px))!important;overflow:auto!important}',
    '.appModal{margin:auto!important;max-height:calc(100dvh - 36px)!important;overflow:auto!important}',
    '.fdbSignatureLocked{display:none!important}',
    '.partnerSection{margin-top:18px}',
    '.partnerCarousel{display:flex;gap:12px;overflow-x:auto;padding:2px 0 8px;scroll-snap-type:x mandatory;scrollbar-width:none}',
    '.partnerCarousel::-webkit-scrollbar{display:none}',
    '.partnerCard{flex:0 0 78%;max-width:310px;scroll-snap-align:start;border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;box-shadow:0 5px 16px #00000008}',
    '.partnerMedia{height:145px;background:linear-gradient(135deg,#171717,#d49a13);display:grid;place-items:center;overflow:hidden}',
    '.partnerMedia img{width:100%;height:100%;object-fit:cover}',
    '.partnerInitial{width:68px;height:68px;border-radius:50%;display:grid;place-items:center;background:#111;color:var(--g2);font-size:28px;font-weight:900}',
    '.partnerBody{padding:13px}',
    '.partnerPlatform{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;color:#8b6410}',
    '.partnerName{display:block;font-size:15px;margin:4px 0 6px}',
    '.partnerText{font-size:12px;color:#666;line-height:1.4;min-height:34px}',
    '.partnerLink{font-size:11px;font-weight:900;color:#8b6410;margin-top:8px}'
  ].join('');
  document.head.appendChild(style);

  document.title = 'Fio do Bigode • Homologação v0.6.1';
  var version = document.querySelector('.version');
  if (version) version.textContent = 'Homologação v0.6.1 • jornada E2E validada';

  function safeText(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
    });
  }

  function patchQualification() {
    if (typeof window.saveQualification !== 'function' || window.saveQualification.__fdbPatched) return;
    var original = window.saveQualification;
    var patched = async function () {
      await original.apply(this, arguments);
      var status = document.getElementById('qualificationStatus');
      if (status && /salvos|concluídos/i.test(status.textContent || '')) {
        var form = document.getElementById('qualificationForm');
        if (form) form.classList.add('hidden');
        var notice = document.getElementById('qualificationNotice');
        if (notice) notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    };
    patched.__fdbPatched = true;
    window.saveQualification = patched;
  }

  function configureSignatureStep() {
    var code = document.getElementById('electronicSignatureCode');
    var canvas = document.getElementById('electronicSignatureCanvas');
    if (!code || !canvas || code.__fdbGateConfigured) return;
    code.__fdbGateConfigured = true;
    code.setAttribute('autocomplete', 'one-time-code');

    var label = canvas.previousElementSibling;
    var clearButton = canvas.nextElementSibling;
    var consent = clearButton && clearButton.nextElementSibling;
    var confirmButton = consent && consent.nextElementSibling;
    var gated = [label, canvas, clearButton, consent, confirmButton].filter(Boolean);
    var hint = document.createElement('div');
    hint.className = 'muted';
    hint.textContent = 'Digite os 6 números enviados por e-mail para liberar o campo de assinatura.';
    code.insertAdjacentElement('afterend', hint);

    function updateGate() {
      var digits = String(code.value || '').replace(/\\D/g, '');
      if (code.value !== digits) code.value = digits;
      var ready = digits.length === 6;
      gated.forEach(function (element) {
        element.classList.toggle('fdbSignatureLocked', !ready);
      });
      hint.classList.toggle('hidden', ready);
      if (ready && typeof window.initElectronicSignatureCanvas === 'function') {
        setTimeout(function () { try { window.initElectronicSignatureCanvas(); } catch (_) {} }, 0);
      }
    }

    code.addEventListener('input', updateGate);
    updateGate();
    code.focus();
  }

  function arrangeHome() {
    var home = document.getElementById('home');
    var campaigns = document.getElementById('campaignArea');
    var categories = document.getElementById('homeCategories');
    if (!home || !campaigns || !categories) return;
    var campaignTitle = campaigns.previousElementSibling;
    var categoryTitle = categories.previousElementSibling;
    if (!campaignTitle || !categoryTitle) return;
    campaignTitle.textContent = 'Publicidade';
    home.insertBefore(campaignTitle, categoryTitle);
    home.insertBefore(campaigns, categoryTitle);
    if (!document.getElementById('homePartnersSection')) {
      var section = document.createElement('div');
      section.id = 'homePartnersSection';
      section.className = 'partnerSection';
      section.innerHTML = '<div class="section">Quem está com a gente</div><div id="homePartners" class="partnerCarousel"><div class="card muted" style="flex:0 0 78%">Carregando parceiros...</div></div>';
      home.insertBefore(section, categoryTitle);
      loadPartners();
    }
  }

  async function loadPartners() {
    var area = document.getElementById('homePartners');
    var section = document.getElementById('homePartnersSection');
    if (!area || !section) return;
    try {
      var response = await fetch('https://api.nofiodobigode.app.br/api/v1/community-partners', { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Falha ao carregar parceiros');
      var items = await response.json();
      if (!Array.isArray(items) || !items.length) { section.classList.add('hidden'); return; }
      area.innerHTML = items.map(function (item) {
        var image = item.post_image_url || item.featured_image_url || item.avatar_url || '';
        var target = item.post_url || item.profile_url || '#';
        var text = item.post_text || item.audience_label || item.description || 'Conheça quem ajuda a espalhar o Fio do Bigode.';
        var platform = item.platform || 'parceiro';
        return '<a class="partnerCard" href="' + safeText(target) + '" target="_blank" rel="noopener noreferrer">'
          + '<div class="partnerMedia">' + (image ? '<img src="' + safeText(image) + '" alt="">' : '<span class="partnerInitial">' + safeText(String(item.name || 'P').charAt(0)) + '</span>') + '</div>'
          + '<div class="partnerBody"><span class="partnerPlatform">' + safeText(platform) + '</span><b class="partnerName">' + safeText(item.name || 'Parceiro Fio do Bigode') + '</b><div class="partnerText">' + safeText(text) + '</div><div class="partnerLink">Ver publicação/perfil →</div></div></a>';
      }).join('');
    } catch (_) {
      section.classList.add('hidden');
    }
  }

  function decorateRating() {
    var box = document.getElementById('dealClosingRating');
    if (!box || box.__fdbBilateral) return;
    box.__fdbBilateral = true;
    var title = box.querySelector('h3');
    if (title) title.textContent = 'Avaliação entre comprador e vendedor';
  }

  var observer = new MutationObserver(function () {
    patchQualification();
    configureSignatureStep();
    arrangeHome();
    decorateRating();
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  patchQualification();
  configureSignatureStep();
  arrangeHome();
  decorateRating();
  return true;
})();
true;
`;

export default function App() {
  const webView = useRef<WebView>(null);
  const [canGoBack, setCanGoBack] = useState(false);
  const [failed, setFailed] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  const onNavigationStateChange = useCallback((navigation: WebViewNavigation) => {
    setCanGoBack(navigation.canGoBack);
  }, []);

  const onMessage = useCallback(async (event: WebViewMessageEvent) => {
    try {
      const message = JSON.parse(event.nativeEvent.data);
      if (message.type !== 'download' || !message.dataUrl) return;

      const match = String(message.dataUrl).match(/^data:([^;]+);base64,(.+)$/s);
      if (!match) throw new Error('Arquivo recebido em formato inválido.');

      const safeName = String(message.fileName || 'documento').replace(/[^a-zA-Z0-9._-]/g, '-');
      const uri = FileSystem.cacheDirectory + safeName;

      await FileSystem.writeAsStringAsync(uri, match[2], {
        encoding: FileSystem.EncodingType.Base64,
      });

      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, {
          mimeType: match[1],
          dialogTitle: 'Salvar ou compartilhar arquivo',
        });
      }
    } catch {
      webView.current?.injectJavaScript(
        "const s=document.getElementById('dealActionStatus');if(s)s.textContent='Não foi possível preparar o arquivo no aparelho.';true;",
      );
    }
  }, []);

  React.useEffect(() => {
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (!canGoBack) return false;
      webView.current?.goBack();
      return true;
    });

    return () => subscription.remove();
  }, [canGoBack]);

  const retry = useCallback(() => {
    setFailed(false);
    setReloadKey((value) => value + 1);
  }, []);

  if (failed) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="light" />
        <View style={styles.errorContainer}>
          <Text style={styles.mustache}>〰</Text>
          <Text style={styles.errorTitle}>Não foi possível abrir o Fio do Bigode</Text>
          <Text style={styles.errorText}>
            Verifique sua conexão com a internet e tente novamente.
          </Text>
          <TouchableOpacity accessibilityRole="button" style={styles.retryButton} onPress={retry}>
            <Text style={styles.retryText}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="dark" />
      <WebView
        key={reloadKey}
        ref={webView}
        source={{ uri: APP_URL }}
        originWhitelist={['https://*']}
        onNavigationStateChange={onNavigationStateChange}
        onMessage={onMessage}
        onError={() => setFailed(true)}
        onHttpError={({ nativeEvent }) => {
          if (nativeEvent.statusCode >= 500) setFailed(true);
        }}
        injectedJavaScript={HOMOLOGATION_UX_FIXES}
        startInLoadingState
        renderLoading={() => (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#D99A00" />
            <Text style={styles.loadingText}>Abrindo o Fio do Bigode…</Text>
          </View>
        )}
        javaScriptEnabled
        domStorageEnabled
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        allowsBackForwardNavigationGestures
        mediaPlaybackRequiresUserAction
        setSupportMultipleWindows={false}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  loadingContainer: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
    backgroundColor: '#FFFFFF',
  },
  loadingText: {
    color: '#52525B',
    fontSize: 16,
  },
  errorContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    backgroundColor: '#111111',
  },
  mustache: {
    marginBottom: 18,
    color: '#D99A00',
    fontSize: 72,
    fontWeight: '900',
  },
  errorTitle: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: '800',
    textAlign: 'center',
  },
  errorText: {
    marginTop: 12,
    color: '#D4D4D8',
    fontSize: 16,
    lineHeight: 23,
    textAlign: 'center',
  },
  retryButton: {
    marginTop: 28,
    paddingHorizontal: 30,
    paddingVertical: 16,
    borderRadius: 14,
    backgroundColor: '#D99A00',
  },
  retryText: {
    color: '#111111',
    fontSize: 17,
    fontWeight: '800',
  },
});
