import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../storage/secure_store.dart';
import '../providers.dart';

/// What the gate needs; each one is required before the app can be used.
enum RequiredPermission { activity, notifications, battery }

enum GateStatus { granted, denied, permanentlyDenied }

/// Vendor skin with its own autostart manager ("xiaomi", "huawei", …), if any.
typedef OemInfo = ({String manufacturer, String? family});

/// Platform access behind an interface so the gate is testable.
abstract class DevicePermissions {
  Future<GateStatus> status(RequiredPermission p);
  Future<GateStatus> request(RequiredPermission p);
  Future<void> openSettings();
  Future<OemInfo> oem();

  /// Opens the vendor autostart screen; false when only app settings could be opened.
  Future<bool> openAutostart();
}

class PlatformDevicePermissions implements DevicePermissions {
  static const _oem = MethodChannel('ir.gamyar.app/oem');

  Permission _handler(RequiredPermission p) => switch (p) {
        RequiredPermission.activity => Permission.activityRecognition,
        RequiredPermission.notifications => Permission.notification,
        RequiredPermission.battery => Permission.ignoreBatteryOptimizations,
      };

  GateStatus _map(PermissionStatus s) => s.isGranted || s.isLimited
      ? GateStatus.granted
      : s.isPermanentlyDenied
          ? GateStatus.permanentlyDenied
          : GateStatus.denied;

  @override
  Future<GateStatus> status(RequiredPermission p) async => _map(await _handler(p).status);

  @override
  Future<GateStatus> request(RequiredPermission p) async => _map(await _handler(p).request());

  @override
  Future<void> openSettings() => openAppSettings();

  @override
  Future<OemInfo> oem() async {
    try {
      final info = await _oem.invokeMapMethod<String, Object?>('info');
      return (manufacturer: '${info?['manufacturer'] ?? ''}', family: info?['family'] as String?);
    } on MissingPluginException {
      return (manufacturer: '', family: null);
    }
  }

  @override
  Future<bool> openAutostart() async {
    try {
      return await _oem.invokeMethod<bool>('openAutostart') ?? false;
    } on MissingPluginException {
      await openAppSettings();
      return false;
    }
  }
}

final devicePermissionsProvider = Provider<DevicePermissions>((ref) => PlatformDevicePermissions());

class PermissionGate {
  const PermissionGate({required this.statuses, required this.oem, required this.autostartConfirmed});

  final Map<RequiredPermission, GateStatus> statuses;
  final OemInfo oem;

  /// The vendor autostart switch can't be read back, so the user confirms it once.
  final bool autostartConfirmed;

  bool get needsAutostart => oem.family != null && !autostartConfirmed;
  bool get satisfied => statuses.values.every((s) => s == GateStatus.granted) && !needsAutostart;
}

/// Tracks the required permissions and re-checks whenever the app returns to
/// the foreground, so revoking one in system settings brings the gate back.
class PermissionGateController extends AsyncNotifier<PermissionGate> {
  static const kAutostartConfirmed = 'autostart_confirmed';

  late final AppLifecycleListener _lifecycle;

  DevicePermissions get _device => ref.read(devicePermissionsProvider);
  SecureStore get _store => ref.read(secureStoreProvider);

  @override
  Future<PermissionGate> build() async {
    _lifecycle = AppLifecycleListener(onResume: () => unawaited(refresh()));
    ref.onDispose(_lifecycle.dispose);
    return _read();
  }

  Future<PermissionGate> _read() async => PermissionGate(
        statuses: {for (final p in RequiredPermission.values) p: await _device.status(p)},
        oem: await _device.oem(),
        autostartConfirmed: await _store.read(kAutostartConfirmed) == '1',
      );

  /// Refreshes without dropping to loading, so the router never flashes the splash.
  Future<void> refresh() async {
    final next = await AsyncValue.guard(_read);
    if (ref.mounted) state = next;
  }

  /// Asks for one permission; a permanent refusal can only be undone in settings.
  Future<void> request(RequiredPermission p) async {
    final current = state.value?.statuses[p];
    if (current == GateStatus.permanentlyDenied) {
      await _device.openSettings();
    } else {
      await _device.request(p);
    }
    await refresh();
  }

  Future<void> openAutostart() => _device.openAutostart();

  Future<void> confirmAutostart() async {
    await _store.write(kAutostartConfirmed, '1');
    await refresh();
  }
}

final permissionGateProvider = AsyncNotifierProvider<PermissionGateController, PermissionGate>(PermissionGateController.new);
