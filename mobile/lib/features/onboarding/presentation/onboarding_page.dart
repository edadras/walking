import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/trail.dart';
import '../../auth/application/session_controller.dart';

/// Five short slides. Permissions are NOT requested here; each one is asked
/// later, in context, with its own explanation.
class OnboardingPage extends ConsumerStatefulWidget {
  const OnboardingPage({super.key});

  @override
  ConsumerState<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends ConsumerState<OnboardingPage> {
  final _controller = PageController();
  int _index = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  List<(String, String, _ArtKind)> _slides(AppLocalizations l) => [
        (l.onboardingMoveTitle, l.onboardingMoveBody, _ArtKind.move),
        (l.onboardingEarnTitle, l.onboardingEarnBody, _ArtKind.earn),
        (l.onboardingRewardTitle, l.onboardingRewardBody, _ArtKind.reward),
        (l.onboardingStoreTitle, l.onboardingStoreBody, _ArtKind.store),
        (l.onboardingNearbyTitle, l.onboardingNearbyBody, _ArtKind.nearby),
      ];

  void _finish() => ref.read(sessionProvider.notifier).completeOnboarding();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final slides = _slides(l);
    final last = _index == slides.length - 1;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: Padding(
                padding: const EdgeInsetsDirectional.all(AppSpacing.sm),
                child: AnimatedOpacity(
                  opacity: last ? 0 : 1,
                  duration: AppMotion.base,
                  child: TextButton(onPressed: last ? null : _finish, child: Text(l.commonSkip, style: TextStyle(color: p.inkMuted))),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: slides.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) {
                  final (title, body, art) = slides[i];
                  return Padding(
                    padding: const EdgeInsetsDirectional.symmetric(horizontal: AppSpacing.x3),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _OnboardingArt(kind: art),
                        const SizedBox(height: AppSpacing.x4),
                        Text(title, style: context.text.headlineMedium),
                        const SizedBox(height: AppSpacing.md),
                        Text(body, style: context.text.bodyLarge?.copyWith(color: p.inkMuted)),
                      ],
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.x3, 0, AppSpacing.x3, AppSpacing.xxl),
              child: Row(
                children: [
                  _Dots(count: slides.length, index: _index),
                  const Spacer(),
                  AppButton(
                    expand: false,
                    label: last ? l.onboardingStart : l.commonContinue,
                    onPressed: last ? _finish : () => _controller.nextPage(duration: AppMotion.slow, curve: AppMotion.enter),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Dots extends StatelessWidget {
  const _Dots({required this.count, required this.index});

  final int count;
  final int index;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Row(
      children: List.generate(count, (i) {
        final on = i <= index;
        return AnimatedContainer(
          duration: AppMotion.base,
          margin: const EdgeInsetsDirectional.only(end: 6),
          width: i == index ? 8 : 6,
          height: i == index ? 8 : 6,
          decoration: BoxDecoration(shape: BoxShape.circle, color: on ? p.green : p.border),
        );
      }),
    );
  }
}

enum _ArtKind { move, earn, reward, store, nearby }

/// Onboarding illustrations drawn from the trail motif — no stock art.
class _OnboardingArt extends StatelessWidget {
  const _OnboardingArt({required this.kind});

  final _ArtKind kind;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    Widget endMark(IconData icon, Color bg, Color fg) => Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(color: bg, borderRadius: AppRadius.mdAll),
          child: Icon(icon, color: fg, size: 22),
        );

    final (progress, mark) = switch (kind) {
      _ArtKind.move => (0.35, endMark(Icons.directions_walk_rounded, p.greenSoft, p.green)),
      _ArtKind.earn => (0.7, endMark(Icons.toll_rounded, p.goldSoft, p.goldInk)),
      _ArtKind.reward => (1.0, endMark(Icons.emoji_events_outlined, p.goldSoft, p.goldInk)),
      _ArtKind.store => (0.85, endMark(Icons.shopping_bag_outlined, p.greenSoft, p.green)),
      _ArtKind.nearby => (0.55, endMark(Icons.place_outlined, p.greenSoft, p.green)),
    };

    return SizedBox(
      height: 180,
      child: Stack(
        children: [
          Positioned.fill(
            top: 40,
            bottom: 40,
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: const Duration(milliseconds: 800),
              curve: AppMotion.enter,
              builder: (_, v, _) => ExcludeSemantics(
                child: CustomPaint(painter: TrailPainter(progress: v, filled: p.green, empty: p.border, accent: p.gold, dots: 16, dotRadius: 4)),
              ),
            ),
          ),
          PositionedDirectional(end: 0, top: 0, child: mark),
        ],
      ),
    );
  }
}
