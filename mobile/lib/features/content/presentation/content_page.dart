import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../../../core/localization/l10n.dart';
import '../../profile/data/profile_repository.dart';

/// Renders admin-managed CMS pages. Supports the small Markdown subset the
/// admin editor produces: headings (##), bullet lists (-) and **bold**.
class ContentPage extends ConsumerWidget {
  const ContentPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(cmsPageProvider(slug));
    return Scaffold(
      appBar: AppBar(title: Text(async.value?.title ?? '')),
      body: AsyncView(
        value: async,
        onRetry: () => ref.invalidate(cmsPageProvider(slug)),
        data: (page) => ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: MarkdownLite.render(context, page.body),
        ),
      ),
    );
  }
}

class FaqPage extends ConsumerWidget {
  const FaqPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(faqsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.profileFaq)),
      body: AsyncView(
        value: async,
        onRetry: () => ref.invalidate(faqsProvider),
        data: (faqs) => ListView.separated(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          itemCount: faqs.length,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, i) => AppCard(
            padding: EdgeInsets.zero,
            child: Theme(
              data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
              child: ExpansionTile(
                title: Text(faqs[i].question, style: context.text.titleSmall),
                childrenPadding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.lg),
                expandedAlignment: AlignmentDirectional.centerStart,
                children: [Text(faqs[i].answer, style: context.text.bodyMedium?.copyWith(color: context.palette.inkMuted))],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

abstract final class MarkdownLite {
  static List<Widget> render(BuildContext context, String source) {
    final t = context.text;
    final widgets = <Widget>[];
    for (final raw in source.split('\n')) {
      final line = raw.trimRight();
      if (line.trim().isEmpty) {
        widgets.add(const SizedBox(height: AppSpacing.sm));
      } else if (line.startsWith('## ') || line.startsWith('# ')) {
        widgets.add(Padding(
          padding: const EdgeInsetsDirectional.only(top: AppSpacing.lg, bottom: AppSpacing.sm),
          child: Text(line.replaceFirst(RegExp(r'^#+\s'), ''), style: t.titleMedium),
        ));
      } else if (line.startsWith('- ')) {
        widgets.add(Padding(
          padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.xs),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Padding(
              padding: const EdgeInsetsDirectional.only(top: 10, end: AppSpacing.sm),
              child: Container(width: 5, height: 5, decoration: BoxDecoration(color: context.palette.green, shape: BoxShape.circle)),
            ),
            Expanded(child: Text.rich(_inline(line.substring(2), t.bodyLarge!))),
          ]),
        ));
      } else {
        widgets.add(Text.rich(_inline(line, t.bodyLarge!)));
      }
    }
    return widgets;
  }

  static TextSpan _inline(String text, TextStyle base) {
    final parts = text.split('**');
    return TextSpan(style: base, children: [
      for (var i = 0; i < parts.length; i++) TextSpan(text: parts[i], style: i.isOdd ? const TextStyle(fontWeight: FontWeight.w700) : null),
    ]);
  }
}
