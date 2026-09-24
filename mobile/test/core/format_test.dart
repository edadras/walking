import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/format/numbers.dart';
import 'package:gamyar/features/auth/presentation/phone_page.dart';

void main() {
  group('Fa', () {
    test('formats with Persian digits and thousands separator', () {
      expect(Fa.number(6840), '۶٬۸۴۰');
      expect(Fa.number(1234567), '۱٬۲۳۴٬۵۶۷');
      expect(Fa.number(0), '۰');
      expect(Fa.number(-250), '−۲۵۰');
    });

    test('decimals drop trailing zero and use the Persian decimal mark', () {
      expect(Fa.decimal(4.73), '۴٫۷');
      expect(Fa.decimal(4.0), '۴');
    });

    test('rial and percent', () {
      expect(Fa.rial(34000), '۳۴٬۰۰۰ ریال');
      expect(Fa.percent(68.4), '۶۸٪');
    });

    test('toLatin normalises Persian and Arabic digits', () {
      expect(Fa.toLatin('۰۹۱۲٣٤٥'), '0912345');
    });
  });

  group('normalizeIranMobile', () {
    test('accepts common forms', () {
      for (final input in ['09121234567', '9121234567', '+989121234567', '00989121234567', '989121234567']) {
        expect(normalizeIranMobile(input), '09121234567', reason: input);
      }
    });

    test('rejects landlines and garbage', () {
      expect(normalizeIranMobile('02112345678'), isNull);
      expect(normalizeIranMobile('0912'), isNull);
      expect(normalizeIranMobile(''), isNull);
    });
  });
}
