import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;

/// A reason the user can't request a payout yet, worded by the server.
typedef CashoutBlocker = ({String code, String message});

class CashoutLimits {
  const CashoutLimits({required this.min, required this.max, required this.windowMax, required this.windowUsed, required this.windowLeft});

  factory CashoutLimits.fromJson(Map<String, dynamic> j) => CashoutLimits(
        min: _i(j['min']),
        max: _i(j['max']),
        windowMax: _i(j['window_max']),
        windowUsed: _i(j['window_used']),
        windowLeft: _i(j['window_left']),
      );

  final int min;
  final int max;
  final int windowMax;
  final int windowUsed;
  final int windowLeft;
}

class CashoutIdentity {
  const CashoutIdentity({required this.firstName, required this.lastName, required this.nationalCode, required this.birthDate, required this.status, this.rejectionReason});

  factory CashoutIdentity.fromJson(Map<String, dynamic> j) => CashoutIdentity(
        firstName: j['first_name'] as String,
        lastName: j['last_name'] as String,
        nationalCode: j['national_code'] as String,
        birthDate: DateTime.parse(j['birth_date'] as String),
        status: j['status'] as String,
        rejectionReason: j['rejection_reason'] as String?,
      );

  final String firstName;
  final String lastName;

  /// Masked by the server; the full code never comes back.
  final String nationalCode;
  final DateTime birthDate;
  final String status; // pending | verified | rejected
  final String? rejectionReason;

  String get fullName => '$firstName $lastName';
}

class BankAccount {
  const BankAccount({required this.id, required this.iban, required this.bankName, required this.holderName, required this.status, this.rejectionReason});

  factory BankAccount.fromJson(Map<String, dynamic> j) => BankAccount(
        id: j['id'] as String,
        iban: j['iban'] as String,
        bankName: j['bank_name'] as String,
        holderName: j['holder_name'] as String,
        status: j['status'] as String,
        rejectionReason: j['rejection_reason'] as String?,
      );

  final String id;
  final String iban; // masked
  final String bankName;
  final String holderName;
  final String status;
  final String? rejectionReason;

  bool get verified => status == 'verified';
}

class CashoutRequest {
  const CashoutRequest({
    required this.id,
    required this.points,
    required this.amountRial,
    required this.status,
    required this.statusLabel,
    required this.createdAt,
    this.bank,
    this.iban,
    this.bankReference,
    this.rejectionReason,
    this.paidAt,
    this.queuePosition,
  });

  factory CashoutRequest.fromJson(Map<String, dynamic> j) => CashoutRequest(
        id: j['id'] as String,
        points: _i(j['points']),
        amountRial: _i(j['amount_rial']),
        status: j['status'] as String,
        statusLabel: j['status_label'] as String,
        bank: j['bank'] as String?,
        iban: j['iban'] as String?,
        bankReference: j['bank_reference'] as String?,
        rejectionReason: j['rejection_reason'] as String?,
        createdAt: DateTime.parse(j['created_at'] as String),
        paidAt: j['paid_at'] == null ? null : DateTime.parse(j['paid_at'] as String),
        queuePosition: (j['queue_position'] as num?)?.toInt(),
      );

  final String id;
  final int points;
  final int amountRial;
  final String status; // pending | approved | paid | rejected | cancelled
  final String statusLabel;
  final String? bank;
  final String? iban;
  final String? bankReference;
  final String? rejectionReason;
  final DateTime createdAt;
  final DateTime? paidAt;

  /// Place in the review queue while pending (1 = next).
  final int? queuePosition;
}

class CashoutOverview {
  const CashoutOverview({
    required this.enabled,
    required this.phone,
    required this.rialPerPoint,
    required this.availablePoints,
    required this.withdrawablePoints,
    required this.immaturePoints,
    required this.maturityDays,
    required this.limits,
    required this.blockers,
    required this.identity,
    required this.accounts,
    required this.requests,
  });

