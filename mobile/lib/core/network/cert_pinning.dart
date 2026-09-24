import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';

/// Public-key pinning: the server certificate's SubjectPublicKeyInfo must hash
/// (SHA-256, base64) to one of the shipped pins. Pinning the key rather than
/// the certificate survives renewals that keep the key; ship at least one
/// backup pin (the next key) so a rotation never locks users out.
abstract final class CertPinning {
  /// Returns [dio] with pinning installed. No pins = no pinning (local development).
  static Dio pinned(Dio dio, List<String> pins) {
    if (pins.isEmpty) return dio;
    final allowed = pins.map((p) => p.trim()).where((p) => p.isNotEmpty).toSet();
    dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () => HttpClient()..badCertificateCallback = (_, _, _) => false,
      validateCertificate: (cert, host, port) {
        if (cert == null) return false;
        try {
          return allowed.contains(spkiSha256(cert.der));
        } on FormatException {
          return false; // fail closed
        }
      },
    );
    return dio;
  }

  /// base64(SHA-256(SubjectPublicKeyInfo DER)) of an X.509 certificate — the
  /// same value as `openssl x509 -pubkey | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64`.
  static String spkiSha256(Uint8List certDer) => base64Encode(sha256.convert(subjectPublicKeyInfo(certDer)).bytes);

  /// Certificate ::= SEQUENCE { tbsCertificate SEQUENCE { [0] version OPTIONAL,
  /// serialNumber, signature, issuer, validity, subject, subjectPublicKeyInfo, … } … }
  static Uint8List subjectPublicKeyInfo(Uint8List der) {
    final cert = _Tlv.read(der, 0);
    final tbs = _Tlv.read(der, cert.contentStart);
    var offset = tbs.contentStart;
    var element = _Tlv.read(der, offset);
    if (element.tag == 0xA0) {
      offset = element.end; // explicit version
      element = _Tlv.read(der, offset);
    }
    // serialNumber, signature, issuer, validity, subject
    for (var i = 0; i < 5; i++) {
      offset = element.end;
      element = _Tlv.read(der, offset);
    }
    if (element.tag != 0x30) throw const FormatException('SubjectPublicKeyInfo not found');
    return Uint8List.sublistView(der, element.start, element.end);
  }
}

class _Tlv {
  const _Tlv(this.tag, this.start, this.contentStart, this.end);

  final int tag;
  final int start;
  final int contentStart;
  final int end;

  static _Tlv read(Uint8List b, int start) {
    int at(int i) => i < b.length ? b[i] : throw const FormatException('Truncated DER');
    final tag = at(start);
    var i = start + 1;
    var length = at(i++);
    if (length & 0x80 != 0) {
      final n = length & 0x7F;
      if (n == 0 || n > 4) throw const FormatException('Unsupported DER length');
      length = 0;
      for (var k = 0; k < n; k++) {
        length = (length << 8) | at(i++);
      }
    }
    if (i + length > b.length) throw const FormatException('Truncated DER');
    return _Tlv(tag, start, i, i + length);
  }
}
