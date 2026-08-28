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
    '.fdbSignatureLocked{display:none!important}'
  ].join('');
  document.head.appendChild(style);

  document.title = 'Fio do Bigode • Homologação v0.6.1';
  var version = document.querySelector('.version');
  if (version) version.textContent = 'Homologação v0.6.1 • jornada E2E validada';

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

    var label = canvas.previousElementSibling;
    var clearButton = canvas.nextElementSibling;
    var consent = clearButton && clearButton.nextElementSibling;
    var confirmButton = consent && consent.nextElementSibling;
    var gated = [label, canvas, clearButton, consent, confirmButton].filter(Boolean);

    function updateGate() {
      var ready = String(code.value || '').replace(/\\D/g, '').length === 6;
      gated.forEach(function (element) {
        element.classList.toggle('fdbSignatureLocked', !ready);
      });
      if (ready && canvas.width <= 300 && typeof window.initElectronicSignatureCanvas === 'function') {
        setTimeout(function () { window.initElectronicSignatureCanvas(); }, 0);
      }
    }

    code.addEventListener('input', updateGate);
    updateGate();
    code.focus();
  }

  var observer = new MutationObserver(function () {
    patchQualification();
    configureSignatureStep();
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  patchQualification();
  configureSignatureStep();
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