  factory CashoutOverview.fromJson(Map<String, dynamic> j) => CashoutOverview(
        enabled: j['enabled'] == true,
        phone: j['phone'] as String? ?? '',
        rialPerPoint: _i(j['rial_per_point']),
        availablePoints: _i(j['available_points']),
        withdrawablePoints: _i(j['withdrawable_points'] ?? j['available_points']),
        immaturePoints: _i(j['immature_points']),
        maturityDays: _i(j['maturity_days']),
        limits: CashoutLimits.fromJson(j['limits'] as Map<String, dynamic>),
        blockers: [
          for (final b in (j['blockers'] as List? ?? const [])) (code: (b as Map)['code'] as String, message: b['message'] as String),
        ],
        identity: j['identity'] == null ? null : CashoutIdentity.fromJson(j['identity'] as Map<String, dynamic>),
        accounts: [for (final a in (j['bank_accounts'] as List? ?? const [])) BankAccount.fromJson(a as Map<String, dynamic>)],
        requests: [for (final r in (j['requests'] as List? ?? const [])) CashoutRequest.fromJson(r as Map<String, dynamic>)],
      );

  final bool enabled;
  final String phone;
  final int rialPerPoint;
  final int availablePoints;

  /// Matured points from walking/activity/sponsors: the only part that can become money.
  final int withdrawablePoints;

  /// Eligible points still inside the maturity window.
  final int immaturePoints;
  final int maturityDays;
  final CashoutLimits limits;
  final List<CashoutBlocker> blockers;
  final CashoutIdentity? identity;
  final List<BankAccount> accounts;
  final List<CashoutRequest> requests;

  bool get canRequest => blockers.isEmpty;
  bool get identityEditable => identity == null || identity!.status == 'rejected';
  bool get identityReady => identity != null && identity!.status != 'rejected';
  List<BankAccount> get verifiedAccounts => accounts.where((a) => a.verified).toList();

  /// The most the user can ask for right now (0 when below the minimum).
  int get maxRequestable {
    final m = [limits.max, limits.windowLeft, withdrawablePoints].reduce((a, b) => a < b ? a : b);
    return m < limits.min ? 0 : m;
  }
}

final cashoutProvider = FutureProvider.autoDispose<CashoutOverview>(
  (ref) async => CashoutOverview.fromJson((await ref.watch(apiClientProvider).get('/cashout'))['data'] as Map<String, dynamic>),
);

/// Every write is device-signed and carries a fresh cash-out SMS code.
class CashoutRepository {
  CashoutRepository(this._api);

  final ApiClient _api;

  /// Sends a confirmation code to the account's phone; returns seconds until a resend is allowed.
  Future<int> sendCode() async => _i(((await _api.post('/cashout/otp', options: Req.signed()))['data'] as Map)['resend_in']);

  Future<void> submitIdentity({required String firstName, required String lastName, required String nationalCode, required DateTime birthDate, required String code}) =>
      _api.post('/cashout/identity', options: Req.signed(), data: {
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'national_code': nationalCode,
        'birth_date': '${birthDate.year.toString().padLeft(4, '0')}-${birthDate.month.toString().padLeft(2, '0')}-${birthDate.day.toString().padLeft(2, '0')}',
        'code': code,
      });

  Future<void> addBankAccount({required String sheba, required String code}) => _api.post('/cashout/bank-accounts', options: Req.signed(), data: {'sheba': sheba, 'code': code});

  Future<void> removeBankAccount(String id) => _api.delete('/cashout/bank-accounts/$id', options: Req.signed());

  Future<CashoutRequest> request({required String bankAccountId, required int points, required String code, required String idempotencyKey}) async =>
      CashoutRequest.fromJson((await _api.post('/cashout/requests',
          options: Req.signed(Options(headers: {'Idempotency-Key': idempotencyKey})),
          data: {'bank_account_id': bankAccountId, 'points': points, 'code': code}))['data'] as Map<String, dynamic>);

  Future<void> cancel(String id) => _api.post('/cashout/requests/$id/cancel', options: Req.signed());
}

final cashoutRepositoryProvider = Provider<CashoutRepository>((ref) => CashoutRepository(ref.watch(apiClientProvider)));
