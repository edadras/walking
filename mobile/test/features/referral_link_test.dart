import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/providers.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/auth/application/pending_referral.dart';
import 'package:gamyar/features/auth/presentation/otp_page.dart';
import 'package:gamyar/features/auth/presentation/phone_page.dart';

import '../helpers/test_app.dart';

void main() {
  test('invite paths and install referrers are parsed strictly', () {
    expect(referralCodeFromPath('/r/abcd1234'), 'ABCD1234');
    expect(referralCodeFromPath('/r/AB'), isNull);
    expect(referralCodeFromPath('/r/ABCD1234/extra'), isNull);
    expect(referralCodeFromPath('/orders/ABCD1234'), isNull);
    expect(referralCodeFromPath('/r/<script>'), isNull);

    expect(codeFromInstallReferrer('code=abcd1234&utm_source=invite'), 'ABCD1234');
    expect(codeFromInstallReferrer('utm_source=google-play&utm_medium=organic'), isNull);
    expect(codeFromInstallReferrer(null), isNull);
  });

  testWidgets('a saved invite pre-fills the referral field at sign-up', (tester) async {
    final store = MemorySecureStore();
    await store.write('pending_referral', 'ABCD1234');
    await tester.pumpWidget(testApp(
      Consumer(builder: (context, ref, _) {
        ref.listen(pendingReferralProvider, (_, _) {}); // app root keeps it alive
        return const OtpPage(args: OtpArgs(phone: '09120000001', resendIn: 60));
      }),
      overrides: [secureStoreProvider.overrideWithValue(store)],
    ));
    await tester.pumpAndSettle();
    expect(find.text('ABCD1234'), findsOneWidget);
  });
}
