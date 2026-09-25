import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/organization/presentation/organization_page.dart';

import '../../helpers/test_app.dart';

class _Api extends ApiClient {
  _Api() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');

  Map<String, dynamic>? org;
  final calls = <String>[];

  Map<String, dynamic> _org() => {
        'id': 'o1',
        'name': 'شرکت نمونه',
        'active': true,
        'department': org?['department'],
        'departments_list': ['فروش', 'فنی'],
        'members': 2,
        'week': {'from': '2026-09-19', 'to': '2026-09-25'},
        'my_rank': 1,
        'my_steps': 42000,
        'ranking': [
          {'rank': 1, 'user_id': 'me', 'name': 'سارا', 'department': org?['department'], 'steps': 42000, 'is_me': true},
          {'rank': 2, 'user_id': 'u2', 'name': 'رضا', 'department': 'فنی', 'steps': 30500, 'is_me': false},
        ],
        'departments': [
          {'department': 'فنی', 'members': 1, 'avg_steps': null},
          {'department': 'بدون واحد', 'members': 1, 'avg_steps': null},
        ],
        'challenges': [
          {'id': 'c1', 'title': 'ماه سلامت', 'ends_at': DateTime.now().add(const Duration(days: 5)).toUtc().toIso8601String()},
        ],
      };

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async => {'data': org == null ? null : _org()};

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    calls.add(path);
    if ((data! as Map)['code'] != 'ACME2026') throw const ApiException(code: 'organization_not_found', message: 'سازمانی با این کد پیدا نشد.');
    org = {'department': null};
    return {'data': _org()};
  }

  @override
  Future<Map<String, dynamic>> patch(String path, {Object? data, Options? options}) async {
    calls.add('PATCH $path');
    org = {'department': (data! as Map)['department']};
    return {'data': _org()};
  }

  @override
  Future<Map<String, dynamic>> delete(String path, {Options? options}) async {
    calls.add('DELETE $path');
    org = null;
    return {'data': null};
  }
}

void main() {
  testWidgets('join with code, pick department, see ranking, leave', (tester) async {
    tester.view
      ..physicalSize = const Size(1080, 3200)
      ..devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final api = _Api();
    await tester.pumpWidget(testApp(const OrganizationPage(), overrides: [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    expect(find.text('برنامه سلامت محل کارت'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'WRONG');
    await tester.tap(find.text('عضویت'));
    await tester.pumpAndSettle();
    expect(find.text('سازمانی با این کد پیدا نشد.'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'ACME2026');
    await tester.tap(find.text('عضویت'));
    await tester.pumpAndSettle();
    expect(find.text('شرکت نمونه'), findsOneWidget);
    expect(find.text('۴۲٬۰۰۰'), findsNWidgets(2), reason: 'my steps card + my ranking row');
    expect(find.text('تو'), findsOneWidget);
    expect(find.text('رضا'), findsOneWidget);
    expect(find.text('ماه سلامت'), findsOneWidget);
    expect(find.text('کمتر از ۳ نفر'), findsNWidgets(2), reason: 'small groups are never averaged');

    await tester.tap(find.text('انتخاب کن'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('فروش').last);
    await tester.pumpAndSettle();
    expect(api.calls, contains('PATCH /organization'));
    expect(find.text('فروش'), findsWidgets);

    await tester.scrollUntilVisible(find.text('خروج از سازمان'), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('خروج از سازمان'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('تأیید'));
    await tester.pumpAndSettle();
    expect(api.calls.last, 'DELETE /organization');
    expect(find.text('برنامه سلامت محل کارت'), findsOneWidget);
  });
}
