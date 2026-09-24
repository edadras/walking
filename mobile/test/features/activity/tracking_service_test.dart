import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/sensors/step_platform.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/activity/application/tracking_service.dart';
import 'package:gamyar/features/activity/data/activity_repository.dart';
import 'package:gamyar/features/activity/data/session_queue.dart';
import 'package:gamyar/features/activity/domain/day_splitter.dart';
import 'package:gamyar/features/activity/domain/session_draft.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:timezone/data/latest_10y.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

import '../../helpers/test_app.dart';

class FakePlatform implements StepPlatform {
  List<StepReading> readings = [];
  DateTime? ackedReadings;
  final calls = <String>[];

  @override
  Future<void> readNow() async => calls.add('readNow');

  @override
  Future<(List<StepReading>, List<ActivityTransition>)> pending() async => (readings, <ActivityTransition>[]);

  @override
  Future<void> acknowledge({DateTime? readingsBefore, DateTime? transitionsBefore}) async {
    calls.add('ack');
    ackedReadings = readingsBefore;
  }

  @override
  Future<StepCapabilities> capabilities() async => const StepCapabilities(stepCounter: true, stepDetector: true, activityPermission: true);

  @override
  Future<bool> startPassive() async => true;

  @override
  Future<void> startActiveWalk({required bool gps}) async {}

  @override
  Future<Map<String, dynamic>?> stopActiveWalk() async => null;

  @override
  Future<LiveWalk?> activeWalk() async => null;

  @override
  Stream<LiveWalk> liveWalk() => const Stream.empty();
}

class FakeRepository extends ActivityRepository {
  FakeRepository() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  final sent = <List<Map<String, dynamic>>>[];
  String Function(Map<String, dynamic>) outcome = (_) => 'accepted';
  ApiException? failWith;

  @override
  Future<List<SyncItemResult>> submitBatch(List<Map<String, dynamic>> sessions) async {
    if (failWith != null) throw failWith!;
    sent.add(sessions);
    return [
      for (final s in sessions)
        SyncItemResult(clientSessionId: s['client_session_id'] as String, status: outcome(s), errorCode: outcome(s) == 'rejected' ? 'session_too_old' : null),
    ];
  }
}

void main() {
  late FakePlatform platform;
  late FakeRepository repo;
  late SessionQueue queue;
  late TrackingService service;
  late DayClock clock;

  setUpAll(() {
    sqfliteFfiInit();
    tz_data.initializeTimeZones();
  });

  setUp(() async {
    platform = FakePlatform();
    repo = FakeRepository();
    queue = await SessionQueue.open(path: inMemoryDatabasePath, factory: databaseFactoryFfiNoIsolate);
    clock = DayClock(tz.getLocation('Asia/Tehran'));
    service = TrackingService(platform: platform, queue: Future.value(queue), repository: repo, clock: clock);
  });

  tearDown(() => queue.close());

  // Readings are placed in a window that never straddles local midnight, so the
  // result doesn't depend on when the suite runs (a split day = two sessions).
  DateTime anchor() {
    final now = tz.TZDateTime.now(tz.getLocation('Asia/Tehran'));
    final midnight = tz.TZDateTime(now.location, now.year, now.month, now.day);
    return (now.difference(midnight) < const Duration(hours: 3) ? midnight.subtract(const Duration(minutes: 5)) : now).toUtc();
  }

  DateTime t(int minutesAgo) => anchor().subtract(Duration(minutes: minutesAgo));

  test('collects readings, queues sessions, then acknowledges the readings', () async {
    platform.readings = [StepReading(time: t(40), counter: 100, bootCount: 1), StepReading(time: t(20), counter: 900, bootCount: 1)];

    final report = await service.sync();

    expect(platform.calls, ['readNow', 'ack']);
    expect(platform.ackedReadings, platform.readings.last.time);
    expect(report.enqueued, 1);
    expect(report.accepted, 1);
    expect(report.remaining, 0);
    expect(repo.sent.single.single['raw_steps'], 800);
  });

  test('offline: sessions stay queued and are retried later', () async {
    platform.readings = [StepReading(time: t(40), counter: 0, bootCount: 1), StepReading(time: t(20), counter: 500, bootCount: 1)];
    repo.failWith = ApiException.network;

    final offline = await service.sync();
    expect(offline.offline, isTrue);
    expect(offline.remaining, 1);
    expect(await service.pendingStepsToday(), anyOf(500, 0)); // 0 only if run right at local midnight

    repo.failWith = null;
    platform.readings = [platform.readings.last];
    final online = await service.sync();
    expect(online.accepted, 1);
    expect(await queue.count(), 0);
  });

  test('duplicates count as done; permanent rejections are dropped', () async {
    await queue.enqueue(PassiveSessionFixture.drafts(3));
    var i = 0;
    repo.outcome = (_) => ['accepted', 'duplicate', 'rejected'][i++ % 3];

    final report = await service.flush();

    expect(report.accepted, 2);
    expect(report.rejected, 1);
    expect(await queue.count(), 0);
  });

  test('concurrent sync calls share one run', () async {
    platform.readings = [StepReading(time: t(40), counter: 0, bootCount: 1), StepReading(time: t(20), counter: 500, bootCount: 1)];

    await Future.wait([service.sync(), service.sync(), service.sync()]);

    expect(platform.calls.where((c) => c == 'readNow'), hasLength(1));
    expect(repo.sent, hasLength(1));
  });
}

abstract final class PassiveSessionFixture {
  static List<SessionDraft> drafts(int n) => [
        for (var i = 0; i < n; i++)
          SessionDraft(kind: 'passive', localDate: '2026-09-24', buckets: [DraftBucket(start: DateTime.utc(2026, 9, 24, 6, i * 10), durationS: 600, steps: 100)]),
      ];
}
