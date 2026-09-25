import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/activity/presentation/activity_page.dart';
import '../features/ads/presentation/rewarded_ad_page.dart';
import '../features/activity/presentation/session_detail_page.dart';
import '../features/activity/presentation/walk_page.dart';
import '../features/auth/application/session_controller.dart';
import '../features/cashout/data/cashout.dart';
import '../features/cashout/presentation/cashout_page.dart';
import '../features/cashout/presentation/identity_page.dart';
import '../features/organization/presentation/organization_page.dart';
import '../features/challenges/presentation/challenges_page.dart';
import '../features/gamification/presentation/achievements_page.dart';
import '../features/gamification/presentation/leaderboard_page.dart';
import '../features/gamification/presentation/referral_page.dart';
import '../features/health/presentation/health_page.dart';
import '../features/health/presentation/water_page.dart';
import '../features/notifications/presentation/inbox_page.dart';
import '../features/auth/presentation/otp_page.dart';
import '../features/auth/presentation/phone_page.dart';
import '../features/content/presentation/content_page.dart';
import '../features/home/presentation/home_page.dart';
import '../features/onboarding/presentation/onboarding_page.dart';
import '../features/profile/presentation/delete_account_page.dart';
import '../features/profile/presentation/devices_page.dart';
import '../features/profile/presentation/edit_profile_page.dart';
import '../features/profile/presentation/notifications_page.dart';
import '../features/profile/presentation/profile_page.dart';
import '../features/rewards/presentation/rewards_page.dart';
import '../features/shell/presentation/app_shell.dart';
import '../features/sponsors/presentation/campaign_page.dart';
import '../features/sponsors/presentation/coupons_page.dart';
import '../features/sponsors/presentation/nearby_page.dart';
import '../features/sponsors/presentation/qr_scan_page.dart';
import '../features/sponsors/presentation/visit_page.dart';
import '../features/store/data/store_models.dart';
import '../features/support/presentation/support_pages.dart';
import '../features/store/presentation/addresses_page.dart';
import '../features/store/presentation/orders_page.dart';
import '../features/store/presentation/product_page.dart';
import '../features/store/presentation/store_page.dart';
import '../features/wallet/presentation/wallet_page.dart';
import '../features/permissions/presentation/permissions_page.dart';
import '../features/quests/presentation/quests_page.dart';
import '../features/social/presentation/friends_page.dart';
import '../core/permissions/required_permissions.dart';
import '../features/shell/presentation/splash_page.dart';

/// Bridges Riverpod session changes to GoRouter's refreshListenable.
class _SessionListenable extends ChangeNotifier {
  _SessionListenable(Ref ref) {
    ref.listen(sessionProvider, (_, _) => notifyListeners());
    ref.listen(permissionGateProvider, (_, _) => notifyListeners());
  }
}

