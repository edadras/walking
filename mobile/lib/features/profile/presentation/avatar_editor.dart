import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/permissions/permission_primer.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/net_image.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../data/profile_repository.dart';

enum AvatarSource { gallery, camera }

/// Returns a local file path, or null when the user backs out. Downscaled on-device so
/// uploads stay small on mobile data; the server re-encodes regardless.
typedef AvatarPicker = Future<String?> Function(AvatarSource source);

final avatarPickerProvider = Provider<AvatarPicker>((ref) => (source) async {
      final file = await ImagePicker().pickImage(
        source: source == AvatarSource.camera ? ImageSource.camera : ImageSource.gallery,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 88,
        preferredCameraDevice: CameraDevice.front,
      );
      return file?.path;
    });

/// Large avatar with a camera badge; tapping offers gallery, camera and removal.
class AvatarEditor extends ConsumerStatefulWidget {
  const AvatarEditor({super.key});

  @override
  ConsumerState<AvatarEditor> createState() => _AvatarEditorState();
}

class _AvatarEditorState extends ConsumerState<AvatarEditor> {
  bool _busy = false;

  Future<void> _choose() async {
    final l = context.l10n;
    final hasAvatar = ref.read(meProvider).avatarUrl != null;
    final action = await showModalBottomSheet<Object>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_library_outlined), title: Text(l.avatarFromGallery), onTap: () => Navigator.pop(c, AvatarSource.gallery)),
          ListTile(leading: const Icon(Icons.photo_camera_outlined), title: Text(l.avatarFromCamera), onTap: () => Navigator.pop(c, AvatarSource.camera)),
          if (hasAvatar)
            ListTile(
              leading: Icon(Icons.delete_outline_rounded, color: c.palette.danger),
              title: Text(l.avatarRemove, style: TextStyle(color: c.palette.danger)),
              onTap: () => Navigator.pop(c, 'remove'),
            ),
          const SizedBox(height: AppSpacing.sm),
        ]),
      ),
    );
    if (action == null || !mounted) return;

    final repo = ref.read(profileRepositoryProvider);
    try {
      if (action == 'remove') {
        setState(() => _busy = true);
        ref.read(sessionProvider.notifier).updateMe(await repo.removeAvatar());
        if (mounted) showAppSnack(context, l.avatarRemoved);
        return;
      }
      final source = action as AvatarSource;
      // The manifest declares CAMERA (QR scanning), so the capture intent needs it granted.
      if (source == AvatarSource.camera && !await PermissionPrimer.ensure(context, AppPermission.camera)) return;
      final path = await ref.read(avatarPickerProvider)(source);
      if (path == null || !mounted) return;
      setState(() => _busy = true);
      ref.read(sessionProvider.notifier).updateMe(await repo.uploadAvatar(path));
      if (mounted) showAppSnack(context, l.avatarUpdated);
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    final me = ref.watch(meProvider);
    return Center(
      child: Semantics(
        button: true,
        label: l.avatarChange,
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: _busy ? null : _choose,
          child: Stack(clipBehavior: Clip.none, children: [
            NetAvatar(
              url: me.avatarUrl,
              radius: 48,
              backgroundColor: p.greenSoft,
              child: Text(me.publicName.isEmpty ? '؟' : me.publicName.characters.first, style: context.text.headlineMedium?.copyWith(color: p.greenStrong)),
            ),
            if (_busy)
              Positioned.fill(
                child: DecoratedBox(
                  decoration: const BoxDecoration(color: Color(0x66000000), shape: BoxShape.circle),
                  child: Center(child: SizedBox.square(dimension: 28, child: CircularProgressIndicator(strokeWidth: 3, color: p.surface))),
                ),
              ),
            PositionedDirectional(
              bottom: 0,
              end: 0,
              child: Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(color: p.green, shape: BoxShape.circle, border: Border.all(color: p.surface, width: 2)),
                child: Icon(Icons.photo_camera_rounded, size: 18, color: p.surface),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
