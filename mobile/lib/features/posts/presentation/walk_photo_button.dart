import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../application/photo_outbox.dart';

/// Opens the camera (never the gallery: the photo must be taken on this walk).
typedef WalkCamera = Future<String?> Function();

final walkCameraProvider = Provider<WalkCamera>((ref) => () async {
      final file = await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 2048, maxHeight: 2048, imageQuality: 85);
      return file?.path;
    });

/// "Take a photo" during a live walk, with a short optional caption.
class WalkPhotoButton extends ConsumerStatefulWidget {
  const WalkPhotoButton({super.key});

  @override
  ConsumerState<WalkPhotoButton> createState() => _WalkPhotoButtonState();
}

class _WalkPhotoButtonState extends ConsumerState<WalkPhotoButton> {
  int _taken = 0;
  bool _busy = false;

  Future<void> _shoot() async {
    final path = await ref.read(walkCameraProvider)();
    if (path == null || !mounted) return;
    final capturedAt = DateTime.now();
    final caption = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => const _CaptionSheet(),
    );
    if (caption == null || !mounted) return; // dismissed: the photo is discarded
    setState(() => _busy = true);
    try {
      // Kept on the phone first, so a failed upload (no signal) loses nothing.
      await ref.read(photoOutboxProvider.notifier).add(path, capturedAt, caption);
      if (mounted) setState(() => _taken++);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      AppButton.secondary(label: l.walkTakePhoto, icon: Icons.photo_camera_outlined, loading: _busy, onPressed: _shoot),
      if (_taken > 0) ...[
        const SizedBox(height: AppSpacing.xs),
        Text(l.walkPhotosCount(Fa.number(_taken)), style: context.text.labelMedium, textAlign: TextAlign.center),
      ],
    ]);
  }
}

class _CaptionSheet extends StatefulWidget {
  const _CaptionSheet();

  @override
  State<_CaptionSheet> createState() => _CaptionSheetState();
}

class _CaptionSheetState extends State<_CaptionSheet> {
  final _text = TextEditingController();

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, 0, AppSpacing.gutter, MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        TextField(controller: _text, maxLength: 200, maxLines: 2, decoration: InputDecoration(labelText: l.walkPhotoCaption)),
        Text(l.walkPhotoHint, style: context.text.bodySmall?.copyWith(color: context.palette.inkMuted)),
        const SizedBox(height: AppSpacing.md),
        AppButton(label: l.walkPhotoSend, icon: Icons.check_rounded, onPressed: () => Navigator.pop(context, _text.text)),
      ]),
    );
  }
}
