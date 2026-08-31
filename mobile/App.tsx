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

const APP_URL = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html';
const FINAL_ADJUSTMENTS_URL = 'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/final-adjustments.js?v=20260831-home-final';

const LOAD_FINAL_ADJUSTMENTS = `
(function(){
  if (document.getElementById('fdbFinalAdjustmentsMobile')) return true;
  var script=document.createElement('script');
  script.id='fdbFinalAdjustmentsMobile';
  script.src='${FINAL_ADJUSTMENTS_URL}';
  (document.body||document.documentElement).appendChild(script);
})();
true;
`;

export default function App() {
  const webView = useRef<WebView>(null);
  const [canGoBack, setCanGoBack] = useState(false);
  const [failed, setFailed] = useState(false);

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
      if (await Sharing.isAvailableAsync()) await Sharing.shareAsync(uri, { mimeType: match[1], dialogTitle: 'Salvar ou compartilhar arquivo' });
    } catch {
      webView.current?.injectJavaScript("document.getElementById('dealActionStatus').textContent='Não foi possível preparar o arquivo no aparelho.';true;");
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
          <TouchableOpacity
            accessibilityRole="button"
            style={styles.retryButton}
            onPress={() => setFailed(false)}
          >
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
        ref={webView}
        source={{ uri: APP_URL }}
        originWhitelist={['https://*']}
        onNavigationStateChange={onNavigationStateChange}
        onMessage={onMessage}
        onError={() => setFailed(true)}
        onHttpError={({ nativeEvent }) => {
          if (nativeEvent.statusCode >= 500) setFailed(true);
        }}
        onLoadEnd={() => webView.current?.injectJavaScript(LOAD_FINAL_ADJUSTMENTS)}
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
