import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/widgets/net_image.dart';
import 'package:gamyar/features/posts/application/photo_outbox.dart';
import 'package:gamyar/features/posts/data/posts.dart';
import 'package:gamyar/features/posts/presentation/feed_page.dart';
import 'package:gamyar/features/posts/presentation/walk_photo_button.dart';

import '../../helpers/test_app.dart';

Map<String, dynamic> _json(String id, {bool mine = false, bool liked = false, int likes = 3, int views = 41, String? status, String type = 'walking'}) => {
      'id': id,
      'image_url': 'https://cdn.test/$id.jpg',
      'thumb_url': 'https://cdn.test/$id-t.jpg',
      'width': 1200,
      'height': 900,
      'caption': 'غروب پارک ملت',
      'captured_at': DateTime.now().subtract(const Duration(minutes: 20)).toUtc().toIso8601String(),
      'author': {'name': mine ? 'من' : 'مریم', 'level': 4},
      'walk': {'activity_type': type, 'steps': 4200, 'distance_m': 6100},
      'views_count': views,
      'likes_count': likes,
      'liked': liked,
      'mine': mine,
      'status': ?status,
      if (mine) 'points_awarded': 2,
    };

class FakePosts implements PostsRepository {
  final liked = <String>[];
  final unliked = <String>[];
  final viewed = <String>[];
  final reported = <(String, String)>[];
  final uploads = <(String, DateTime, String?)>[];
  Object? uploadError;

  @override
  Future<PostPage> feed({bool mine = false, String? before}) async => mine
      ? (posts: [WalkPost.fromJson(_json('m1', mine: true, status: 'pending', likes: 0, views: 0))], next: null)
      : (posts: [WalkPost.fromJson(_json('p1')), WalkPost.fromJson(_json('p2', type: 'bicycle'))], next: null);

  @override
  Future<int> like(String id) async {
    liked.add(id);
    return 4;
  }

  @override
  Future<int> unlike(String id) async {
    unliked.add(id);
    return 3;
  }

  @override
  Future<void> views(List<String> ids) async => viewed.addAll(ids);

  @override
  Future<void> report(String id, String reason) async => reported.add((id, reason));

  @override
  Future<void> delete(String id) async {}

  @override
  Future<WalkPost> upload(String path, DateTime capturedAt, String? caption) async {
    if (uploadError != null) throw uploadError!;
    uploads.add((path, capturedAt, caption));
    return WalkPost.fromJson(_json('new', mine: true, status: 'pending'));
  }
}

// 1×1 transparent PNG so no network is touched.
final _pixel = MemoryImage(base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
ImageProvider _noImage(String url) => _pixel;

void main() {
  late Directory dir;
  setUp(() => dir = Directory.systemTemp.createTempSync('outbox'));
  tearDown(() => dir.deleteSync(recursive: true));

  List overrides(FakePosts repo) => [
        postsRepositoryProvider.overrideWithValue(repo),
        outboxDirProvider.overrideWithValue(() async => dir),
        imageResolverProvider.overrideWithValue(_noImage),
      ];

  testWidgets('feed: real counts, the walk it came from, like toggles and reports hide', (tester) async {
    tester.view.physicalSize = const Size(1170, 5000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final repo = FakePosts();
    await tester.pumpWidget(testApp(const FeedPage(), overrides: overrides(repo)));
    await tester.pumpAndSettle();

    expect(find.text('مریم'), findsNWidgets(2));
    expect(find.text('۴۱'), findsNWidgets(2)); // views
    expect(find.textContaining('پیاده‌روی ۴٬۲۰۰ قدمی'), findsOneWidget);
    expect(find.textContaining('۶٫۱ کیلومتر دوچرخه‌سواری'), findsOneWidget);
    expect(find.textContaining('هر لایک ۱ امتیاز'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.favorite_border_rounded).first);
    await tester.pumpAndSettle();
    expect(repo.liked, ['p1']);
    expect(find.text('۴'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.favorite_rounded).first);
    await tester.pumpAndSettle();
    expect(repo.unliked, ['p1']);

    // A card on screen for over a second is one view; reported in a batch.
    await tester.pump(const Duration(seconds: 6));
    expect(repo.viewed, containsAll(['p1', 'p2']));

    await tester.tap(find.byIcon(Icons.more_vert_rounded).last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('حریم خصوصی (چهره، پلاک، خانه)'));
    await tester.pumpAndSettle();
    expect(repo.reported, [('p2', 'privacy')]);
    expect(find.text('مریم'), findsOneWidget);
  });

  testWidgets('my photos show review state and are never counted as my own views', (tester) async {
    final repo = FakePosts();
    await tester.pumpWidget(testApp(const FeedPage(), overrides: overrides(repo)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('عکس‌های من'));
    await tester.pumpAndSettle();

    expect(find.text('در انتظار تأیید پیاده‌روی'), findsOneWidget);
    await tester.pump(const Duration(seconds: 6));
    expect(repo.viewed, isNot(contains('m1')));
  });

  testWidgets('a photo taken on the walk is uploaded with its time and caption', (tester) async {
    final repo = FakePosts();
    final shot = File('${dir.path}/camera.jpg')..writeAsBytesSync([1, 2, 3]);
    await tester.pumpWidget(testApp(
      const Scaffold(body: Center(child: WalkPhotoButton())),
      overrides: [...overrides(repo), walkCameraProvider.overrideWithValue(() async => shot.path)],
    ));
    await tester.tap(find.text('عکس'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'درخت‌های چنار');
    await tester.tap(find.text('ثبت عکس'));
    // File copy/upload is real I/O: let it run, then settle the UI.
    for (var i = 0; i < 40 && repo.uploads.isEmpty; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
      await tester.pump();
    }
    for (var i = 0; i < 10; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump(const Duration(milliseconds: 100));
    }

    expect(repo.uploads.single.$3, 'درخت‌های چنار');
    expect(find.text('۱ عکس در این پیاده‌روی'), findsOneWidget);
    expect(shot.existsSync(), isFalse); // the camera copy is not left behind
  });

  test('offline photos wait in the outbox; refused ones are dropped', () async {
    final repo = FakePosts()..uploadError = const ApiException(code: 'network', message: 'offline');
    final container = ProviderContainer(overrides: [postsRepositoryProvider.overrideWithValue(repo), outboxDirProvider.overrideWithValue(() async => dir)]);
    addTearDown(container.dispose);
    final outbox = container.read(photoOutboxProvider.notifier);
    container.read(photoOutboxProvider);

    final shot = File('${dir.path}/c.jpg')..writeAsBytesSync([1]);
    await outbox.add(shot.path, DateTime(2026, 9, 24, 11, 7), null);
    expect(container.read(photoOutboxProvider), hasLength(1));

    repo.uploadError = null;
    expect(await outbox.flush(), 1);
    expect(container.read(photoOutboxProvider), isEmpty);

    final again = File('${dir.path}/d.jpg')..writeAsBytesSync([1]);
    repo.uploadError = const ApiException(code: 'post_limit', message: 'limit', status: 422);
    await outbox.add(again.path, DateTime(2026, 9, 24, 11, 8), null);
    expect(container.read(photoOutboxProvider), isEmpty);
  });
}
