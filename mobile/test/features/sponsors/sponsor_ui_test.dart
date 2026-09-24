import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/sponsors/application/location_source.dart';
import 'package:gamyar/features/sponsors/data/sponsor_models.dart';
import 'package:gamyar/features/sponsors/data/sponsor_repository.dart';
import 'package:gamyar/features/sponsors/presentation/coupons_page.dart';
import 'package:gamyar/features/sponsors/presentation/nearby_page.dart';
import 'package:gamyar/features/sponsors/presentation/visit_page.dart';

import '../../helpers/test_app.dart';

class FakeLocation implements LocationSource {
  int calls = 0;

  @override
  Future<Fix> current() async {
    calls++;
    return (lat: 35.6997, lng: 51.338, accuracy: 12.0, mock: false);
  }
}

Map<String, dynamic> visitJson({String status = 'started', int stay = 0, bool qr = false, bool inside = true, Map<String, dynamic>? coupon}) => {
      'id': 'v1', 'status': status, 'campaign_id': 'c1', 'campaign_name': 'قدم بزن، قهوه بگیر', 'location_id': 'l1', 'location_name': 'شعبه پارک ملت',
      'stay_seconds': stay, 'min_stay_seconds': 180, 'requires_qr': true, 'qr_verified': qr, 'inside': inside, 'points_awarded': status == 'rewarded' ? 40 : 0,
      'rejection_reason': null, 'ping_interval_s': 30, 'entered_at': '2026-09-24T08:00:00Z', 'coupon': coupon,
    };

Map<String, dynamic> couponJson({String status = 'available'}) => {
      'id': 'uc1', 'code': 'ABCD2345', 'status': status, 'status_label': status == 'available' ? 'قابل استفاده' : 'استفاده‌شده',
      'title': '۲۰٪ تخفیف نوشیدنی گرم', 'description': null, 'terms': 'یک بار', 'discount_label': '20٪ تخفیف', 'shared_code': null,
      'sponsor': {'id': 's1', 'name': 'کافه قدم', 'logo_url': null}, 'claimed_at': '2026-09-24T08:00:00Z', 'expires_at': '2026-10-08T08:00:00Z', 'used_at': null,
    };

class FakeSponsorRepository extends SponsorRepository {
  FakeSponsorRepository() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  VisitState visitState = VisitState.fromJson(visitJson());
  final pings = <Fix>[];
  final tokens = <String>[];
  final claims = <String>[];

  @override
  Future<List<NearbyPlace>> nearby(double lat, double lng, {double radiusKm = 5}) async => [
        NearbyPlace.fromJson({
          'id': 'l1', 'name': 'شعبه پارک ملت', 'address': 'ولیعصر', 'city': 'تهران', 'lat': 35.779, 'lng': 51.414, 'radius_m': 60, 'open_now': true, 'distance_m': 450,
          'sponsor': {'id': 's1', 'name': 'کافه قدم', 'logo_url': null},
          'campaigns': [
            {'id': 'c1', 'name': 'قدم بزن، قهوه بگیر', 'reward_points': 40, 'coupon': null, 'verification_method': 'geofence_qr', 'requires_qr': true, 'min_stay_seconds': 180, 'ends_at': '2026-10-24T08:00:00Z', 'eligible': true, 'ineligible_reason': null},
            {'id': 'c2', 'name': 'بازدید دوم', 'reward_points': 10, 'coupon': null, 'verification_method': 'geofence_stay', 'requires_qr': false, 'min_stay_seconds': 120, 'ends_at': '2026-10-24T08:00:00Z', 'eligible': false, 'ineligible_reason': 'cooldown'},
          ],
        }),
      ];

  @override
  Future<VisitState> visit(String visitId) async => visitState;

  bool rewardNext = false;

  @override
  Future<VisitState> ping(String visitId, Fix fix) async {
    pings.add(fix);
    visitState = rewardNext
        ? VisitState.fromJson(visitJson(status: 'rewarded', stay: 180, qr: true, coupon: couponJson()))
        : VisitState.fromJson(visitJson(stay: visitState.staySeconds + 30));
    return visitState;
  }

  @override
  Future<VisitState> submitQr(String visitId, String token) async {
    tokens.add(token);
    visitState = VisitState.fromJson(visitJson(status: 'rewarded', stay: 180, qr: true, coupon: couponJson()));
    return visitState;
  }

  @override
  Future<List<UserCouponItem>> myCoupons() async => [UserCouponItem.fromJson(couponJson()), UserCouponItem.fromJson(couponJson(status: 'used'))];

  @override
  Future<List<CouponOffer>> availableCoupons() async => [
        CouponOffer.fromJson({'id': 'k1', 'title': 'یک کلوچه رایگان', 'discount_label': 'رایگان', 'point_cost': 150, 'sponsor': {'id': 's1', 'name': 'کافه قدم'}, 'remaining': 12}),
      ];

  @override
  Future<UserCouponItem> claim(String couponId, String idempotencyKey) async {
    claims.add('$couponId:$idempotencyKey');
    return UserCouponItem.fromJson(couponJson());
  }
}

