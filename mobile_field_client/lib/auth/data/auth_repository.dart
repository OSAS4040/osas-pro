import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../core/networking/app_dio.dart';
import '../../session/session_controller.dart';

class AuthRepository {
  AuthRepository(this._session) : _dio = createAppDio(_session);

  final SessionController _session;
  final Dio _dio;

  String _deviceType() {
    if (kIsWeb) return 'unknown';
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android';
      case TargetPlatform.iOS:
        return 'ios';
      default:
        return 'unknown';
    }
  }

  /// Unified login: email or phone in [identifier]. Server normalizes phone.
  Future<Map<String, dynamic>> login({
    required String identifier,
    required String password,
    String? fcmToken,
  }) async {
    final trimmed = identifier.trim();
    final res = await _dio.post<Map<String, dynamic>>(
      '/auth/login',
      data: {
        'identifier': trimmed,
        'password': password,
        'device_name': 'Flutter field client',
        'device_type': _deviceType(),
        if (fcmToken != null && fcmToken.isNotEmpty) 'fcm_token': fcmToken,
      },
    );
    final data = res.data;
    if (data == null) {
      throw DioException(requestOptions: res.requestOptions, message: 'Empty body');
    }
    return data;
  }

  Future<void> logout({String? fcmToken}) async {
    try {
      await _dio.post<void>(
        '/auth/logout',
        data: {
          if (fcmToken != null && fcmToken.isNotEmpty) 'fcm_token': fcmToken,
        },
      );
    } catch (_) {}
    await _session.clearLocal();
  }

  Future<void> logoutAllDevices() async {
    try {
      await _dio.post<void>('/auth/logout-all');
    } catch (_) {}
    await _session.clearLocal();
  }

  /// Call after Firebase gives a new token (or on app start). Requires active session.
  Future<void> registerPushDevice({required String fcmToken}) async {
    await _dio.post<void>(
      '/auth/push-device',
      data: {
        'fcm_token': fcmToken.trim(),
        'device_name': 'Flutter field client',
        'device_type': _deviceType(),
      },
    );
  }
}
