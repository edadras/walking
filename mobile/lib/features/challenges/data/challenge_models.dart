int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class ChallengeItem {
  const ChallengeItem({
    required this.id,
    required this.title,
    required this.description,
    required this.imageUrl,
    required this.typeLabel,
    required this.metric,
    required this.target,
    required this.rewardPoints,
    required this.rewardXp,
    required this.sponsored,
    required this.startsAt,
    required this.endsAt,
    required this.participants,
    required this.status,
    required this.joinable,
    required this.joined,
    required this.progress,
    required this.completedAt,
    this.top = const [],
  });

  factory ChallengeItem.fromJson(Map<String, dynamic> j) => ChallengeItem(
        id: j['id'] as String,
        title: j['title'] as String,
        description: j['description'] as String?,
        imageUrl: j['image_url'] as String?,
        typeLabel: j['type_label'] as String? ?? '',
        metric: j['metric'] as String? ?? 'steps',
        target: _i(j['target']),
        rewardPoints: _i(j['reward_points']),
        rewardXp: _i(j['reward_xp']),
        sponsored: j['sponsored'] == true,
        startsAt: DateTime.parse(j['starts_at'] as String),
        endsAt: DateTime.parse(j['ends_at'] as String),
        participants: _i(j['participants']),
        status: j['status'] as String,
        joinable: j['joinable'] == true,
        joined: j['joined'] == true,
        progress: _i(j['progress']),
        completedAt: DateTime.tryParse(j['completed_at'] as String? ?? ''),
        top: ((j['top'] as List?) ?? const [])
            .map((e) => e as Map<String, dynamic>)
            .map((e) => (rank: _i(e['rank']), name: e['name'] as String, progress: _i(e['progress']), completed: e['completed'] == true, isMe: e['is_me'] == true))
            .toList(),
      );

  final String id;
  final String title;
  final String? description;
  final String? imageUrl;
  final String typeLabel;
  final String metric;
  final int target;
  final int rewardPoints;
  final int rewardXp;
  final bool sponsored;
  final DateTime startsAt;
  final DateTime endsAt;
  final int participants;
  final String status; // upcoming | running | ended
  final bool joinable;
  final bool joined;
  final int progress;
  final DateTime? completedAt;
  final List<({int rank, String name, int progress, bool completed, bool isMe})> top;

  bool get completed => completedAt != null;
  double get fraction => target == 0 ? 0 : (progress / target).clamp(0, 1).toDouble();
}
