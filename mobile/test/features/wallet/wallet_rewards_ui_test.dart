import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/rewards/data/rewards_models.dart';
import 'package:gamyar/features/rewards/data/rewards_repository.dart';
import 'package:gamyar/features/rewards/presentation/rewards_page.dart';
import 'package:gamyar/features/wallet/data/wallet_models.dart';
import 'package:gamyar/features/wallet/data/wallet_repository.dart';
import 'package:gamyar/features/wallet/presentation/wallet_page.dart';

import '../../helpers/test_app.dart';

Map<String, dynamic> walletJson({int available = 1840, int pending = 60}) => {
      'available': available, 'pending': pending, 'rial_per_point': 500, 'rial_value': available * 500,
      'pending_rial_value': pending * 500, 'lifetime_earned': 2400, 'lifetime_spent': 560,
      'next_release_at': DateTime.now().add(const Duration(hours: 5)).toUtc().toIso8601String(),
    };

class FakeWalletRepository extends WalletRepository {
  FakeWalletRepository() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  @override
  Future<TxPage> transactions({String filter = 'all', String? cursor}) async => TxPage(
        filter == 'spent'
            ? [PointTx.fromJson({'id': 'b', 'type': 'purchase', 'type_label': 'خرید', 'amount': -400, 'status': 'completed', 'status_label': 'انجام‌شده', 'description': 'کارت هدیه', 'rial_value': 200000, 'created_at': '2026-09-23T10:00:00Z'})]
            : [
                PointTx.fromJson({'id': 'a', 'type': 'walking_reward', 'type_label': 'پاداش قدم', 'amount': 68, 'status': 'pending', 'status_label': 'در حال بررسی', 'description': 'پاداش ۶٬۸۴۰ قدم', 'rial_value': 34000, 'created_at': '2026-09-24T10:00:00Z'}),
                PointTx.fromJson({'id': 'c', 'type': 'walking_reward', 'type_label': 'پاداش قدم', 'amount': 40, 'status': 'reversed', 'status_label': 'لغوشده', 'description': 'پاداش ۴٬۰۰۰ قدم', 'rial_value': 20000, 'created_at': '2026-09-22T10:00:00Z'}),
              ],
        null,
      );
}

void main() {
  testWidgets('wallet shows balance, rial value, pending and history with filters', (tester) async {
    await tester.pumpWidget(testApp(const WalletPage(), overrides: [
      walletBalanceProvider.overrideWith((_) async => WalletBalance.fromJson(walletJson())),
      walletRepositoryProvider.overrideWithValue(FakeWalletRepository()),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('۱٬۸۴۰'), findsOneWidget);
    expect(find.text('ارزش تقریبی: ۹۲۰٬۰۰۰ ریال'), findsOneWidget);
    expect(find.text('پاداش ۶٬۸۴۰ قدم'), findsOneWidget);
    expect(find.text('+۶۸'), findsOneWidget);
    expect(find.textContaining('لغو شد'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'مصرف'));
    await tester.pumpAndSettle();
    expect(find.text('کارت هدیه'), findsOneWidget);
    expect(find.text('−۴۰۰'), findsOneWidget);
  });

  testWidgets('reward center explains the earning rules and upcoming multipliers', (tester) async {
    final center = RewardCenter.fromJson({
      'today': {'points': 68, 'rial_value': 34000, 'verified_steps': 6840, 'rewarded_steps': 6840, 'remaining_rewardable_steps': 13160, 'remaining_points': 232, 'goal': 10000, 'goal_reached': false},
      'wallet': walletJson(),
      'earning': {
        'steps_per_unit': 1000, 'points_per_unit': 10, 'daily_cap': 300, 'weekly_cap': 1500, 'max_rewarded_steps': 20000,
        'goal_bonus': 20, 'streak_bonuses': {'7': 30, '30': 150}, 'multiplier_now': 1.0,
        'upcoming_multipliers': [{'date': '2026-09-25', 'multiplier': 1.5, 'names': ['جمعه‌ها ×۱٫۵']}],
      },
      'recent': [{'id': 'r1', 'kind': 'walking', 'points': 68, 'status': 'pending', 'multiplier': 1.0, 'created_at': '2026-09-24T10:00:00Z'}],
    });

    await tester.pumpWidget(testApp(const RewardsPage(), overrides: [rewardCenterProvider.overrideWith((_) async => center)]));
    await tester.pumpAndSettle();

    expect(find.text('+۶۸'), findsWidgets);
    expect(find.text('هر ۱٬۰۰۰ قدم تأییدشده'), findsOneWidget);
    expect(find.text('امروز تا ۱۳٬۱۶۰ قدم دیگر امتیاز دارد'), findsOneWidget);
    expect(find.text('رسیدن به هدف روزانه'), findsOneWidget);
    expect(find.text('۷ روز متوالی'), findsOneWidget);
    expect(find.text('×۱٫۵'), findsOneWidget);
    expect(find.text('پاداش قدم'), findsOneWidget);
  });
}
