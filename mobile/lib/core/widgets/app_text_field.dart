import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../format/numbers.dart';
import '../theme/app_palette.dart';
import '../theme/tokens.dart';

/// Label above the field, Persian error below it.
class AppTextField extends StatelessWidget {
  const AppTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.errorText,
    this.keyboardType,
    this.inputFormatters,
    this.textDirection,
    this.autofocus = false,
    this.onChanged,
    this.onSubmitted,
    this.maxLength,
    this.suffix,
    this.textAlign = TextAlign.start,
    this.style,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final String? errorText;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final TextDirection? textDirection;
  final bool autofocus;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final int? maxLength;
  final Widget? suffix;
  final TextAlign textAlign;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: context.text.labelMedium?.copyWith(color: context.palette.inkMuted)),
        const SizedBox(height: AppSpacing.sm),
        TextField(
          controller: controller,
          autofocus: autofocus,
          keyboardType: keyboardType,
          inputFormatters: inputFormatters,
          textDirection: textDirection,
          textAlign: textAlign,
          onChanged: onChanged,
          onSubmitted: onSubmitted,
          maxLength: maxLength,
          style: style ?? context.text.bodyLarge,
          decoration: InputDecoration(hintText: hint, errorText: errorText, counterText: '', suffixIcon: suffix),
        ),
      ],
    );
  }
}

/// Accepts Persian/Arabic/Latin digits and normalises to Latin while typing.
class DigitsOnlyFormatter extends TextInputFormatter {
  const DigitsOnlyFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = Fa.toLatin(newValue.text).replaceAll(RegExp(r'[^0-9]'), '');
    return TextEditingValue(text: digits, selection: TextSelection.collapsed(offset: digits.length));
  }
}
