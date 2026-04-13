import 'package:dio/dio.dart';

import '../config/app_env.dart';
import '../../session/session_controller.dart';

Dio createAppDio(SessionController session) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppEnv.apiBaseUrl,
      connectTimeout: const Duration(seconds: 18),
      receiveTimeout: const Duration(seconds: 45),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final t = session.token;
        if (t != null && t.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $t';
        }
        return handler.next(options);
      },
      onError: (e, handler) async {
        if (e.response?.statusCode == 401) {
          await session.clearLocal();
        }
        return handler.next(e);
      },
    ),
  );

  return dio;
}
