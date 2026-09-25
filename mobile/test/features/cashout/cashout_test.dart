import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/cashout/data/cashout.dart';
import 'package:gamyar/features/cashout/data/iranian_id.dart';
import 'package:gamyar/features/cashout/presentation/cashout_page.dart';
import 'package:gamyar/features/cashout/presentation/identity_page.dart';
import 'package:go_router/go_router.dart';

import '../../helpers/test_app.dart';

const _sheba = 'IR062960000000100324200001';

/// In-memory cash-out server: the SMS code is always 123456.
class _Api extends ApiClient {
  _Api() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');

  final calls = <String>[];
  final bodies = <String, Map>{};
  final signed = <String>[];
  Map<String, dynamic>? identity;
  final accounts = <Map<String, dynamic>>[];
  final requests = <Map<String, dynamic>>[];
  int available = 30000;

  List<Map<String, String>> get _blockers => [
        if (identity?['status'] != 'verified') {'code': 'identity_unverified', 'message': 'مشخصات هویتی را ثبت کن.'},
        if (!accounts.any((a) => a['status'] == 'verified')) {'code': 'bank_account_unverified', 'message': 'یک حساب بانکی تأییدشده لازم است.'},
        if (requests.any((r) => r['status'] == 'pending')) {'code': 'request_open', 'message': 'یک درخواست برداشت در حال بررسی داری.'},
      ];

  Map<String, dynamic> _overview() => {
        'data': {
          'enabled': true,
          'phone': '09121234567',
          'rial_per_point': 10,
          'available_points': available,
          'limits': {'min': 5000, 'max': 50000, 'window_max': 150000, 'window_used': 0, 'window_left': 150000},
          'blockers': _blockers,
          'identity': identity,
          'bank_accounts': accounts,
          'requests': requests,
        },
      };

  void _checkCode(Object? data) {
    if ((data! as Map)['code'] != '12345') {
      throw const ApiException(code: 'otp_invalid', message: 'کد وارد شده صحیح نیست.');
    }
  }

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async => switch (path) {
        '/cashout' => _overview(),
        _ => throw ApiException(code: 'not_found', message: path),
      };

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    calls.add(path);
    if (data is Map) bodies[path] = data;
    if (options?.extra?['signed'] == true) signed.add(path);
    switch (path) {
      case '/cashout/otp':
        return {'data': {'expires_in': 120, 'resend_in': 60}};
      case '/cashout/identity':
        _checkCode(data);
        final d = data! as Map;
        identity = {'first_name': d['first_name'], 'last_name': d['last_name'], 'national_code': '•••••••899', 'birth_date': d['birth_date'], 'status': 'pending', 'rejection_reason': null};
        return _overview();
      case '/cashout/bank-accounts':
        _checkCode(data);
        accounts.add({'id': '01HZZZZZZZZZZZZZZZZZZZZZZB', 'iban': 'IR•• •••• •••• •••• •••• 0001', 'bank_name': 'بانک نامشخص', 'holder_name': 'مریم احمدی', 'status': 'pending', 'rejection_reason': null});
        return _overview();
      case '/cashout/requests':
        _checkCode(data);
        final points = (data! as Map)['points'] as int;
        available -= points;
        final r = {'id': '01HZZZZZZZZZZZZZZZZZZZZZZR', 'points': points, 'amount_rial': points * 10, 'status': 'pending', 'status_label': 'در انتظار بررسی', 'bank': 'ملت', 'iban': '•••', 'bank_reference': null, 'rejection_reason': null, 'created_at': '2026-09-25T08:00:00Z', 'paid_at': null};
        requests.insert(0, r);
        return {'data': r};
      case '/cashout/requests/01HZZZZZZZZZZZZZZZZZZZZZZR/cancel':
        requests.first['status'] = 'cancelled';
        requests.first['status_label'] = 'لغو شد';
        available += requests.first['points'] as int;
        return {'data': requests.first};
    }
    return const {};
  }

  void verifyAll() {
    identity = {'first_name': 'مریم', 'last_name': 'احمدی', 'national_code': '•••••••899', 'birth_date': '1995-03-21', 'status': 'verified', 'rejection_reason': null};
    accounts
      ..clear()
      ..add({'id': '01HZZZZZZZZZZZZZZZZZZZZZZB', 'iban': 'IR•• •••• •••• •••• •••• 0001', 'bank_name': 'ملت', 'holder_name': 'مریم احمدی', 'status': 'verified', 'rejection_reason': null});
  }
}

