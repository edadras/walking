import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/dates.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/state_views.dart';
import '../../activity/application/activity_providers.dart';
import '../data/notification_repository.dart';

IconData _icon(InboxItem n) => switch (n.data['type']) {
      'referral' => Icons.group_add_outlined,
      'achievement' => Icons.emoji_events_outlined,
      'goal' => Icons.flag_outlined,
      'streak' => Icons.local_fire_department_outlined,
      'challenge' => Icons.flag_circle_outlined,
      'visit' => Icons.storefront_outlined,
      'order' => Icons.receipt_long_outlined,
      _ => n.category == 'reward_received' ? Icons.toll_rounded : Icons.campaign_outlined,
    };

/// Where tapping a notification leads (server data never carries raw routes).
String? _route(InboxItem n) => switch (n.data['type']) {
      'referral' => '/referral',
      'achievement' => '/achievements',
      'goal' || 'streak' => '/rewards',
      'challenge' when n.data['id'] is String => '/challenges/${n.data['id']}',
      'visit' => '/coupons',
      'order' when n.data['id'] is String => '/orders/${n.data['id']}',
      _ => null,
    };

/// In-app inbox. Opening marks everything read after the first frame.
class InboxPage extends ConsumerStatefulWidget {
  const InboxPage({super.key});

  @override
  ConsumerState<InboxPage> createState() => _InboxPageState();
}

class _InboxPageState extends ConsumerState<InboxPage> {
  Future<void> _markRead() async {
    try {
      await markAllRead(ref);
      ref.invalidate(inboxProvider);
      ref.invalidate(homeProvider);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    }
  }

  void _open(InboxItem n) {
    final route = _route(n);
    if (route == '/rewards') {
      context.go(route!);
    } else if (route != null) {
      context.push(route);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final inbox = ref.watch(inboxProvider);
    final hasUnread = inbox.value?.any((n) => !n.read) ?? false;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.inboxTitle),
        actions: [if (hasUnread) TextButton(onPressed: _markRead, child: Text(l.inboxMarkRead))],
      ),
      body: RefreshIndicator(
        color: p.green,
        onRefresh: () async => ref.invalidate(inboxProvider),
        child: AsyncView(
          value: inbox,
          onRetry: () => ref.invalidate(inboxProvider),
          data: (items) => items.isEmpty
              ? ListView(children: [EmptyView(title: l.inboxEmpty)])
              : ListView.separated(
                  padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.sm),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1, indent: AppSpacing.gutter, endIndent: AppSpacing.gutter),
                  itemBuilder: (_, i) {
                    final n = items[i];
                    return ListTile(
                      onTap: () => _open(n),
                      leading: CircleAvatar(
                        backgroundColor: n.read ? p.surfaceSunken : p.greenSoft,
                        child: Icon(_icon(n), color: n.read ? p.inkSubtle : p.greenStrong, size: 20),
                      ),
                      title: Text(n.title, style: context.text.titleSmall?.copyWith(fontWeight: n.read ? FontWeight.w500 : FontWeight.w700)),
                      subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        if (n.body.isNotEmpty) Text(n.body, style: context.text.bodySmall),
                        Text(FaDate.relative(n.createdAt), style: context.text.labelSmall),
                      ]),
                      trailing: n.read ? null : Container(width: 8, height: 8, decoration: BoxDecoration(color: p.green, shape: BoxShape.circle)),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
