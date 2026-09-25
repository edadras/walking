import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class OrgRankEntry {
  const OrgRankEntry({required this.rank, required this.name, required this.steps, this.department, this.isMe = false});

  factory OrgRankEntry.fromJson(Map<String, dynamic> j) =>
      OrgRankEntry(rank: _i(j['rank']), name: j['name'] as String, steps: _i(j['steps']), department: j['department'] as String?, isMe: j['is_me'] == true);

  final int rank;
  final String name;
  final int steps;
  final String? department;
  final bool isMe;
}

class OrgDepartment {
  const OrgDepartment({required this.name, required this.members, this.avgSteps});

  factory OrgDepartment.fromJson(Map<String, dynamic> j) =>
      OrgDepartment(name: j['department'] as String, members: _i(j['members']), avgSteps: (j['avg_steps'] as num?)?.toInt());

  final String name;
  final int members;

  /// Null for groups too small to show without identifying someone.
  final int? avgSteps;
}

class OrgChallenge {
  const OrgChallenge({required this.id, required this.title, required this.endsAt});

  final String id;
  final String title;
  final DateTime endsAt;
}

class Organization {
  const Organization({
    required this.name,
    required this.active,
    required this.members,
    required this.myRank,
    required this.mySteps,
    required this.ranking,
    required this.departments,
    required this.departmentOptions,
    required this.challenges,
    this.department,
  });

  factory Organization.fromJson(Map<String, dynamic> j) => Organization(
        name: j['name'] as String,
        active: j['active'] == true,
        department: j['department'] as String?,
        departmentOptions: ((j['departments_list'] as List?) ?? const []).cast<String>(),
        members: _i(j['members']),
        myRank: (j['my_rank'] as num?)?.toInt(),
        mySteps: _i(j['my_steps']),
        ranking: [for (final r in (j['ranking'] as List? ?? const [])) OrgRankEntry.fromJson(r as Map<String, dynamic>)],
        departments: [for (final d in (j['departments'] as List? ?? const [])) OrgDepartment.fromJson(d as Map<String, dynamic>)],
        challenges: [
          for (final c in (j['challenges'] as List? ?? const []))
            OrgChallenge(id: (c as Map)['id'] as String, title: c['title'] as String, endsAt: DateTime.parse(c['ends_at'] as String)),
        ],
      );

  final String name;
  final bool active;
  final String? department;
  final List<String> departmentOptions;
  final int members;
  final int? myRank;
  final int mySteps;
  final List<OrgRankEntry> ranking;
  final List<OrgDepartment> departments;
  final List<OrgChallenge> challenges;
}

/// Null when the user isn't in any organization.
final organizationProvider = FutureProvider.autoDispose<Organization?>((ref) async {
  final data = (await ref.watch(apiClientProvider).get('/organization'))['data'];
  return data == null ? null : Organization.fromJson(data as Map<String, dynamic>);
});

class OrganizationRepository {
  OrganizationRepository(this._api);

  final ApiClient _api;

  Future<void> join(String code) => _api.post('/organization/join', data: {'code': code.trim()});

  Future<void> setDepartment(String department) => _api.patch('/organization', data: {'department': department});

  Future<void> leave() => _api.delete('/organization');
}

final organizationRepositoryProvider = Provider<OrganizationRepository>((ref) => OrganizationRepository(ref.watch(apiClientProvider)));
