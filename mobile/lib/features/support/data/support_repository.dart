import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

class Ticket {
  const Ticket({
    required this.id,
    required this.number,
    required this.category,
    required this.categoryLabel,
    required this.subject,
    required this.status,
    required this.statusLabel,
    required this.canReply,
    required this.lastMessageAt,
    this.messages = const [],
  });

  factory Ticket.fromJson(Map<String, dynamic> j) => Ticket(
        id: j['id'] as String,
        number: j['number'] as String,
        category: j['category'] as String,
        categoryLabel: j['category_label'] as String? ?? '',
        subject: j['subject'] as String,
        status: j['status'] as String,
        statusLabel: j['status_label'] as String? ?? '',
        canReply: j['can_reply'] == true,
        lastMessageAt: DateTime.parse(j['last_message_at'] as String),
        messages: ((j['messages'] as List?) ?? const [])
            .map((e) => e as Map<String, dynamic>)
            .map((e) => (fromMe: e['from'] == 'me', body: e['body'] as String, at: DateTime.parse(e['at'] as String)))
            .toList(),
      );

  final String id;
  final String number;
  final String category;
  final String categoryLabel;
  final String subject;
  final String status;
  final String statusLabel;
  final bool canReply;
  final DateTime lastMessageAt;
  final List<({bool fromMe, String body, DateTime at})> messages;

  bool get awaitingMe => status == 'awaiting_user';
}

class SupportRepository {
  SupportRepository(this._api);

  final ApiClient _api;

  Future<List<({String id, String label})>> categories() async => ((await _api.get('/support/categories'))['data'] as List)
      .map((e) => e as Map<String, dynamic>)
      .map((e) => (id: e['id'] as String, label: e['label'] as String))
      .toList();

  Future<List<Ticket>> tickets() async => ((await _api.get('/support/tickets'))['data'] as List).map((e) => Ticket.fromJson(e as Map<String, dynamic>)).toList();

  Future<Ticket> ticket(String id) async => Ticket.fromJson((await _api.get('/support/tickets/$id'))['data'] as Map<String, dynamic>);

  Future<Ticket> open({required String category, required String subject, required String body}) async =>
      Ticket.fromJson((await _api.post('/support/tickets', data: {'category': category, 'subject': subject, 'body': body}))['data'] as Map<String, dynamic>);

  Future<Ticket> reply(String id, String body) async => Ticket.fromJson((await _api.post('/support/tickets/$id/messages', data: {'body': body}))['data'] as Map<String, dynamic>);

  Future<Ticket> close(String id) async => Ticket.fromJson((await _api.post('/support/tickets/$id/close'))['data'] as Map<String, dynamic>);
}

final supportRepositoryProvider = Provider<SupportRepository>((ref) => SupportRepository(ref.watch(apiClientProvider)));

final ticketsProvider = FutureProvider.autoDispose<List<Ticket>>((ref) => ref.watch(supportRepositoryProvider).tickets());

final ticketProvider = FutureProvider.autoDispose.family<Ticket, String>((ref, id) => ref.watch(supportRepositoryProvider).ticket(id));

final supportCategoriesProvider = FutureProvider.autoDispose<List<({String id, String label})>>((ref) => ref.watch(supportRepositoryProvider).categories());
