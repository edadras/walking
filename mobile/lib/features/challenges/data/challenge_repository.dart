import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'challenge_models.dart';

final challengesProvider = FutureProvider.autoDispose<List<ChallengeItem>>((ref) async {
  final list = (await ref.watch(apiClientProvider).get('/challenges'))['data'] as List;
  return list.map((e) => ChallengeItem.fromJson(e as Map<String, dynamic>)).toList();
});

final challengeProvider = FutureProvider.autoDispose.family<ChallengeItem, String>((ref, id) async {
  final d = (await ref.watch(apiClientProvider).get('/challenges/$id'))['data'] as Map<String, dynamic>;
  return ChallengeItem.fromJson(d);
});

Future<ChallengeItem> joinChallenge(ApiClient api, String id) async =>
    ChallengeItem.fromJson((await api.post('/challenges/$id/join', options: Req.signed()))['data'] as Map<String, dynamic>);