/// Signed in: the required-permissions gate comes before anything else.
@visibleForTesting
String? signedInRedirect(String loc, AsyncValue<PermissionGate> gate) {
  if (!gate.hasValue && !gate.hasError) return loc == '/splash' ? null : '/splash';
  if (gate.value?.satisfied != true) return loc == '/permissions' ? null : '/permissions';
  final entry = loc == '/splash' || loc == '/onboarding' || loc.startsWith('/auth') || loc == '/blocked' || loc == '/permissions';
  return entry ? '/home' : null;
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _SessionListenable(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      final loc = state.matchedLocation;
      final public = loc.startsWith('/page/');

      if (!session.hasValue) return loc == '/splash' ? null : '/splash';

      return switch (session.value!) {
        SessionUnauthenticated(onboardingDone: false) => loc == '/onboarding' ? null : '/onboarding',
        SessionUnauthenticated() => loc.startsWith('/auth') || public ? null : '/auth/phone',
        SessionBlocked() => loc == '/blocked' || public ? null : '/blocked',
        SessionAuthenticated() => signedInRedirect(loc, ref.read(permissionGateProvider)),
      };
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashPage()),
      GoRoute(path: '/onboarding', builder: (_, _) => const OnboardingPage()),
      GoRoute(path: '/blocked', builder: (_, _) => const BlockedPage()),
      GoRoute(path: '/permissions', builder: (_, _) => const PermissionsPage()),
      GoRoute(path: '/quests', builder: (_, _) => const QuestsPage()),
      GoRoute(path: '/friends', builder: (_, _) => const FriendsPage()),
      GoRoute(path: '/organization', builder: (_, _) => const OrganizationPage()),
      GoRoute(path: '/friend-challenges/:id', builder: (_, s) => FriendRacePage(id: s.pathParameters['id']!)),
      GoRoute(path: '/auth/phone', builder: (_, _) => const PhonePage()),
      GoRoute(
        path: '/auth/otp',
        redirect: (_, state) => state.extra is OtpArgs ? null : '/auth/phone',
        builder: (_, state) => OtpPage(args: state.extra! as OtpArgs),
      ),
      GoRoute(path: '/page/:slug', builder: (_, state) => ContentPage(slug: state.pathParameters['slug']!)),
      GoRoute(path: '/faq', builder: (_, _) => const FaqPage()),
      GoRoute(path: '/walk', builder: (_, _) => const WalkPage()),
      GoRoute(path: '/wallet', builder: (_, _) => const WalletPage()),
      GoRoute(path: '/cashout', builder: (_, _) => const CashoutPage()),
      GoRoute(path: '/cashout/identity', builder: (_, s) => CashoutIdentityPage(overview: s.extra! as CashoutOverview)),
      GoRoute(path: '/health', builder: (_, _) => const HealthPage()),
      GoRoute(path: '/weekly-report', builder: (_, _) => const WeeklyReportPage()),
      GoRoute(path: '/water', builder: (_, _) => const WaterPage()),
      GoRoute(path: '/leaderboard', builder: (_, _) => const LeaderboardPage()),
      GoRoute(path: '/achievements', builder: (_, _) => const AchievementsPage()),
      GoRoute(path: '/referral', builder: (_, _) => const ReferralPage()),
      GoRoute(path: '/notifications', builder: (_, _) => const InboxPage()),
      GoRoute(path: '/nearby', builder: (_, _) => const NearbyPage()),
      GoRoute(path: '/campaigns/:id', builder: (_, s) => CampaignPage(id: s.pathParameters['id']!)),
      GoRoute(path: '/visits/:id', builder: (_, s) => VisitPage(id: s.pathParameters['id']!)),
      GoRoute(path: '/scan-qr', builder: (_, _) => const QrScanPage()),
      GoRoute(path: '/coupons', builder: (_, _) => const CouponsPage()),
      GoRoute(path: '/rewarded-ad', builder: (_, _) => const RewardedAdPage()),
      GoRoute(path: '/support', builder: (_, _) => const SupportPage()),
      GoRoute(path: '/support/new', builder: (_, _) => const NewTicketPage()),
      GoRoute(path: '/support/:id', builder: (_, s) => TicketPage(id: s.pathParameters['id']!)),
      GoRoute(path: '/orders', builder: (_, _) => const OrdersPage()),
      GoRoute(path: '/orders/:id', builder: (_, s) => OrderDetailPage(id: s.pathParameters['id']!)),
      GoRoute(path: '/addresses', builder: (_, _) => const AddressesPage()),
      GoRoute(path: '/addresses/new', builder: (_, _) => const AddressFormPage()),
      GoRoute(path: '/addresses/edit', builder: (_, s) => AddressFormPage(address: s.extra as Address?)),
      GoRoute(
        path: '/challenges',
        builder: (_, _) => const ChallengesPage(),
        routes: [GoRoute(path: ':id', builder: (_, s) => ChallengeDetailPage(id: s.pathParameters['id']!))],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const HomePage())]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/activity',
              builder: (_, _) => const ActivityPage(),
              routes: [GoRoute(path: 'session/:id', builder: (_, s) => SessionDetailPage(id: s.pathParameters['id']!))],
            ),
          ]),
          StatefulShellBranch(routes: [GoRoute(path: '/rewards', builder: (_, _) => const RewardsPage())]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/store',
              builder: (_, _) => const StorePage(),
              routes: [GoRoute(path: ':slug', builder: (_, s) => ProductPage(slug: s.pathParameters['slug']!))],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/profile',
              builder: (_, _) => const ProfilePage(),
              routes: [
                GoRoute(path: 'edit', builder: (_, _) => const EditProfilePage()),
                GoRoute(path: 'notifications', builder: (_, _) => const NotificationsPage()),
                GoRoute(path: 'devices', builder: (_, _) => const DevicesPage()),
                GoRoute(path: 'delete', builder: (_, _) => const DeleteAccountPage()),
              ],
            ),
          ]),
        ],
      ),
    ],
    debugLogDiagnostics: kDebugMode,
  );
});
