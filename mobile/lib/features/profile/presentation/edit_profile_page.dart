import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/application/session_controller.dart';
import '../data/profile_repository.dart';

class EditProfilePage extends ConsumerStatefulWidget {
  const EditProfilePage({super.key});

  @override
  ConsumerState<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends ConsumerState<EditProfilePage> {
  late final me = ref.read(meProvider);
  late final _name = TextEditingController(text: me.displayName ?? '');
  late final _birth = TextEditingController(text: me.birthYear?.toString() ?? '');
  late final _height = TextEditingController(text: me.heightCm?.toString() ?? '');
  late final _weight = TextEditingController(text: me.weightKg?.toString() ?? '');
  late String? _gender = me.gender;
  Map<String, List<String>> _errors = const {};
  bool _saving = false;

  @override
  void dispose() {
    for (final c in [_name, _birth, _height, _weight]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _errors = const {};
    });
    int? n(TextEditingController c) => int.tryParse(Fa.toLatin(c.text.trim()));
    try {
      final updated = await ref.read(profileRepositoryProvider).updateProfile({
        'display_name': _name.text.trim().isEmpty ? null : _name.text.trim(),
        'birth_year': n(_birth),
        'height_cm': n(_height),
        'weight_kg': double.tryParse(Fa.toLatin(_weight.text.trim())),
        'gender': _gender,
      });
      ref.read(sessionProvider.notifier).updateMe(updated);
      if (mounted) {
        showAppSnack(context, context.l10n.commonSaved);
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      setState(() => _errors = e.fields);
      if (e.fields.isEmpty && mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = context.palette;
    String? err(String f) => _errors[f]?.first;
    const gap = SizedBox(height: AppSpacing.xl);

    return Scaffold(
      appBar: AppBar(title: Text(l.editProfileTitle)),
      body: ListView(
        padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
        children: [
          AppTextField(label: l.editDisplayName, hint: l.editDisplayNameHint, controller: _name, maxLength: 30, errorText: err('display_name')),
          gap,
          Row(children: [
            Expanded(
              child: AppTextField(
                label: l.editHeight,
                controller: _height,
                keyboardType: TextInputType.number,
                inputFormatters: const [DigitsOnlyFormatter()],
                maxLength: 3,
                errorText: err('height_cm'),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: AppTextField(
                label: l.editWeight,
                controller: _weight,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                maxLength: 5,
                errorText: err('weight_kg'),
              ),
            ),
          ]),
          gap,
          AppTextField(
            label: l.editBirthYear,
            controller: _birth,
            keyboardType: TextInputType.number,
            inputFormatters: const [DigitsOnlyFormatter()],
            maxLength: 4,
            hint: '1995',
            errorText: err('birth_year'),
          ),
          gap,
          Text(l.editGender, style: context.text.labelMedium),
          const SizedBox(height: AppSpacing.sm),
          SegmentedButton<String?>(
            showSelectedIcon: false,
            segments: [
              ButtonSegment(value: 'female', label: Text(l.editGenderFemale)),
              ButtonSegment(value: 'male', label: Text(l.editGenderMale)),
              ButtonSegment(value: null, label: Text(l.editGenderNone)),
            ],
            selected: {_gender},
            onSelectionChanged: (s) => setState(() => _gender = s.first),
          ),
          const SizedBox(height: AppSpacing.lg),
          Container(
            padding: const EdgeInsetsDirectional.all(AppSpacing.md),
            decoration: BoxDecoration(color: p.surface, borderRadius: AppRadius.smAll),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.lock_outline_rounded, size: 18, color: p.inkMuted),
              const SizedBox(width: AppSpacing.sm),
              Expanded(child: Text(l.editBodyNote, style: context.text.bodySmall)),
            ]),
          ),
          const SizedBox(height: AppSpacing.x3),
          AppButton(label: l.commonSave, onPressed: _save, loading: _saving),
        ],
      ),
    );
  }
}