void main() {
  group('IranianId', () {
    test('national code check digit', () {
      expect(IranianId.nationalCode('0499370899'), '0499370899');
      expect(IranianId.nationalCode('۰۴۹۹۳۷۰۸۹۹'), '0499370899');
      expect(IranianId.nationalCode('0499370898'), isNull);
      expect(IranianId.nationalCode('1111111111'), isNull);
    });

    test('sheba mod 97', () {
      expect(IranianId.sheba('IR06 2960 0000 0010 0324 2000 01'), _sheba);
      expect(IranianId.sheba('062960000000100324200001'), _sheba);
      expect(IranianId.sheba('IR072960000000100324200001'), isNull);
    });

    test('names must be Persian', () {
      expect(IranianId.persianName('مریم'), isTrue);
      expect(IranianId.persianName('Maryam'), isFalse);
    });
  });

  late _Api api;
  setUp(() => api = _Api());

  Future<void> pump(WidgetTester tester) async {
    tester.view
      ..physicalSize = const Size(1080, 3600)
      ..devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final router = GoRouter(initialLocation: '/cashout', routes: [
      GoRoute(path: '/cashout', builder: (_, _) => const CashoutPage()),
      GoRoute(path: '/cashout/identity', builder: (_, s) => CashoutIdentityPage(overview: s.extra! as CashoutOverview)),
    ]);
    await tester.pumpWidget(testRouterApp(router, overrides: [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();
  }

  Future<void> tap(WidgetTester tester, String text) async {
    await tester.ensureVisible(find.text(text).last);
    await tester.pumpAndSettle();
    await tester.tap(find.text(text).last);
    await tester.pumpAndSettle();
  }

  Future<void> enterSms(WidgetTester tester, String code) async {
    // A full-length code submits itself.
    await tester.enterText(find.byType(TextField).last, code);
    await tester.pumpAndSettle();
  }

  testWidgets('identity is submitted with an SMS code; wrong code stays in the sheet', (tester) async {
    await pump(tester);
    expect(find.text('مشخصات هویتی را ثبت کن.'), findsOneWidget, reason: 'server blockers are shown');
    expect(find.text('ثبت درخواست واریز'), findsNothing);

    await tap(tester, 'ثبت مشخصات');
    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), 'مریم');
    await tester.enterText(fields.at(1), 'احمدی');
    await tester.enterText(fields.at(2), '0499370898');
    await tap(tester, 'ثبت مشخصات');
    expect(find.text('کد ملی معتبر نیست.'), findsOneWidget);
    expect(find.text('این قسمت را کامل کن.'), findsOneWidget, reason: 'birth date missing');
    expect(api.calls, isEmpty);

    await tester.enterText(fields.at(2), '0499370899');
    for (final (label, pick) in [('روز', '۱'), ('ماه', 'فروردین'), ('سال', '۱۳۷۴')]) {
      await tester.tap(find.text(label));
      await tester.pumpAndSettle();
      await tester.tap(find.text(pick).last);
      await tester.pumpAndSettle();
    }
    await tap(tester, 'ثبت مشخصات');
    expect(api.calls, ['/cashout/otp']);
    expect(find.textContaining('۰۹۱۲۱۲۳۴۵۶۷'), findsOneWidget);

    await enterSms(tester, '11111');
    expect(find.text('کد وارد شده صحیح نیست.'), findsOneWidget);

    await enterSms(tester, '12345');
    expect(api.bodies['/cashout/identity'], {'first_name': 'مریم', 'last_name': 'احمدی', 'national_code': '0499370899', 'birth_date': '1995-03-21', 'code': '12345'});
    expect(api.signed, containsAll(['/cashout/otp', '/cashout/identity']));
    expect(find.text('مریم احمدی'), findsOneWidget);
    expect(find.text('در انتظار بررسی'), findsOneWidget);
  });

  testWidgets('a Sheba is validated before any SMS is sent', (tester) async {
    api.identity = {'first_name': 'مریم', 'last_name': 'احمدی', 'national_code': '•••••••899', 'birth_date': '1995-03-21', 'status': 'pending', 'rejection_reason': null};
    await pump(tester);
    await tap(tester, 'افزودن حساب');
    expect(find.textContaining('مریم احمدی'), findsWidgets);
    await tester.enterText(find.byType(TextField).last, '072960000000100324200001');
    await tester.tap(find.text('تأیید').last);
    await tester.pumpAndSettle();
    expect(find.text('شماره شبا معتبر نیست.'), findsOneWidget);
    expect(api.calls, isEmpty);

    await tester.enterText(find.byType(TextField).last, '062960000000100324200001');
    await tester.tap(find.text('تأیید').last);
    await tester.pumpAndSettle();
    await enterSms(tester, '12345');
    expect(api.bodies['/cashout/bank-accounts'], {'sheba': _sheba, 'code': '12345'});
    expect(find.text('بانک نامشخص'), findsOneWidget);
  });

  testWidgets('request respects the limits, debits and can be cancelled', (tester) async {
    api.verifyAll();
    await pump(tester);
    expect(find.text('هر درخواست بین ۵٬۰۰۰ تا ۵۰٬۰۰۰ امتیاز'), findsOneWidget);

    final amount = find.byType(TextField).first;
    await tester.enterText(amount, '4000');
    await tap(tester, 'ثبت درخواست واریز');
    expect(find.text('مقدار باید بین ۵٬۰۰۰ و ۳۰٬۰۰۰ امتیاز باشد.'), findsOneWidget, reason: 'capped by the balance');
    expect(api.calls, isEmpty);

    await tap(tester, 'حداکثر');
    expect(find.text('مبلغ واریزی: ۳۰۰٬۰۰۰ ریال'), findsOneWidget);
    await tap(tester, 'ثبت درخواست واریز');
    await enterSms(tester, '12345');
    expect(api.bodies['/cashout/requests'], {'bank_account_id': '01HZZZZZZZZZZZZZZZZZZZZZZB', 'points': 30000, 'code': '12345'});
    expect(find.text('درخواست ثبت شد؛ نتیجه را اطلاع می‌دهیم.'), findsOneWidget);
    expect(find.text('یک درخواست برداشت در حال بررسی داری.'), findsOneWidget);

    ScaffoldMessenger.of(tester.element(find.byType(CashoutPage))).hideCurrentSnackBar();
    await tester.pumpAndSettle();
    await tap(tester, 'لغو درخواست');
    await tester.tap(find.text('تأیید').last);
    await tester.pumpAndSettle();
    expect(api.calls.last, '/cashout/requests/01HZZZZZZZZZZZZZZZZZZZZZZR/cancel');
    await tester.scrollUntilVisible(find.text('لغو شد'), 200, scrollable: find.byType(Scrollable).first);
    expect(find.text('لغو شد'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('۳۰٬۰۰۰'), -200, scrollable: find.byType(Scrollable).first);
    expect(find.text('۳۰٬۰۰۰'), findsOneWidget, reason: 'points are back');
  });
}
