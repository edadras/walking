import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/auth/application/session_controller.dart';
import 'package:gamyar/features/profile/data/me.dart';
import 'package:gamyar/features/profile/data/profile_repository.dart';
import 'package:gamyar/features/profile/presentation/avatar_editor.dart';

import '../../helpers/test_app.dart';

Me meWith(String? avatar) => Me.fromJson({
      'id': '01h', 'public_name': 'سارا', 'display_name': 'سارا', 'status': 'active', 'level': 2, 'xp': 10, 'avatar_url': avatar,
      'referral_code': 'X', 'timezone': 'Asia/Tehran', 'profile': <String, dynamic>{}, 'settings': <String, dynamic>{},
    });

class _Session extends SessionController {
  _Session(this.initial);
  final Me initial;

  @override
  Future<SessionState> build() async => SessionAuthenticated(initial);
}

class _Repo extends ProfileRepository {
  _Repo() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  final calls = <String>[];

  @override
  Future<Me> uploadAvatar(String filePath) async {
    calls.add('upload:$filePath');
    return meWith('https://cdn.test/a.jpg');
  }

  @override
  Future<Me> removeAvatar() async {
    calls.add('remove');
    return meWith(null);
  }
}

void main() {
  Future<(_Repo, List<AvatarSource>)> pump(WidgetTester tester, {String? avatar, String? picked = '/tmp/p.jpg'}) async {
    final repo = _Repo();
    final sources = <AvatarSource>[];
    await tester.pumpWidget(testApp(
      // As in the app: the editor only exists once the session is authenticated.
      Scaffold(body: Consumer(builder: (_, ref, _) => ref.watch(sessionProvider).hasValue ? const AvatarEditor() : const SizedBox())),
      overrides: [
        sessionProvider.overrideWith(() => _Session(meWith(avatar))),
        profileRepositoryProvider.overrideWithValue(repo),
        avatarPickerProvider.overrideWithValue((s) async {
          sources.add(s);
          return picked;
        }),
      ],
    ));
    await tester.pumpAndSettle();
    return (repo, sources);
  }

  testWidgets('picking from the gallery uploads and updates the session', (tester) async {
    final (repo, sources) = await pump(tester);
    expect(find.text('حذف عکس'), findsNothing);

    await tester.tap(find.byType(AvatarEditor));
    await tester.pumpAndSettle();
    expect(find.text('حذف عکس'), findsNothing, reason: 'nothing to remove yet');
    await tester.tap(find.text('انتخاب از گالری'));
    await tester.pumpAndSettle();

    expect(sources, [AvatarSource.gallery]);
    expect(repo.calls, ['upload:/tmp/p.jpg']);
    expect(find.text('عکس پروفایل به‌روز شد.'), findsOneWidget);
  });

  testWidgets('cancelling the picker uploads nothing', (tester) async {
    final (repo, _) = await pump(tester, picked: null);
    await tester.tap(find.byType(AvatarEditor));
    await tester.pumpAndSettle();
    await tester.tap(find.text('انتخاب از گالری'));
    await tester.pumpAndSettle();
    expect(repo.calls, isEmpty);
  });

  testWidgets('an existing photo can be removed', (tester) async {
    final (repo, _) = await pump(tester, avatar: 'https://cdn.test/old.jpg');
    await tester.tap(find.byType(AvatarEditor));
    await tester.pumpAndSettle();
    await tester.tap(find.text('حذف عکس'));
    await tester.pumpAndSettle();
    expect(repo.calls, ['remove']);
    expect(find.text('عکس پروفایل حذف شد.'), findsOneWidget);
  });
}
