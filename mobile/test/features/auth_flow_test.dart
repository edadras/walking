import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/features/auth/data/auth_repository.dart';
import 'package:gamyar/features/auth/presentation/phone_page.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/test_app.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repo;

  setUp(() => repo = MockAuthRepository());

  Widget phonePage(List<String> pushed) {
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (_, _) => const PhonePage()),
      GoRoute(
        path: '/auth/otp',
        builder: (_, s) {
          pushed.add((s.extra! as OtpArgs).phone);
          return const Scaffold(body: Text('otp'));
        },
      ),
    ]);
    return testRouterApp(router, overrides: [authRepositoryProvider.overrideWithValue(repo)]);
  }

  testWidgets('rejects an invalid number without calling the server', (tester) async {
    await tester.pumpWidget(phonePage([]));
    await tester.enterText(find.byType(TextField), '0212345678');
    await tester.tap(find.text('دریافت کد'));
    await tester.pump();

    expect(find.text('شماره موبایل معتبر نیست.'), findsOneWidget);
    verifyNever(() => repo.requestOtp(any()));
  });

  testWidgets('requests a code with a normalised number and moves to OTP', (tester) async {
    when(() => repo.requestOtp('09121234567')).thenAnswer((_) async => const OtpRequestResult(expiresIn: 120, resendIn: 60));
    final pushed = <String>[];

    await tester.pumpWidget(phonePage(pushed));
    await tester.enterText(find.byType(TextField), '۰۹۱۲۱۲۳۴۵۶۷');
    await tester.tap(find.text('دریافت کد'));
    await tester.pumpAndSettle();

    expect(pushed, ['09121234567']);
    expect(find.text('otp'), findsOneWidget);
  });
}
