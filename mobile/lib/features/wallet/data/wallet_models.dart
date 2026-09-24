int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class WalletBalance {
  const WalletBalance({
    required this.available,
    required this.pending,
    required this.rialPerPoint,
    required this.rialValue,
    required this.pendingRialValue,
    required this.lifetimeEarned,
    required this.lifetimeSpent,
    required this.nextReleaseAt,
  });

  factory WalletBalance.fromJson(Map<String, dynamic> j) => WalletBalance(
        available: _i(j['available']),
        pending: _i(j['pending']),
        rialPerPoint: _i(j['rial_per_point']),
        rialValue: _i(j['rial_value']),
        pendingRialValue: _i(j['pending_rial_value']),
        lifetimeEarned: _i(j['lifetime_earned']),
        lifetimeSpent: _i(j['lifetime_spent']),
        nextReleaseAt: DateTime.tryParse(j['next_release_at'] as String? ?? ''),
      );

  final int available;
  final int pending;
  final int rialPerPoint;
  final int rialValue;
  final int pendingRialValue;
  final int lifetimeEarned;
  final int lifetimeSpent;
  final DateTime? nextReleaseAt;
}

class PointTx {
  const PointTx({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.amount,
    required this.status,
    required this.statusLabel,
    required this.description,
    required this.rialValue,
    required this.createdAt,
    required this.availableAt,
  });

  factory PointTx.fromJson(Map<String, dynamic> j) => PointTx(
        id: j['id'] as String,
        type: j['type'] as String,
        typeLabel: j['type_label'] as String? ?? '',
        amount: _i(j['amount']),
        status: j['status'] as String,
        statusLabel: j['status_label'] as String? ?? '',
        description: j['description'] as String? ?? '',
        rialValue: _i(j['rial_value']),
        createdAt: DateTime.parse(j['created_at'] as String),
        availableAt: DateTime.tryParse(j['available_at'] as String? ?? ''),
      );

  final String id;
  final String type;
  final String typeLabel;
  final int amount;
  final String status;
  final String statusLabel;
  final String description;
  final int rialValue;
  final DateTime createdAt;
  final DateTime? availableAt;

  bool get isPending => status == 'pending';
  bool get isReversed => status == 'reversed';
}

class TxPage {
  const TxPage(this.items, this.nextCursor);

  final List<PointTx> items;
  final String? nextCursor;
}
