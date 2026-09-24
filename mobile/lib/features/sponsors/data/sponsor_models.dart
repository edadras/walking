int _i(Object? v) => (v as num?)?.toInt() ?? 0;
DateTime? _dt(Object? v) => v == null ? null : DateTime.parse(v as String);

class SponsorRef {
  const SponsorRef({required this.id, required this.name, this.logoUrl});

  factory SponsorRef.fromJson(Map<String, dynamic> j) => SponsorRef(id: j['id'] as String, name: j['name'] as String, logoUrl: j['logo_url'] as String?);

  final String id;
  final String name;
  final String? logoUrl;
}

class CouponOffer {
  const CouponOffer({required this.id, required this.title, required this.discountLabel, required this.pointCost, this.description, this.terms, this.sponsor, this.expiresAt, this.remaining});

  factory CouponOffer.fromJson(Map<String, dynamic> j) => CouponOffer(
        id: j['id'] as String,
        title: j['title'] as String,
        description: j['description'] as String?,
        terms: j['terms'] as String?,
        discountLabel: j['discount_label'] as String? ?? '',
        pointCost: _i(j['point_cost']),
        sponsor: j['sponsor'] == null ? null : SponsorRef.fromJson(j['sponsor'] as Map<String, dynamic>),
        expiresAt: _dt(j['expires_at']),
        remaining: (j['remaining'] as num?)?.toInt(),
      );

  final String id;
  final String title;
  final String? description;
  final String? terms;
  final String discountLabel;
  final int pointCost;
  final SponsorRef? sponsor;
  final DateTime? expiresAt;
  final int? remaining;
}

class CampaignSummary {
  const CampaignSummary({
    required this.id,
    required this.name,
    required this.rewardPoints,
    required this.requiresQr,
    required this.minStaySeconds,
    required this.endsAt,
    required this.eligible,
    this.ineligibleReason,
    this.coupon,
  });

  factory CampaignSummary.fromJson(Map<String, dynamic> j) => CampaignSummary(
        id: j['id'] as String,
        name: j['name'] as String,
        rewardPoints: _i(j['reward_points']),
        coupon: j['coupon'] == null ? null : CouponOffer.fromJson(j['coupon'] as Map<String, dynamic>),
        requiresQr: j['requires_qr'] == true,
        minStaySeconds: _i(j['min_stay_seconds']),
        endsAt: DateTime.parse(j['ends_at'] as String),
        eligible: j['eligible'] == true,
        ineligibleReason: j['ineligible_reason'] as String?,
      );

  final String id;
  final String name;
  final int rewardPoints;
  final CouponOffer? coupon;
  final bool requiresQr;
  final int minStaySeconds;
  final DateTime endsAt;
  final bool eligible;
  final String? ineligibleReason;
}

class Branch {
  const Branch({required this.id, required this.name, required this.lat, required this.lng, required this.radiusM, required this.openNow, this.address, this.city, this.distanceM});

  factory Branch.fromJson(Map<String, dynamic> j) => Branch(
        id: j['id'] as String,
        name: j['name'] as String,
        address: j['address'] as String?,
        city: j['city'] as String?,
        lat: (j['lat'] as num).toDouble(),
        lng: (j['lng'] as num).toDouble(),
        radiusM: _i(j['radius_m']),
        openNow: j['open_now'] != false,
        distanceM: (j['distance_m'] as num?)?.toInt(),
      );

  final String id;
  final String name;
  final String? address;
  final String? city;
  final double lat;
  final double lng;
  final int radiusM;
  final bool openNow;
  final int? distanceM;
}

/// A branch near the user with its running campaigns.
class NearbyPlace {
  const NearbyPlace({required this.branch, required this.sponsor, required this.campaigns});

  factory NearbyPlace.fromJson(Map<String, dynamic> j) => NearbyPlace(
        branch: Branch.fromJson(j),
        sponsor: SponsorRef.fromJson(j['sponsor'] as Map<String, dynamic>),
        campaigns: (j['campaigns'] as List).map((e) => CampaignSummary.fromJson(e as Map<String, dynamic>)).toList(),
      );

  final Branch branch;
  final SponsorRef sponsor;
  final List<CampaignSummary> campaigns;

  int get bestPoints => campaigns.fold(0, (m, c) => c.rewardPoints > m ? c.rewardPoints : m);
}

class CampaignDetail {
  const CampaignDetail({required this.summary, required this.sponsor, required this.branches, required this.maxRewardsPerUser, required this.myRewards, this.description, this.imageUrl});

