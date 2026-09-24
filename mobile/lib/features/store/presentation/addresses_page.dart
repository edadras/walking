import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_palette.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/state_views.dart';
import '../data/store_models.dart';
import '../data/store_repository.dart';

class AddressesPage extends ConsumerWidget {
  const AddressesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.addressesTitle)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await context.push('/addresses/new');
          ref.invalidate(addressesProvider);
        },
        icon: const Icon(Icons.add),
        label: Text(l.addressNew),
      ),
      body: AsyncView(
        value: ref.watch(addressesProvider),
        onRetry: () => ref.invalidate(addressesProvider),
        data: (list) => list.isEmpty
            ? EmptyView(title: l.addressesEmpty)
            : ListView(
                padding: const EdgeInsetsDirectional.fromSTEB(AppSpacing.gutter, AppSpacing.gutter, AppSpacing.gutter, 96),
                children: [
                  for (final a in list)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.sm),
                      child: AppCard(
                        onTap: () async {
                          await context.push('/addresses/edit', extra: a);
                          ref.invalidate(addressesProvider);
                        },
                        child: Row(children: [
                          Icon(a.isDefault ? Icons.home_rounded : Icons.place_outlined, color: a.isDefault ? context.palette.green : context.palette.inkSubtle),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(a.title ?? a.recipient, style: context.text.titleSmall),
                              Text(a.oneLine, style: context.text.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                              Text('${a.recipient} · ${Fa.digits(a.phone)}', style: context.text.labelSmall),
                            ]),
                          ),
                        ]),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}

class AddressFormPage extends ConsumerStatefulWidget {
  const AddressFormPage({super.key, this.address});

  final Address? address;

  @override
  ConsumerState<AddressFormPage> createState() => _AddressFormPageState();
}

class _AddressFormPageState extends ConsumerState<AddressFormPage> {
  final _form = GlobalKey<FormState>();
  late final _title = TextEditingController(text: widget.address?.title);
  late final _recipient = TextEditingController(text: widget.address?.recipient);
  late final _phone = TextEditingController(text: widget.address?.phone);
  late final _province = TextEditingController(text: widget.address?.province);
  late final _city = TextEditingController(text: widget.address?.city);
  late final _line = TextEditingController(text: widget.address?.line);
  late final _postal = TextEditingController(text: widget.address?.postalCode);
  late bool _default = widget.address?.isDefault ?? false;
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_title, _recipient, _phone, _province, _city, _line, _postal]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref.read(storeRepositoryProvider).saveAddress(Address(
            id: widget.address?.id ?? '',
            title: _title.text.trim().isEmpty ? null : _title.text.trim(),
            recipient: _recipient.text.trim(),
            phone: Fa.toLatin(_phone.text.trim()),
            province: _province.text.trim(),
            city: _city.text.trim(),
            line: _line.text.trim(),
            postalCode: Fa.toLatin(_postal.text.trim()),
            isDefault: _default,
          ));
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    await ref.read(storeRepositoryProvider).deleteAddress(widget.address!.id);
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    String? required(String? v) => (v == null || v.trim().isEmpty) ? l.fieldRequired : null;
    Widget field(TextEditingController c, String label, {String? Function(String?)? validator, TextInputType? keyboard, int maxLines = 1, TextDirection? dir}) => Padding(
          padding: const EdgeInsetsDirectional.only(bottom: AppSpacing.md),
          child: TextFormField(
            controller: c,
            decoration: InputDecoration(labelText: label),
            validator: validator,
            keyboardType: keyboard,
            maxLines: maxLines,
            textDirection: dir,
            inputFormatters: keyboard == TextInputType.phone || keyboard == TextInputType.number ? [LengthLimitingTextInputFormatter(11)] : null,
          ),
        );

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.address == null ? l.addressNew : l.addressEdit),
        actions: [if (widget.address != null) IconButton(tooltip: l.addressDelete, icon: const Icon(Icons.delete_outline), onPressed: _delete)],
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsetsDirectional.all(AppSpacing.gutter),
          children: [
            field(_title, l.addressTitleField),
            field(_recipient, l.addressRecipient, validator: required),
            field(_phone, l.addressPhone,
                keyboard: TextInputType.phone,
                dir: TextDirection.ltr,
                validator: (v) => RegExp(r'^0\d{10}$').hasMatch(Fa.toLatin(v?.trim() ?? '')) ? null : l.addressInvalidPhone),
            field(_province, l.addressProvince, validator: required),
            field(_city, l.addressCity, validator: required),
            field(_line, l.addressLine, validator: required, maxLines: 3),
            field(_postal, l.addressPostalCode,
                keyboard: TextInputType.number,
                dir: TextDirection.ltr,
                validator: (v) => RegExp(r'^\d{10}$').hasMatch(Fa.toLatin(v?.trim() ?? '')) ? null : l.addressInvalidPostal),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(l.addressDefault), value: _default, onChanged: (v) => setState(() => _default = v)),
            const SizedBox(height: AppSpacing.lg),
            AppButton(label: l.commonSave, loading: _busy, onPressed: _save),
          ],
        ),
      ),
    );
  }
}
