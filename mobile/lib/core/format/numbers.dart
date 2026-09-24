/// Persian number formatting. All user-visible numbers go through here so
/// digits are always Persian and thousands use the Arabic separator (٬).
abstract final class Fa {
  static const _latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
  static const _persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  static const _arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

  static String digits(Object value) {
    var s = value.toString();
    for (var i = 0; i < 10; i++) {
      s = s.replaceAll(_latin[i], _persian[i]);
    }
    return s;
  }

  /// Converts Persian/Arabic digits typed by the user to Latin for parsing/APIs.
  static String toLatin(String value) {
    var s = value;
    for (var i = 0; i < 10; i++) {
      s = s.replaceAll(_persian[i], _latin[i]).replaceAll(_arabic[i], _latin[i]);
    }
    return s;
  }

  /// 12840 → ۱۲٬۸۴۰
  static String number(num value, {int decimals = 0}) {
    final negative = value < 0;
    final fixed = value.abs().toStringAsFixed(decimals);
    final parts = fixed.split('.');
    final whole = parts[0];
    final buffer = StringBuffer();
    for (var i = 0; i < whole.length; i++) {
      if (i > 0 && (whole.length - i) % 3 == 0) buffer.write('٬');
      buffer.write(whole[i]);
    }
    var out = buffer.toString();
    if (parts.length > 1 && int.parse(parts[1]) != 0) out = '$out٫${parts[1]}';
    return digits(negative ? '−$out' : out);
  }

  /// 4.73 → ۴٫۷ (one decimal, trailing zero dropped)
  static String decimal(num value, {int decimals = 1}) => number(value, decimals: decimals);

  static String percent(num value) => '${digits(value.round())}٪';

  static String rial(num value) => '${number(value)} ریال';

  /// Compact form for big money values: 1,250,000 → ۱٫۲۵ میلیون
  static String compactRial(num value) {
    if (value.abs() >= 1000000000) return '${decimal(value / 1000000000, decimals: 2)} میلیارد ریال';
    if (value.abs() >= 1000000) return '${decimal(value / 1000000, decimals: 2)} میلیون ریال';
    return rial(value);
  }

  static String signedPoints(num value) => value > 0 ? '+${number(value)}' : number(value);
}
