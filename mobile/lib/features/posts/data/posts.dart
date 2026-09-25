import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class PostAuthor {
  const PostAuthor({required this.name, this.avatarUrl, this.level});

  factory PostAuthor.fromJson(Map<String, dynamic> j) =>
      PostAuthor(name: j['name'] as String? ?? '', avatarUrl: j['avatar_url'] as String?, level: (j['level'] as num?)?.toInt());

  final String name;
  final String? avatarUrl;
  final int? level;
}

class WalkPost {
  const WalkPost({
    required this.id,
    required this.imageUrl,
    required this.thumbUrl,
    required this.width,
    required this.height,
    required this.capturedAt,
    required this.author,
    required this.viewsCount,
    required this.likesCount,
    required this.liked,
    required this.mine,
    this.caption,
    this.walkType,
    this.walkSteps,
    this.walkDistanceM,
    this.status,
    this.pointsAwarded,
  });

  factory WalkPost.fromJson(Map<String, dynamic> j) {
    final walk = j['walk'] as Map<String, dynamic>?;
    return WalkPost(
      id: j['id'] as String,
      imageUrl: j['image_url'] as String,
      thumbUrl: j['thumb_url'] as String? ?? j['image_url'] as String,
      width: _i(j['width']),
      height: _i(j['height']),
      caption: j['caption'] as String?,
      capturedAt: DateTime.parse(j['captured_at'] as String),
      author: PostAuthor.fromJson(j['author'] as Map<String, dynamic>),
      walkType: walk?['activity_type'] as String?,
      walkSteps: (walk?['steps'] as num?)?.toInt(),
      walkDistanceM: (walk?['distance_m'] as num?)?.toInt(),
      viewsCount: _i(j['views_count']),
      likesCount: _i(j['likes_count']),
      liked: j['liked'] == true,
      mine: j['mine'] == true,
      status: j['status'] as String?,
      pointsAwarded: (j['points_awarded'] as num?)?.toInt(),
    );
  }

  final String id;
  final String imageUrl;
  final String thumbUrl;
  final int width;
  final int height;
  final String? caption;
  final DateTime capturedAt;
  final PostAuthor author;
  final String? walkType;
  final int? walkSteps;
  final int? walkDistanceM;
  final int viewsCount;
  final int likesCount;
  final bool liked;
  final bool mine;

  /// Only for my own posts: pending | published | hidden.
  final String? status;
  final int? pointsAwarded;

  double get aspect => width > 0 && height > 0 ? width / height : 4 / 3;

  WalkPost copyWith({bool? liked, int? likesCount}) => WalkPost(
        id: id,
        imageUrl: imageUrl,
        thumbUrl: thumbUrl,
        width: width,
        height: height,
        caption: caption,
        capturedAt: capturedAt,
        author: author,
        walkType: walkType,
        walkSteps: walkSteps,
        walkDistanceM: walkDistanceM,
        viewsCount: viewsCount,
        likesCount: likesCount ?? this.likesCount,
        liked: liked ?? this.liked,
        mine: mine,
        status: status,
        pointsAwarded: pointsAwarded,
      );
}

typedef PostPage = ({List<WalkPost> posts, String? next});

class PostsRepository {
  PostsRepository(this._api);

  final ApiClient _api;

  Future<PostPage> feed({bool mine = false, String? before}) async {
    final r = await _api.get('/posts', query: {'scope': mine ? 'mine' : 'all', 'before': ?before});
    return (
      posts: (r['data'] as List).map((e) => WalkPost.fromJson(e as Map<String, dynamic>)).toList(),
      next: (r['meta'] as Map?)?['next'] as String?,
    );
  }

  /// Multipart and unsigned like avatars: the server re-encodes the picture and only
  /// publishes it once the walk it was taken on is verified.
  Future<WalkPost> upload(String path, DateTime capturedAt, String? caption) async {
    final r = await _api.post(
      '/posts',
      data: FormData.fromMap({
        'image': await MultipartFile.fromFile(path, filename: 'walk.jpg'),
        'captured_at': capturedAt.toUtc().toIso8601String(),
        if (caption != null && caption.trim().isNotEmpty) 'caption': caption.trim(),
      }),
      options: Options(contentType: 'multipart/form-data', sendTimeout: const Duration(seconds: 90)),
    );
    return WalkPost.fromJson(r['data'] as Map<String, dynamic>);
  }

  Future<int> like(String id) async => _i(((await _api.post('/posts/$id/like'))['data'] as Map)['likes_count']);

  Future<int> unlike(String id) async => _i(((await _api.delete('/posts/$id/like'))['data'] as Map)['likes_count']);

  Future<void> views(List<String> ids) => _api.post('/posts/views', data: {'ids': ids});

  Future<void> report(String id, String reason) => _api.post('/posts/$id/report', data: {'reason': reason});

  Future<void> delete(String id) => _api.delete('/posts/$id');
}

final postsRepositoryProvider = Provider<PostsRepository>((ref) => PostsRepository(ref.watch(apiClientProvider)));
