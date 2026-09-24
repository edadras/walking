import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/support/data/support_repository.dart';
import 'package:gamyar/features/support/presentation/support_pages.dart';

import '../../helpers/test_app.dart';

Map<String, dynamic> ticketJson({bool canReply = true, List<Map<String, dynamic>>? messages}) => {
      'id': 't1', 'number': '#AB12CD', 'category': 'points', 'category_label': 'امتیاز و کیف پول', 'subject': 'امتیازم اضافه نشد',
      'status': canReply ? 'awaiting_user' : 'closed', 'status_label': canReply ? 'در انتظار پاسخ شما' : 'بسته‌شده', 'can_reply': canReply,
      'last_message_at': '2026-09-24T08:00:00Z',
      'messages': messages ??
          [
            {'from': 'me', 'body': 'دیروز امتیاز نگرفتم', 'at': '2026-09-24T07:00:00Z'},
            {'from': 'support', 'body': 'پس از بررسی آزاد می‌شود', 'at': '2026-09-24T08:00:00Z'},
          ],
    };

class FakeSupport extends SupportRepository {
  FakeSupport({this.canReply = true}) : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  final bool canReply;
  final replies = <String>[];
  final opened = <String>[];

  @override
  Future<Ticket> ticket(String id) async => Ticket.fromJson(ticketJson(canReply: canReply));

  @override
  Future<Ticket> reply(String id, String body) async {
    replies.add(body);
    return Ticket.fromJson(ticketJson());
  }

  @override
  Future<List<({String id, String label})>> categories() async => [(id: 'points', label: 'امتیاز و کیف پول'), (id: 'other', label: 'سایر')];

  @override
  Future<Ticket> open({required String category, required String subject, required String body}) async {
    opened.add('$category|$subject');
    return Ticket.fromJson(ticketJson());
  }
}

void main() {
  testWidgets('conversation shows both sides and sends a reply', (tester) async {
    final repo = FakeSupport();
    await tester.pumpWidget(testApp(const TicketPage(id: 't1'), overrides: [supportRepositoryProvider.overrideWithValue(repo)]));
    await tester.pumpAndSettle();

    expect(find.text('دیروز امتیاز نگرفتم'), findsOneWidget);
    expect(find.text('پشتیبانی گام‌یار'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'ممنون');
    await tester.tap(find.byIcon(Icons.send_rounded));
    await tester.pumpAndSettle();
    expect(repo.replies, ['ممنون']);
  });

  testWidgets('closed tickets cannot be replied to', (tester) async {
    await tester.pumpWidget(testApp(const TicketPage(id: 't1'), overrides: [supportRepositoryProvider.overrideWithValue(FakeSupport(canReply: false))]));
    await tester.pumpAndSettle();
    expect(find.text('این درخواست بسته شده است.'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
  });

  testWidgets('new ticket validates before sending', (tester) async {
    final repo = FakeSupport();
    await tester.pumpWidget(testApp(const NewTicketPage(), overrides: [supportRepositoryProvider.overrideWithValue(repo)]));
    await tester.pumpAndSettle();

    await tester.tap(find.text('ارسال'));
    await tester.pumpAndSettle();
    expect(find.text('این فیلد لازم است.'), findsWidgets);
    expect(repo.opened, isEmpty);

    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('سایر').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'عنوان'), 'سوال درباره سطح');
    await tester.enterText(find.widgetWithText(TextFormField, 'شرح مشکل'), 'سطح من بعد از چالش بالا نرفت.');
    await tester.tap(find.text('ارسال'));
    await tester.pumpAndSettle();
    expect(repo.opened, ['other|سوال درباره سطح']);
  });
}
