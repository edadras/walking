import 'package:flutter/material.dart';

import '../../../core/format/numbers.dart';
import '../../../core/localization/l10n.dart';

String distanceLabel(BuildContext context, int? metres) {
  if (metres == null) return '';
  final l = context.l10n;
  return metres < 1000 ? l.distanceM(Fa.number(metres)) : l.distanceKm(Fa.decimal(metres / 1000));
}

String reasonLabel(BuildContext context, String? reason) {
  final l = context.l10n;
  return switch (reason) {
    'limit_reached' => l.reason_limit_reached,
    'cooldown' => l.reason_cooldown,
    'campaign_exhausted' => l.reason_campaign_exhausted,
    'campaign_ended' => l.reason_campaign_ended,
    'teleport' => l.reason_teleport,
    'mock_location' => l.reason_mock_location,
    _ => l.reason_other,
  };
}
