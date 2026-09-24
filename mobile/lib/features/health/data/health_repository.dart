import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'health_models.dart';

class HealthRepository {
  HealthRepository(this._api);

  final ApiClient _api;

  Future<HealthSummaryData> summary(String range) async =>
      HealthSummaryData.fromJson((await _api.get('/health/summary', query: {'range': range}))['data'] as Map<String, dynamic>);

  Future<WeeklyReport> weeklyReport() async => WeeklyReport.fromJson((await _api.get('/activity/weekly-report'))['data'] as Map<String, dynamic>);

  Future<WaterDay> water() async => WaterDay.fromJson((await _api.get('/health/water'))['data'] as Map<String, dynamic>);

  Future<WaterDay> addWater(int ml) async => WaterDay.fromJson((await _api.post('/health/water', data: {'amount_ml': ml}))['data'] as Map<String, dynamic>);

  Future<void> deleteWater(int id) => _api.delete('/health/water/$id');
}

final healthRepositoryProvider = Provider<HealthRepository>((ref) => HealthRepository(ref.watch(apiClientProvider)));

final healthSummaryProvider = FutureProvider.autoDispose.family<HealthSummaryData, String>((ref, range) => ref.watch(healthRepositoryProvider).summary(range));

final weeklyReportProvider = FutureProvider.autoDispose<WeeklyReport>((ref) => ref.watch(healthRepositoryProvider).weeklyReport());

final waterProvider = FutureProvider.autoDispose<WaterDay>((ref) => ref.watch(healthRepositoryProvider).water());
