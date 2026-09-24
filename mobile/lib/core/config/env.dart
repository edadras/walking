/// Build-time configuration via --dart-define.
///
///   flutter run --dart-define=API_BASE_URL=https://api.gamyar.ir/api/v1
abstract final class Env {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

  /// Google Cloud project number used for Play Integrity standard requests (0 = disabled).
  static const integrityCloudProjectNumber = int.fromEnvironment('INTEGRITY_PROJECT_NUMBER');

  /// Raster tiles for the nearby-rewards map (swap for a local provider in production).
  static const mapTileUrl = String.fromEnvironment('MAP_TILE_URL', defaultValue: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');

  /// Comma-separated base64 SHA-256 SPKI pins of the API host (current + backup).
  /// Empty = no pinning (local development only; release builds must set it).
  static const certPins = String.fromEnvironment('CERT_PINS');

  static List<String> get certPinList => certPins.isEmpty ? const [] : certPins.split(',');

  static const platform = 'android';
}
