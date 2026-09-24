import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/features/activity/data/session_queue.dart';
import 'package:gamyar/features/activity/domain/session_draft.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

SessionDraft draft(int steps, {String date = '2026-09-24'}) => SessionDraft(
      kind: 'passive',
      localDate: date,
      buckets: [DraftBucket(start: DateTime.utc(2026, 9, 24, 6), durationS: 600, steps: steps)],
    );

void main() {
  late SessionQueue queue;

  setUpAll(sqfliteFfiInit);
  setUp(() async => queue = await SessionQueue.open(path: inMemoryDatabasePath, factory: databaseFactoryFfiNoIsolate));
  tearDown(() => queue.close());

  test('allocates strictly increasing sequences that never go back', () async {
    await queue.enqueue([draft(100), draft(200)]);
    final first = await queue.next();
    expect(first.map((s) => s.sequence), [1, 2]);
    expect(first.first.payload['sequence'], 1);

    await queue.remove(first.map((s) => s.clientSessionId));
    await queue.enqueue([draft(300)]);

    expect((await queue.next()).single.sequence, 3);
    expect(await queue.lastSequence(), 3);
  });

  test('reports unsynced steps per day', () async {
    await queue.enqueue([draft(100), draft(250), draft(999, date: '2026-09-23')]);

    expect(await queue.pendingSteps('2026-09-24'), 350);
    expect(await queue.count(), 3);
  });

  test('keeps items and counts attempts until removed', () async {
    await queue.enqueue([draft(100)]);
    final item = (await queue.next()).single;
    await queue.markAttempt([item.clientSessionId]);

    expect((await queue.next()).single.attempts, 1);
  });
}
