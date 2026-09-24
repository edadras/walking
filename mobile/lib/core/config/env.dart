/// Build-time configuration via --dart-define.
///
///   flutter run --dart-define=API_BASE_URL=https://api.gamyar.ir/api/v1
abstract final class Env {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

  /// Google Cloud project number used for Play Integrity standard requests (0 = disabled).
  static const integrityCloudProjectNumber = int.fromEnvironment('INTEGRITY_PROJECT_NUMBER');

  static const platform = 'android';
}
