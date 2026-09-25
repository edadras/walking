import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';

/// Light / dark / follow the system. Stored on the device only.
class ThemeModeController extends Notifier<ThemeMode> {
  static const _key = 'theme_mode';

  @override
  ThemeMode build() {
    unawaited(_load());
    return ThemeMode.system;
  }

  Future<void> _load() async {
    final saved = await ref.read(secureStoreProvider).read(_key);
    final mode = ThemeMode.values.where((m) => m.name == saved).firstOrNull;
    if (mode != null && ref.mounted) state = mode;
  }

  Future<void> set(ThemeMode mode) async {
    state = mode;
    await ref.read(secureStoreProvider).write(_key, mode.name);
  }
}

final themeModeProvider = NotifierProvider<ThemeModeController, ThemeMode>(ThemeModeController.new);
