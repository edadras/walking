import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../../core/network/api_exception.dart';
import '../../../core/sensors/step_platform.dart';
import '../../auth/application/session_controller.dart';
import '../data/activity_repository.dart';
import '../data/session_queue.dart';
import '../domain/active_walk_mapper.dart';
import '../domain/day_splitter.dart';
import '../domain/passive_builder.dart';
import '../domain/session_draft.dart';

class SyncReport {
  const SyncReport({this.enqueued = 0, this.accepted = 0, this.rejected = 0, this.remaining = 0, this.offline = false});

  final int enqueued;
  final int accepted;
  final int rejected;
  final int remaining;
  final bool offline;
}

/// Moves step data from the device to the server:
///   native readings → passive sessions → offline queue → signed batch upload.
///
/// Every stage is idempotent: readings are acknowledged only after their
/// sessions are durably queued, and the server de-duplicates by client id.
class TrackingService {
  TrackingService({required this.platform, required this.queue, required this.repository, required this.clock});

  final StepPlatform platform;
  final Future<SessionQueue> queue;
  final ActivityRepository repository;
  final DayClock clock;

  Future<SyncReport>? _inFlight;

  static const batchSize = 20;

  /// Collect + upload. Concurrent callers share the same run.
  Future<SyncReport> sync() => _inFlight ??= _sync().whenComplete(() => _inFlight = null);

  Future<SyncReport> _sync() async {
    var enqueued = 0;
    try {
      enqueued = await collectPassive();
    } catch (e) {
      // Sensor/channel failures must never block uploading what's already queued.
      if (kDebugMode) debugPrint('collectPassive failed: $e');
    }
    final flushed = await flush();
    return SyncReport(
      enqueued: enqueued,
      accepted: flushed.accepted,
      rejected: flushed.rejected,
      remaining: flushed.remaining,
      offline: flushed.offline,
    );
  }

  /// Turns new background readings into queued passive sessions.
  Future<int> collectPassive() async {
    await platform.readNow();
    final (readings, transitions) = await platform.pending();
    final result = PassiveSessionBuilder(clock).build(readings, transitions);
    final q = await queue;
    final count = await q.enqueue(result.sessions);
    // Only after the sessions are durable do we let the native side forget the readings.
    await platform.acknowledge(readingsBefore: result.readingsBefore, transitionsBefore: result.transitionsBefore);
    return count;
  }

  /// Queues a finished active walk. Returns the drafts that were queued.
  Future<List<SessionDraft>> queueActiveWalk(Map<String, dynamic> payload) async {
    final drafts = draftsFromActiveWalk(payload, clock).where((d) => d.steps > 0).toList();
    await (await queue).enqueue(drafts);
    return drafts;
  }

  Future<SyncReport> flush() async {
    final q = await queue;
    var accepted = 0;
    var rejected = 0;
    while (true) {
      final batch = await q.next(limit: batchSize);
      if (batch.isEmpty) break;

      final List<SyncItemResult> results;
      try {
        results = await repository.submitBatch(batch.map((s) => s.payload).toList());
      } on ApiException catch (e) {
        await q.markAttempt(batch.map((s) => s.clientSessionId));
        return SyncReport(accepted: accepted, rejected: rejected, remaining: await q.count(), offline: e.isNetwork);
      }

      final done = <String>[];
      for (final r in results) {
        switch (r.status) {
          case 'accepted' || 'duplicate':
            accepted++;
            done.add(r.clientSessionId);
          case 'rejected':
            // Permanent: the server will never accept this payload (too old, invalid, replayed).
            rejected++;
            done.add(r.clientSessionId);
            if (kDebugMode) debugPrint('session ${r.clientSessionId} rejected: ${r.errorCode}');
        }
      }
      await q.remove(done);
      if (done.isEmpty) break; // defensive: never spin on a batch the server didn't answer
    }
    return SyncReport(accepted: accepted, rejected: rejected, remaining: await q.count());
  }

  Future<int> pendingStepsToday() async => (await queue).pendingSteps(clock.dateOf(DateTime.now()));
}

final stepPlatformProvider = Provider<StepPlatform>((ref) => MethodChannelStepPlatform());

final sessionQueueProvider = Provider<Future<SessionQueue>>((ref) => SessionQueue.open());

/// Calendar days follow the account's timezone, exactly like the server.
final dayClockProvider = Provider<DayClock>((ref) {
  final session = ref.watch(sessionProvider).value;
  final name = session is SessionAuthenticated ? session.me.timezone : 'Asia/Tehran';
  try {
    return DayClock(tz.getLocation(name));
  } catch (_) {
    return DayClock(tz.getLocation('Asia/Tehran'));
  }
});

final trackingServiceProvider = Provider<TrackingService>((ref) => TrackingService(
      platform: ref.watch(stepPlatformProvider),
      queue: ref.watch(sessionQueueProvider),
      repository: ref.watch(activityRepositoryProvider),
      clock: ref.watch(dayClockProvider),
    ));

/// Whether automatic step tracking can run, for the home banner / settings.
enum TrackingAvailability { ready, needsPermission, noSensor }

final trackingAvailabilityProvider = FutureProvider.autoDispose<TrackingAvailability>((ref) async {
  final caps = await ref.watch(stepPlatformProvider).capabilities();
  if (!caps.stepCounter) return TrackingAvailability.noSensor;
  if (!caps.activityPermission) return TrackingAvailability.needsPermission;
  return TrackingAvailability.ready;
});
