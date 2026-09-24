import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/activity_models.dart';
import '../data/activity_repository.dart';
import 'tracking_service.dart';

/// Home = server truth + steps still waiting in the offline queue for today.
class HomeView {
  const HomeView({required this.data, required this.unsyncedSteps});

  final HomeData data;
  final int unsyncedSteps;

  int get steps => data.today.steps + unsyncedSteps;
  int get goal => data.today.goal;

  /// Recorded but not yet checked by the server (queued locally or awaiting scoring).
  int get awaitingVerification => unsyncedSteps + data.today.pendingSteps;
}

class HomeController extends AsyncNotifier<HomeView> {
  @override
  Future<HomeView> build() async {
    final data = await ref.watch(activityRepositoryProvider).home();
    final unsynced = await ref.read(trackingServiceProvider).pendingStepsToday();
    return HomeView(data: data, unsyncedSteps: unsynced);
  }

  /// Pull-to-refresh: sync first so the numbers include the latest steps.
  Future<void> refresh() async {
    try {
      await ref.read(trackingServiceProvider).sync();
    } on ApiException {
      // Offline: still reload what we can.
    }
    ref.invalidateSelf();
    await future;
  }
}

final homeProvider = AsyncNotifierProvider<HomeController, HomeView>(HomeController.new);

/// One day's timeline (`null` = today).
final dayActivityProvider = FutureProvider.autoDispose.family<DayActivity, String?>(
  (ref, date) => ref.watch(activityRepositoryProvider).day(date),
);

final walkSessionProvider = FutureProvider.autoDispose.family<WalkSession, String>(
  (ref, id) => ref.watch(activityRepositoryProvider).session(id),
);

/// Invalidate every activity view after a sync changed server data.
void refreshActivityViews(Ref ref) {
  ref.invalidate(homeProvider);
  ref.invalidate(dayActivityProvider);
}
