import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens a URL outside the app (bank pages must run in the real browser, never a WebView).
typedef ExternalUrlOpener = Future<bool> Function(String url);

final externalUrlOpenerProvider = Provider<ExternalUrlOpener>(
  (ref) => (url) => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
);
