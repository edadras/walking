import 'dart:convert';

import 'package:flutter/services.dart';

/// Hardware-backed device key living in the Android Keystore
/// (android/.../DeviceKeyChannel.kt). The private key never enters Dart.
abstract class DeviceKey {
  /// Creates the EC P-256 key on first use and returns the base64 DER
  /// SubjectPublicKeyInfo.
  Future<String> publicKey();

  /// Returns a base64 DER ECDSA-SHA256 signature of [data].
  Future<String> sign(Uint8List data);

  /// Local, low-weight integrity hints (emulator / root). Verified server-side with Play Integrity.
  Future<DeviceSignals> signals();

  /// Play Integrity standard token bound to [requestHash], or null if unavailable.
  Future<String?> integrityToken(String requestHash, int cloudProjectNumber);

  // Rotation (see DeviceIdentity.rotateKey): a pending key is created and used
  // for the proof; the active key is replaced only after the server accepted it.

  /// Creates (or reuses) the pending key and returns its base64 DER public key.
  Future<String> pendingPublicKey();

  /// Signs with the pending key.
  Future<String> signPending(Uint8List data);

  /// Makes the pending key active and deletes the old one.
  Future<void> commitPending();

  /// Drops an unused pending key.
  Future<void> discardPending();

  /// When the active key was created (for age-based rotation).
  Future<DateTime?> keyCreatedAt();
}

class DeviceSignals {
  const DeviceSignals({required this.emulator, required this.rooted, required this.hardwareBacked});

  final bool emulator;
  final bool rooted;
  final bool hardwareBacked;
}

class KeystoreDeviceKey implements DeviceKey {
  static const _channel = MethodChannel('ir.gamyar/device_key');

  @override
  Future<String> publicKey() async => (await _channel.invokeMethod<String>('publicKey'))!;

  @override
  Future<String> sign(Uint8List data) async => (await _channel.invokeMethod<String>('sign', {'data': data}))!;

  @override
  Future<DeviceSignals> signals() async {
    final m = await _channel.invokeMapMethod<String, Object?>('signals') ?? const {};
    return DeviceSignals(
      emulator: m['emulator'] == true,
      rooted: m['rooted'] == true,
      hardwareBacked: m['hardwareBacked'] == true,
    );
  }

  @override
  Future<String> pendingPublicKey() async => (await _channel.invokeMethod<String>('pendingPublicKey'))!;

  @override
  Future<String> signPending(Uint8List data) async => (await _channel.invokeMethod<String>('signPending', {'data': data}))!;

  @override
  Future<void> commitPending() => _channel.invokeMethod<bool>('commitPending');

  @override
  Future<void> discardPending() => _channel.invokeMethod<bool>('discardPending');

  @override
  Future<DateTime?> keyCreatedAt() async {
    final ms = await _channel.invokeMethod<int>('keyCreatedAt');
    return ms == null || ms == 0 ? null : DateTime.fromMillisecondsSinceEpoch(ms);
  }

  @override
  Future<String?> integrityToken(String requestHash, int cloudProjectNumber) async {
    if (cloudProjectNumber == 0) return null;
    try {
      return await _channel.invokeMethod<String>('integrityToken', {
        'requestHash': requestHash,
        'cloudProjectNumber': cloudProjectNumber,
      });
    } on PlatformException {
      // No Play Services / network: the server treats it as "unavailable", not as fraud.
      return null;
    }
  }
}

/// Canonical string (must match App\Domain\Device\RequestSignature on the server):
///   METHOD \n PATH \n TIMESTAMP \n NONCE \n hex(sha256(body))
String canonicalRequest(String method, String path, String timestamp, String nonce, String bodySha256Hex) =>
    [method.toUpperCase(), path, timestamp, nonce, bodySha256Hex].join('\n');

Uint8List utf8Bytes(String s) => Uint8List.fromList(utf8.encode(s));
