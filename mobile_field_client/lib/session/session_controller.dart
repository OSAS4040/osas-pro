import 'package:flutter/foundation.dart';

import '../storage/secure_token_store.dart';
import 'bootstrap_payload.dart';

/// Holds authenticated session; server bootstrap drives module visibility (UX only).
class SessionController extends ChangeNotifier {
  SessionController({SecureTokenStore? store}) : _store = store ?? SecureTokenStore();

  final SecureTokenStore _store;

  BootstrapPayload? _payload;
  bool _restored = false;

  BootstrapPayload? get payload => _payload;
  bool get isAuthenticated => _payload != null;
  bool get hasRestored => _restored;

  List<String> get enabledModules => List.unmodifiable(_payload?.enabledModules ?? const []);
  String get homeScreen => _payload?.homeScreen ?? 'dashboard';
  String? get token => _payload?.token;

  bool canOpenModule(String moduleId) => enabledModules.contains(moduleId);

  Future<void> restore() async {
    final t = await _store.readToken();
    final cached = await _store.readBootstrapJson();
    if (t != null && t.isNotEmpty && cached != null) {
      cached['token'] = t;
      _payload = BootstrapPayload.tryParse(cached);
    } else {
      _payload = null;
    }
    _restored = true;
    notifyListeners();
  }

  Future<void> applyLoginResponse(Map<String, dynamic> json) async {
    final p = BootstrapPayload.tryParse(json);
    if (p == null) {
      throw StateError('Invalid login payload');
    }
    await _store.writeToken(p.token);
    await _store.writeBootstrapJson(p.toStorageJson());
    _payload = p;
    notifyListeners();
  }

  Future<void> clearLocal() async {
    await _store.clearAll();
    _payload = null;
    notifyListeners();
  }
}