/// Records what the real repository sends.
class RecordingApi extends ApiClient {
  RecordingApi() : super(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1');

  final calls = <(String, Object?, Options?)>[];

  @override
  Future<Map<String, dynamic>> post(String path, {Object? data, Options? options}) async {
    calls.add((path, data, options));
    return {'data': path.contains('claim') ? couponJson() : visitJson()};
  }

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, Options? options}) async {
    calls.add((path, query, options));
    return {'data': <dynamic>[]};
  }
}

void main() {
  testWidgets('nearby lists branches with points, requirements and eligibility', (tester) async {
    await tester.pumpWidget(testApp(const NearbyPage(), overrides: [
      locationPermissionProvider.overrideWith((_) async => true),
      locationSourceProvider.overrideWithValue(FakeLocation()),
      sponsorRepositoryProvider.overrideWithValue(FakeSponsorRepository()),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('کافه قدم'), findsOneWidget);
    expect(find.text('شعبه پارک ملت · ۴۵۰ متر'), findsOneWidget);
    expect(find.text('+۴۰'), findsOneWidget);
    expect(find.text('۳ دقیقه حضور · اسکن QR صندوق'), findsOneWidget);
    expect(find.textContaining('به‌تازگی از این شعبه پاداش گرفته‌ای'), findsOneWidget);
  });

  testWidgets('without permission the page explains why location is needed', (tester) async {
    await tester.pumpWidget(testApp(const NearbyPage(), overrides: [locationPermissionProvider.overrideWith((_) async => false)]));
    await tester.pumpAndSettle();
    expect(find.text('فعال کردن موقعیت'), findsOneWidget);
    expect(find.textContaining('ذخیره نمی‌شود'), findsOneWidget);
  });

  testWidgets('visit pings on the server interval, then QR completes it', (tester) async {
    final repo = FakeSponsorRepository();
    final location = FakeLocation();
    await tester.pumpWidget(testApp(const VisitPage(id: 'v1'), overrides: [
      sponsorRepositoryProvider.overrideWithValue(repo),
      locationSourceProvider.overrideWithValue(location),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('داخل محدوده شعبه هستی'), findsOneWidget);
    expect(find.text('از ۰۳:۰۰'), findsOneWidget);
    expect(repo.pings, isEmpty);

    await tester.pump(const Duration(seconds: 30));
    await tester.pump();
    await tester.pump(const Duration(seconds: 30));
    await tester.pump();
    expect(repo.pings, hasLength(2));
    expect(find.text('۰۱:۰۰'), findsOneWidget);

    // The scanner needs a camera; the server's answer to the next ping completes the visit.
    repo.rewardNext = true;
    await tester.pump(const Duration(seconds: 30));
    await tester.pumpAndSettle();

    expect(find.text('بازدید تأیید شد!'), findsOneWidget);
    expect(find.textContaining('۴۰ امتیاز'), findsOneWidget);
    expect(find.text('۲۰٪ تخفیف نوشیدنی گرم'), findsOneWidget);

    final pingsBefore = repo.pings.length;
    await tester.pump(const Duration(seconds: 90));
    expect(repo.pings.length, pingsBefore, reason: 'no pings once the visit is closed');
  });

  testWidgets('coupons show a readable code and claiming asks first', (tester) async {
    final repo = FakeSponsorRepository();
    await tester.pumpWidget(testApp(const CouponsPage(), overrides: [sponsorRepositoryProvider.overrideWithValue(repo)]));
    await tester.pumpAndSettle();

    expect(find.text('ABCD 2345'), findsNWidgets(2));
    expect(find.text('این کد را به صندوق‌دار نشان بده.'), findsOneWidget);

    await tester.tap(find.text('دریافت با امتیاز'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('دریافت با ۱۵۰ امتیاز'));
    await tester.pumpAndSettle();
    expect(find.text('۱۵۰ امتیاز از کیف پولت کم می‌شود. ادامه می‌دهی؟'), findsOneWidget);
    await tester.tap(find.text('تأیید'));
    await tester.pumpAndSettle();

    expect(repo.claims.single, startsWith('k1:'));
    expect(repo.claims.single.length, greaterThan(20));
  });

  test('repository signs visit calls, rounds the nearby query and sends an idempotency key', () async {
    final api = RecordingApi();
    final repo = SponsorRepository(api);

    await repo.nearby(35.699712, 51.338045);
    final (path, query, _) = api.calls.last;
    expect(path, '/locations/nearby');
    expect((query! as Map)['lat'], '35.700');

    await repo.startVisit('c1', 'l1', (lat: 1.0, lng: 2.0, accuracy: 5.0, mock: true));
    expect(api.calls.last.$3!.extra?['signed'], isTrue);
    expect((api.calls.last.$2! as Map)['mock'], isTrue);

    await repo.claim('k1', 'key-123');
    expect(api.calls.last.$3!.headers?['Idempotency-Key'], 'key-123');
    expect(api.calls.last.$3!.extra?['signed'], isTrue);
  });

  test('visit state helpers', () {
    final v = VisitState.fromJson(visitJson(stay: 90));
    expect(v.stayFraction, 0.5);
    expect(v.stayDone, isFalse);
    expect(UserCouponItem.fromJson(couponJson()).prettyCode, 'ABCD 2345');
  });
}
