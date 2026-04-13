/// API root including `/api/v1`, e.g. `https://api.example.com/api/v1`.
/// Build: `flutter run --dart-define=API_BASE_URL=https://host/api/v1`
class AppEnv {
  AppEnv._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1/api/v1',
  );
}
