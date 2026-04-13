import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const _kToken = 'auth_token_plain';
const _kBootstrap = 'auth_bootstrap_json';

/// Stores Sanctum token and last login bootstrap JSON (server source of truth snapshot).
class SecureTokenStore {
  SecureTokenStore({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  final FlutterSecureStorage _storage;

  Future<void> writeToken(String token) => _storage.write(key: _kToken, value: token);

  Future<String?> readToken() => _storage.read(key: _kToken);

  Future<void> clearToken() => _storage.delete(key: _kToken);

  Future<void> writeBootstrapJson(Map<String, dynamic> json) =>
      _storage.write(key: _kBootstrap, value: jsonEncode(json));

  Future<Map<String, dynamic>?> readBootstrapJson() async {
    final raw = await _storage.read(key: _kBootstrap);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return null;
  }

  Future<void> clearBootstrap() => _storage.delete(key: _kBootstrap);

  Future<void> clearAll() async {
    await clearToken();
    await clearBootstrap();
  }
}
