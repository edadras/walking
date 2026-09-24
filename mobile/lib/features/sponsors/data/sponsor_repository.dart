import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'sponsor_models.dart';

/// A position fix as sent to the server (the server decides presence).
typedef Fix = ({double lat, double lng, double accuracy, bool mock});

class SponsorRepository {
  SponsorRepository(this._api);

  final ApiClient _api;

  Map<String, dynamic> _fix(Fix f) => {'lat': f.lat, 'lng': f.lng, 'accuracy': f.accuracy, 'mock': f.mock};

  Future<List<NearbyPlace>> nearby(double lat, double lng, {double radiusKm = 5}) async {
    // Coordinates are rounded to ~100 m for the list query; the server never stores them.
    final r = await _api.get('/locations/nearby', query: {'lat': lat.toStringAsFixed(3), 'lng': lng.toStringAsFixed(3), 'radius_km': radiusKm});
    return (r['data'] as List).map((e) => NearbyPlace.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<CampaignDetail> campaign(String id) async => CampaignDetail.fromJson((await _api.get('/campaigns/$id'))['data'] as Map<String, dynamic>);

  Future<VisitState> startVisit(String campaignId, String locationId, Fix fix) async => VisitState.fromJson(
      (await _api.post('/visits', data: {'campaign_id': campaignId, 'location_id': locationId, ..._fix(fix)}, options: Req.signed()))['data'] as Map<String, dynamic>);

  Future<VisitState> ping(String visitId, Fix fix) async =>
      VisitState.fromJson((await _api.post('/visits/$visitId/ping', data: _fix(fix), options: Req.signed()))['data'] as Map<String, dynamic>);

  Future<VisitState> submitQr(String visitId, String token) async =>
      VisitState.fromJson((await _api.post('/visits/$visitId/qr', data: {'token': token}, options: Req.signed()))['data'] as Map<String, dynamic>);

  Future<VisitState> visit(String visitId) async => VisitState.fromJson((await _api.get('/visits/$visitId'))['data'] as Map<String, dynamic>);

  Future<List<UserCouponItem>> myCoupons() async =>
      ((await _api.get('/coupons'))['data'] as List).map((e) => UserCouponItem.fromJson(e as Map<String, dynamic>)).toList();

  Future<List<CouponOffer>> availableCoupons() async =>
      ((await _api.get('/coupons', query: {'tab': 'available'}))['data'] as List).map((e) => CouponOffer.fromJson(e as Map<String, dynamic>)).toList();

  Future<UserCouponItem> claim(String couponId, String idempotencyKey) async => UserCouponItem.fromJson(
      (await _api.post('/coupons/$couponId/claim', options: Req.signed(Options(headers: {'Idempotency-Key': idempotencyKey}))))['data'] as Map<String, dynamic>);
}

final sponsorRepositoryProvider = Provider<SponsorRepository>((ref) => SponsorRepository(ref.watch(apiClientProvider)));

final campaignProvider = FutureProvider.autoDispose.family<CampaignDetail, String>((ref, id) => ref.watch(sponsorRepositoryProvider).campaign(id));

final myCouponsProvider = FutureProvider.autoDispose<List<UserCouponItem>>((ref) => ref.watch(sponsorRepositoryProvider).myCoupons());

final availableCouponsProvider = FutureProvider.autoDispose<List<CouponOffer>>((ref) => ref.watch(sponsorRepositoryProvider).availableCoupons());
