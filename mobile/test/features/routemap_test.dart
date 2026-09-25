import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/features/routemap/application/route_outbox.dart';
import 'package:gamyar/features/routemap/data/route_map.dart';
import 'package:mocktail/mocktail.dart';

class _Repo extends Mock implements RouteMapRepository {}

void main() {
  test('tracks parse as coloured lines with time left', () {
    final t = MapTrack.fromJson({
      'color': '#8E4EC6',
      'points': [
        [35.7, 51.4],
        [35.71, 51.41],
      ],
      'expires_in': 3600,
    });
    expect(t.points.last.latitude, 35.71);
    expect(t.expiresIn, const Duration(hours: 1));
    expect(hexColor('#8E4EC6').toARGB32(), 0xFF8E4EC6);
  });

  test('a finished route waits offline and is sent later; refused ones are dropped', () async {
    final dir = Directory.systemTemp.createTempSync('routes');
    addTearDown(() => dir.deleteSync(recursive: true));
    final repo = _Repo();
    final outbox = RouteOutbox(() async => dir, repo);
    final line = [
      [35.7, 51.4, 1758790000000],
      [35.701, 51.4, 1758790010000],
    ];

    when(() => repo.upload(any())).thenThrow(const ApiException(code: 'network', message: 'offline'));
    await outbox.add(line);
    expect(File('${dir.path}/routes.json').readAsStringSync(), contains('35.701'));

    when(() => repo.upload(any())).thenAnswer((_) async {});
    expect(await outbox.flush(), 1);
    expect(File('${dir.path}/routes.json').readAsStringSync(), '[]');

    when(() => repo.upload(any())).thenThrow(const ApiException(code: 'invalid', message: 'bad', status: 422));
    await outbox.add(line);
    expect(File('${dir.path}/routes.json').readAsStringSync(), '[]');
  });
}