  factory CampaignDetail.fromJson(Map<String, dynamic> j) => CampaignDetail(
        summary: CampaignSummary.fromJson(j),
        description: j['description'] as String?,
        imageUrl: j['image_url'] as String?,
        sponsor: SponsorRef.fromJson(j['sponsor'] as Map<String, dynamic>),
        branches: (j['locations'] as List).map((e) => Branch.fromJson(e as Map<String, dynamic>)).toList(),
        maxRewardsPerUser: _i(j['max_rewards_per_user']),
        myRewards: _i(j['my_rewards']),
      );

  final CampaignSummary summary;
  final String? description;
  final String? imageUrl;
  final SponsorRef sponsor;
  final List<Branch> branches;
  final int maxRewardsPerUser;
  final int myRewards;
}

class UserCouponItem {
  const UserCouponItem({
    required this.id,
    required this.code,
    required this.status,
    required this.statusLabel,
    required this.title,
    required this.discountLabel,
    required this.claimedAt,
    this.description,
    this.terms,
    this.sharedCode,
    this.sponsor,
    this.expiresAt,
    this.usedAt,
  });

  factory UserCouponItem.fromJson(Map<String, dynamic> j) => UserCouponItem(
        id: j['id'] as String,
        code: j['code'] as String,
        status: j['status'] as String,
        statusLabel: j['status_label'] as String? ?? '',
        title: j['title'] as String,
        description: j['description'] as String?,
        terms: j['terms'] as String?,
        discountLabel: j['discount_label'] as String? ?? '',
        sharedCode: j['shared_code'] as String?,
        sponsor: j['sponsor'] == null ? null : SponsorRef.fromJson(j['sponsor'] as Map<String, dynamic>),
        claimedAt: DateTime.parse(j['claimed_at'] as String),
        expiresAt: _dt(j['expires_at']),
        usedAt: _dt(j['used_at']),
      );

  final String id;
  final String code;
  final String status; // available | used | expired | revoked
  final String statusLabel;
  final String title;
  final String? description;
  final String? terms;
  final String discountLabel;
  final String? sharedCode;
  final SponsorRef? sponsor;
  final DateTime claimedAt;
  final DateTime? expiresAt;
  final DateTime? usedAt;

  bool get usable => status == 'available';

  /// "ABCD EFGH" — easier to read aloud to the cashier.
  String get prettyCode => code.length == 8 ? '${code.substring(0, 4)} ${code.substring(4)}' : code;
}

class VisitState {
  const VisitState({
    required this.id,
    required this.status,
    required this.campaignId,
    required this.campaignName,
    required this.locationId,
    required this.locationName,
    required this.staySeconds,
    required this.minStaySeconds,
    required this.requiresQr,
    required this.qrVerified,
    required this.inside,
    required this.pointsAwarded,
    required this.pingIntervalS,
    this.rejectionReason,
    this.coupon,
  });

  factory VisitState.fromJson(Map<String, dynamic> j) => VisitState(
        id: j['id'] as String,
        status: j['status'] as String,
        campaignId: j['campaign_id'] as String,
        campaignName: j['campaign_name'] as String,
        locationId: j['location_id'] as String,
        locationName: j['location_name'] as String,
        staySeconds: _i(j['stay_seconds']),
        minStaySeconds: _i(j['min_stay_seconds']),
        requiresQr: j['requires_qr'] == true,
        qrVerified: j['qr_verified'] == true,
        inside: j['inside'] == true,
        pointsAwarded: _i(j['points_awarded']),
        rejectionReason: j['rejection_reason'] as String?,
        pingIntervalS: _i(j['ping_interval_s']) == 0 ? 30 : _i(j['ping_interval_s']),
        coupon: j['coupon'] == null ? null : UserCouponItem.fromJson(j['coupon'] as Map<String, dynamic>),
      );

  final String id;
  final String status; // started | verified | rewarded | rejected | expired
  final String campaignId;
  final String campaignName;
  final String locationId;
  final String locationName;
  final int staySeconds;
  final int minStaySeconds;
  final bool requiresQr;
  final bool qrVerified;
  final bool inside;
  final int pointsAwarded;
  final String? rejectionReason;
  final int pingIntervalS;
  final UserCouponItem? coupon;

  bool get open => status == 'started';
  bool get rewarded => status == 'rewarded';
  bool get stayDone => staySeconds >= minStaySeconds;
  double get stayFraction => minStaySeconds == 0 ? 1 : (staySeconds / minStaySeconds).clamp(0, 1).toDouble();
}
