import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';

class InboxItem {
  const InboxItem({required this.id, required this.category, required this.title, required this.body, required this.read, required this.createdAt, required this.data});

  factory InboxItem.fromJson(Map<String, dynamic> j) => InboxItem(
        id: j['id'] as String,
        category: j['category'] as String? ?? '',
        title: j['title'] as String? ?? '',
        body: j['body'] as String? ?? '',
        read: j['read'] == true,
        createdAt: DateTime.parse(j['created_at'] as String),
        data: (j['data'] as Map?)?.cast<String, dynamic>() ?? const {},
      );

  final String id;
  final String category;
  final String title;
  final String body;
  final bool read;
  final DateTime createdAt;
  final Map<String, dynamic> data;
}

final inboxProvider = FutureProvider.autoDispose<List<InboxItem>>((ref) async {
  final r = await ref.watch(apiClientProvider).get('/notifications');
  return (r['data'] as List).map((e) => InboxItem.fromJson(e as Map<String, dynamic>)).toList();
});

Future<void> markAllRead(WidgetRef ref) async {
  await ref.read(apiClientProvider).post('/notifications/read');
}
