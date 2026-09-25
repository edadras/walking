import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class FriendCard {
  const FriendCard({required this.userId, required this.name, required this.level, this.avatarUrl, this.weekSteps = 0, this.isMe = false, this.friendshipId});

  factory FriendCard.fromJson(Map<String, dynamic> j) => FriendCard(
        userId: j['user_id'] as String,
        name: j['name'] as String,
        avatarUrl: j['avatar_url'] as String?,
        level: _i(j['level']) == 0 ? 1 : _i(j['level']),
        weekSteps: _i(j['week_steps'] ?? j['steps']),
        isMe: j['is_me'] == true,
        friendshipId: (j['friendship_id'] ?? j['id']) as int?,
      );

  final String userId;
  final String name;
  final String? avatarUrl;
  final int level;

  /// Verified steps this week (ranking) or during a race (standings).
  final int weekSteps;
  final bool isMe;
  final int? friendshipId;
}

class FriendsOverview {
  const FriendsOverview({required this.code, required this.ranking, required this.incoming, required this.outgoing});

  factory FriendsOverview.fromJson(Map<String, dynamic> j) {
    List<FriendCard> list(String k) => ((j[k] as List?) ?? const []).map((e) => FriendCard.fromJson(e as Map<String, dynamic>)).toList();
    return FriendsOverview(code: j['code'] as String, ranking: list('ranking'), incoming: list('incoming'), outgoing: list('outgoing'));
  }

  final String code;
  final List<FriendCard> ranking;
  final List<FriendCard> incoming;
  final List<FriendCard> outgoing;

  List<FriendCard> get friends => ranking.where((f) => !f.isMe).toList();
}

class FriendRace {
  const FriendRace({
    required this.id,
    required this.title,
    required this.creator,
    required this.startsOn,
    required this.endsOn,
    required this.status,
    required this.myStatus,
    required this.members,
    this.standings = const [],
    this.invited = const [],
  });

  factory FriendRace.fromJson(Map<String, dynamic> j) => FriendRace(
        id: j['id'] as String,
        title: j['title'] as String,
        creator: j['creator'] as String? ?? '',
        startsOn: DateTime.parse(j['starts_on'] as String),
        endsOn: DateTime.parse(j['ends_on'] as String),
        status: j['status'] as String,
        myStatus: j['my_status'] as String,
        members: _i(j['members']),
        standings: ((j['standings'] as List?) ?? const []).map((e) => FriendCard.fromJson(e as Map<String, dynamic>)).toList(),
        invited: ((j['invited'] as List?) ?? const []).cast<String>(),
      );

  final String id;
  final String title;
  final String creator;
  final DateTime startsOn;
  final DateTime endsOn;
  final String status; // upcoming | running | finished
  final String myStatus; // invited | joined | left
  final int members;
  final List<FriendCard> standings;
  final List<String> invited;
}

final friendsProvider = FutureProvider.autoDispose<FriendsOverview>(
  (ref) async => FriendsOverview.fromJson((await ref.watch(apiClientProvider).get('/friends'))['data'] as Map<String, dynamic>),
);

final friendRacesProvider = FutureProvider.autoDispose<List<FriendRace>>((ref) async {
  final list = (await ref.watch(apiClientProvider).get('/friend-challenges'))['data'] as List;
  return list.map((e) => FriendRace.fromJson(e as Map<String, dynamic>)).toList();
});

final friendRaceProvider = FutureProvider.autoDispose.family<FriendRace, String>(
  (ref, id) async => FriendRace.fromJson((await ref.watch(apiClientProvider).get('/friend-challenges/$id'))['data'] as Map<String, dynamic>),
);

class SocialRepository {
  SocialRepository(this._api);

  final ApiClient _api;

  Future<void> addFriend(String code) => _api.post('/friends', data: {'code': code.trim()});
  Future<void> accept(int friendshipId) => _api.post('/friends/$friendshipId/accept');
  Future<void> remove(int friendshipId) => _api.delete('/friends/$friendshipId');

  Future<FriendRace> createRace({required String title, required int days, required List<String> friendIds}) async =>
      FriendRace.fromJson((await _api.post('/friend-challenges', data: {'title': title, 'days': days, 'friend_ids': friendIds}))['data'] as Map<String, dynamic>);

  Future<void> join(String id) => _api.post('/friend-challenges/$id/join');
  Future<void> leave(String id) => _api.post('/friend-challenges/$id/leave');
}

final socialRepositoryProvider = Provider<SocialRepository>((ref) => SocialRepository(ref.watch(apiClientProvider)));
