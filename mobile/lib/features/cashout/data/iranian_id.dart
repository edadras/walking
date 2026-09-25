import '../../../core/format/numbers.dart';

/// Client-side checks mirroring the server's, for instant feedback only.
abstract final class IranianId {
  static String _digits(String v) => Fa.toLatin(v).replaceAll(RegExp(r'\D'), '');

  /// The 10-digit code when its check digit is right, otherwise null.
  static String? nationalCode(String value) {
    final c = _digits(value);
    if (c.length != 10 || RegExp(r'^(\d)\1{9}$').hasMatch(c)) return null;
    var sum = 0;
    for (var i = 0; i < 9; i++) {
      sum += int.parse(c[i]) * (10 - i);
    }
    final r = sum % 11;
    final check = int.parse(c[9]);
    return (r < 2 ? check == r : check == 11 - r) ? c : null;
  }

  /// "IR" + 24 digits when the ISO 13616 mod-97 check passes, otherwise null.
  static String? sheba(String value) {
    var v = Fa.toLatin(value).toUpperCase().replaceAll(RegExp(r'[\s-]'), '');
    if (RegExp(r'^\d{24}$').hasMatch(v)) v = 'IR$v';
    if (!RegExp(r'^IR\d{24}$').hasMatch(v)) return null;
    final numeric = '${v.substring(4)}1827${v.substring(2, 4)}';
    var mod = 0;
    for (var i = 0; i < numeric.length; i += 7) {
      final end = i + 7 > numeric.length ? numeric.length : i + 7;
      mod = int.parse('$mod${numeric.substring(i, end)}') % 97;
    }
    return mod == 1 ? v : null;
  }

  /// Persian letters, spaces and ZWNJ only (names must match the national card).
  static bool persianName(String value) => RegExp(r'^[؀-ۿ‌\s]{2,}$').hasMatch(value.trim());
}
