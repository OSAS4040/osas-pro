import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../../auth/data/auth_repository.dart';
import '../../session/session_controller.dart';
import 'firebase_bootstrap.dart';

/// Registers FCM token with Laravel whenever the session is authenticated.
/// Listens to [FirebaseMessaging.onTokenRefresh] so rotated tokens stay synced.
class PushMessagingBinding {
  PushMessagingBinding(this._session, this._authRepo);

  final SessionController _session;
  final AuthRepository _authRepo;

  StreamSubscription<String>? _tokenRefreshSub;
  String? _lastRegisteredToken;
  /// Last token successfully sent to API (avoids duplicate POST for same FCM string).
  String? _lastPushedToServer;
  bool _everSyncedToken = false;

  String? get lastRegisteredToken => _lastRegisteredToken;

  void attach() {
    _session.addListener(_onSessionChanged);
    _onSessionChanged();
  }

  void detach() {
    _session.removeListener(_onSessionChanged);
    unawaited(_tearDownMessagingListeners());
  }

  void _onSessionChanged() {
    if (_session.isAuthenticated) {
      unawaited(_ensureRegistered());
    } else {
      unawaited(_tearDownMessagingListeners());
    }
  }

  Future<void> _ensureRegistered() async {
    final ok = await ensureFirebaseInitialized();
    if (!ok) return;

    final messaging = FirebaseMessaging.instance;
    if (!kIsWeb) {
      await messaging.setAutoInitEnabled(true);
    }
    if (defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS) {
      await messaging.requestPermission(alert: true, badge: true, sound: true);
    }

    final token = await messaging.getToken();
    if (token != null && token.isNotEmpty) {
      await _pushTokenToBackend(token);
    }

    await _tokenRefreshSub?.cancel();
    _tokenRefreshSub = FirebaseMessaging.instance.onTokenRefresh.listen((t) {
      unawaited(_pushTokenToBackend(t));
    });
  }

  Future<void> _pushTokenToBackend(String token) async {
    if (_lastPushedToServer == token) {
      _lastRegisteredToken = token;
      return;
    }
    try {
      await _authRepo.registerPushDevice(fcmToken: token);
      _lastPushedToServer = token;
      _lastRegisteredToken = token;
      _everSyncedToken = true;
    } catch (e, st) {
      if (kDebugMode) {
        debugPrint('registerPushDevice failed: $e\n$st');
      }
    }
  }

  Future<void> _tearDownMessagingListeners() async {
    await _tokenRefreshSub?.cancel();
    _tokenRefreshSub = null;
    _lastRegisteredToken = null;
    _lastPushedToServer = null;

    if (_everSyncedToken) {
      _everSyncedToken = false;
      final ok = await ensureFirebaseInitialized();
      if (ok) {
        try {
          await FirebaseMessaging.instance.deleteToken();
        } catch (_) {}
      }
    }
  }
}
