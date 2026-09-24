import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../domain/session_draft.dart';

class QueuedSession {
  const QueuedSession({
    required this.clientSessionId,
    required this.sequence,
    required this.localDate,
    required this.steps,
    required this.payload,
    required this.attempts,
  });

  final String clientSessionId;
  final int sequence;
  final String localDate;
  final int steps;
  final Map<String, dynamic> payload;
  final int attempts;
}

/// Durable offline queue of sessions waiting to be synced.
///
/// Sequence numbers are allocated here, in the same transaction as the insert,
/// and never decrease — the server rejects any sequence it has already seen.
/// The queue holds observations only; nothing in it can mint points.
class SessionQueue {
  SessionQueue(this._db);

  final Database _db;

  static Future<SessionQueue> open({String? path, DatabaseFactory? factory}) async {
    final f = factory ?? databaseFactory;
    final dbPath = path ?? p.join(await f.getDatabasesPath(), 'gamyar_activity.db');
    final db = await f.openDatabase(
      dbPath,
      options: OpenDatabaseOptions(
        version: 1,
        onCreate: (db, _) async {
          await db.execute('''
            CREATE TABLE pending_sessions (
              client_session_id TEXT PRIMARY KEY,
              sequence INTEGER NOT NULL UNIQUE,
              local_date TEXT NOT NULL,
              steps INTEGER NOT NULL,
              payload TEXT NOT NULL,
              attempts INTEGER NOT NULL DEFAULT 0,
              created_at INTEGER NOT NULL
            )''');
          await db.execute('CREATE TABLE kv (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        },
      ),
    );
    return SessionQueue(db);
  }

  Future<int> enqueue(List<SessionDraft> drafts) async {
    if (drafts.isEmpty) return 0;
    return _db.transaction((txn) async {
      final row = await txn.query('kv', where: 'key = ?', whereArgs: ['sequence']);
      var sequence = row.isEmpty ? 0 : int.parse(row.first['value']! as String);
      for (final draft in drafts) {
        sequence++;
        await txn.insert('pending_sessions', {
          'client_session_id': draft.clientSessionId,
          'sequence': sequence,
          'local_date': draft.localDate,
          'steps': draft.steps,
          'payload': jsonEncode(draft.toJson(sequence: sequence)),
          'created_at': DateTime.now().millisecondsSinceEpoch,
        });
      }
      await txn.insert('kv', {'key': 'sequence', 'value': '$sequence'}, conflictAlgorithm: ConflictAlgorithm.replace);
      return drafts.length;
    });
  }

  Future<List<QueuedSession>> next({int limit = 20}) async {
    final rows = await _db.query('pending_sessions', orderBy: 'sequence ASC', limit: limit);
    return rows
        .map((r) => QueuedSession(
              clientSessionId: r['client_session_id']! as String,
              sequence: r['sequence']! as int,
              localDate: r['local_date']! as String,
              steps: r['steps']! as int,
              payload: jsonDecode(r['payload']! as String) as Map<String, dynamic>,
              attempts: r['attempts']! as int,
            ))
        .toList();
  }

  Future<void> remove(Iterable<String> ids) async {
    final list = ids.toList();
    if (list.isEmpty) return;
    await _db.delete(
      'pending_sessions',
      where: 'client_session_id IN (${List.filled(list.length, '?').join(',')})',
      whereArgs: list,
    );
  }

  Future<void> markAttempt(Iterable<String> ids) async {
    for (final id in ids) {
      await _db.rawUpdate('UPDATE pending_sessions SET attempts = attempts + 1 WHERE client_session_id = ?', [id]);
    }
  }

  Future<int> count() async => Sqflite.firstIntValue(await _db.rawQuery('SELECT COUNT(*) FROM pending_sessions')) ?? 0;

  /// Steps recorded on the device for [localDate] but not yet on the server.
  Future<int> pendingSteps(String localDate) async =>
      Sqflite.firstIntValue(
        await _db.rawQuery('SELECT COALESCE(SUM(steps),0) FROM pending_sessions WHERE local_date = ?', [localDate]),
      ) ??
      0;

  Future<int> lastSequence() async {
    final row = await _db.query('kv', where: 'key = ?', whereArgs: ['sequence']);
    return row.isEmpty ? 0 : int.parse(row.first['value']! as String);
  }

  Future<void> close() => _db.close();
}
