import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/ads/application/ad_providers.dart';
import 'package:gamyar/features/ads/data/ads_repository.dart';
import 'package:gamyar/features/ads/presentation/ad_slot.dart';
import 'package:gamyar/features/ads/presentation/rewarded_ad_page.dart';
import 'package:gamyar/features/config/data/app_config.dart';

import '../../helpers/test_app.dart';

Map<String, dynamic> adJson({String format = 'banner'}) => {
      'id': 'a1', 'format': format, 'title': 'کفش پیاده‌روی', 'body': 'راحت برای هر روز', 'image_url': null,
      'cta_label': 'مشاهده', 'action_url': 'https://example.com', 'min_view_seconds': 3, 'advertiser': 'فروشگاه ورزشی', 'token': 'tok-1',
    };

class FakeAdsRepository extends AdsRepository {
  FakeAdsRepository() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  final sent = <({String token, String type})>[];
  int placementCalls = 0;
  int completes = 0;

  @override
  Future<AdCreative?> placement(String key) async {
    placementCalls++;
    return AdCreative.fromJson(adJson());
  }

  @override
  Future<void> events(List<({String token, String type})> events) async => sent.addAll(events);

  @override
  Future<RewardedView> startRewarded(String placement) async => RewardedView.fromJson(
      {'id': 'v1', 'status': 'started', 'ad': adJson(format: 'rewarded'), 'reward_points': 10, 'points_awarded': 0, 'started_at': '2026-09-24T08:00:00Z'});

  @override
  Future<RewardedView> completeRewarded(String viewId) async {
    completes++;
    return RewardedView.fromJson(
        {'id': 'v1', 'status': 'rewarded', 'ad': adJson(format: 'rewarded'), 'reward_points': 10, 'points_awarded': 10, 'started_at': '2026-09-24T08:00:00Z'});
  }
}

AppConfig config({bool ads = true}) => AppConfig(features: {'ads': ads, 'rewarded_ads': ads}, settings: const {}, updateRequired: false, serverTime: null);

void main() {
  testWidgets('ad slot shows a labelled ad and reports one impression', (tester) async {
    final repo = FakeAdsRepository();
    await tester.pumpWidget(testApp(const AdSlot(placement: 'home_banner'), overrides: [
      adsRepositoryProvider.overrideWithValue(repo),
      configProvider.overrideWithValue(config()),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('تبلیغ'), findsOneWidget);
    expect(find.text('کفش پیاده‌روی'), findsOneWidget);
    expect(find.text('فروشگاه ورزشی'), findsOneWidget);

    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(seconds: 3));
    expect(repo.sent.map((e) => e.type), ['impression']);
    expect(repo.sent.single.token, 'tok-1');

    await tester.pump(const Duration(seconds: 10));
    expect(repo.sent, hasLength(1), reason: 'impression is reported once');
  });

  testWidgets('with ads off nothing is requested or shown', (tester) async {
    final repo = FakeAdsRepository();
    await tester.pumpWidget(testApp(const AdSlot(placement: 'home_banner'), overrides: [
      adsRepositoryProvider.overrideWithValue(repo),
      configProvider.overrideWithValue(config(ads: false)),
    ]));
    await tester.pumpAndSettle();
    expect(find.text('تبلیغ'), findsNothing);
    expect(repo.placementCalls, 0);
  });

  testWidgets('rewarded ad unlocks the claim only after the countdown', (tester) async {
    final repo = FakeAdsRepository();
    await tester.pumpWidget(testApp(const RewardedAdPage(), overrides: [adsRepositoryProvider.overrideWithValue(repo)]));
    await tester.pump();
    await tester.pump();

    expect(find.text('۳ ثانیه تا دریافت امتیاز'), findsOneWidget);
    expect(find.text('دریافت ۱۰ امتیاز'), findsNothing);

    await tester.pump(const Duration(seconds: 3));
    await tester.tap(find.text('دریافت ۱۰ امتیاز'));
    await tester.pumpAndSettle();

    expect(repo.completes, 1);
    expect(find.text('امتیاز ثبت شد'), findsOneWidget);
  });
}
