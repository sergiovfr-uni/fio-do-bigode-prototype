import { StatusBar } from 'expo-status-bar';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
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

const APP_URL = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html';
const LIVE_REFRESH_MS = 30000;
const RUNTIME_VERSION = '20260901-mobile-final1';

const LOAD_RUNTIME_SCRIPTS = `
(function(){
  try {
    function load(id, src){
      if(document.getElementById(id)) return;
      var script=document.createElement('script');
      script.id=id;
      script.src=src;
      document.body.appendChild(script);
    }
    load('fdbFinalAdjustmentsScript','https://sergiovfr-uni.github.io/fio-do-bigode-prototype/final-adjustments.js?v=${RUNTIME_VERSION}');
    load('fdbRuntimeFixesScript','https://sergiovfr-uni.github.io/fio-do-bigode-prototype/runtime-fixes.js?v=${RUNTIME_VERSION}');
  } catch (_) {}
  true;
})();
`;

const REFRESH_DYNAMIC_DATA = `
(function(){
  try {
    var calls = ['loadCampaigns','loadListings','loadDeals','loadNotifications'];
    calls.forEach(function(name){
      try {
        if (typeof window[name] === 'function') {
          var result = window[name]();
          if (result && typeof result.catch === 'function') result.catch(function(){});
        }
      } catch (_) {}
    });
  } catch (_) {}
  true;
})();
`;

export default function App() {
  const webView = useRef<WebView>(null);
  const appState = useRef(AppState.currentState);
  const [canGoBack, setCanGoBack] = useState(false);
  const [failed, setFailed] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  const refreshDynamicData = useCallback(() => {
    webView.current?.injectJavaScript(REFRESH_DYNAMIC_DATA);
  }, []);

  const loadRuntimeScripts = useCallback(() => {
    webView.current?.injectJavaScript(LOAD_RUNTIME_SCRIPTS);
    setTimeout(refreshDynamicData, 700);
  }, [refreshDynamicData]);

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
      await FileSystem.writeAsStringAsync(uri, match[2], { encoding: FileSystem.EncodingType.Base64 });
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: match[1], dialogTitle: 'Salvar ou compartilhar arquivo' });
      }
    } catch {
      webView.current?.injectJavaScript("const s=document.getElementById('dealActionStatus');if(s)s.textContent='Não foi possível preparar o arquivo no aparelho.';true;");
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

  React.useEffect(() => {
    const subscription = AppState.addEventListener('change', nextState => {
      const wasBackground = /inactive|background/.test(appState.current);
      appState.current = nextState;
      if (wasBackground && nextState === 'active') {
        webView.current?.reload();
      }
    });
    return () => subscription.remove();
  }, []);

  React.useEffect(() => {
    const timer = setInterval(() => {
      if (appState.current === 'active' && !failed) refreshDynamicData();
    }, LIVE_REFRESH_MS);
    return () => clearInterval(timer);
  }, [failed, refreshDynamicData]);

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
          <Text style={styles.errorText}>Verifique sua conexão com a internet e tente novamente.</Text>
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
        onLoadEnd={loadRuntimeScripts}
        onError={() => setFailed(true)}
        onHttpError={({ nativeEvent }) => {
          if (nativeEvent.statusCode >= 500) setFailed(true);
        }}
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
        cacheEnabled={false}
        allowsBackForwardNavigationGestures
        mediaPlaybackRequiresUserAction
        setSupportMultipleWindows={false}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#FFFFFF' },
  loadingContainer: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', gap: 16, backgroundColor: '#FFFFFF' },
  loadingText: { color: '#52525B', fontSize: 16 },
  errorContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, backgroundColor: '#111111' },
  mustache: { marginBottom: 18, color: '#D99A00', fontSize: 72, fontWeight: '900' },
  errorTitle: { color: '#FFFFFF', fontSize: 24, fontWeight: '800', textAlign: 'center' },
  errorText: { marginTop: 12, color: '#D4D4D8', fontSize: 16, lineHeight: 23, textAlign: 'center' },
  retryButton: { marginTop: 28, paddingHorizontal: 30, paddingVertical: 16, borderRadius: 14, backgroundColor: '#D99A00' },
  retryText: { color: '#111111', fontSize: 17, fontWeight: '800' },
});
