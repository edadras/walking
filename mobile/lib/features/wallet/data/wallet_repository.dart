import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'wallet_models.dart';

class WalletRepository {
  WalletRepository(this._api);

  final ApiClient _api;

  Future<WalletBalance> balance() async => WalletBalance.fromJson((await _api.get('/wallet'))['data'] as Map<String, dynamic>);

  Future<TxPage> transactions({String filter = 'all', String? cursor}) async {
    final r = await _api.get('/wallet/transactions', query: {'filter': filter, 'cursor': ?cursor});
    final items = (r['data'] as List).map((e) => PointTx.fromJson(e as Map<String, dynamic>)).toList();
    return TxPage(items, (r['meta'] as Map?)?['next_cursor'] as String?);
  }
}

final walletRepositoryProvider = Provider<WalletRepository>((ref) => WalletRepository(ref.watch(apiClientProvider)));

final walletBalanceProvider = FutureProvider.autoDispose<WalletBalance>((ref) => ref.watch(walletRepositoryProvider).balance());

/// Paged history for one filter; `loadMore` appends the next cursor page.
@immutable
class TxListState {
  const TxListState({this.items = const [], this.cursor, this.loadingMore = false, this.done = false});

  final List<PointTx> items;
  final String? cursor;
  final bool loadingMore;
  final bool done;
}

class TxListController extends AsyncNotifier<TxListState> {
  TxListController(this.filter);

  final String filter;

  @override
  Future<TxListState> build() async {
    final page = await ref.watch(walletRepositoryProvider).transactions(filter: filter);
    return TxListState(items: page.items, cursor: page.nextCursor, done: page.nextCursor == null);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || current.done || current.loadingMore) return;
    state = AsyncData(TxListState(items: current.items, cursor: current.cursor, loadingMore: true));
    try {
      final page = await ref.read(walletRepositoryProvider).transactions(filter: filter, cursor: current.cursor);
      state = AsyncData(TxListState(items: [...current.items, ...page.items], cursor: page.nextCursor, done: page.nextCursor == null));
    } catch (_) {
      state = AsyncData(TxListState(items: current.items, cursor: current.cursor));
    }
  }
}

final txListProvider = AsyncNotifierProvider.autoDispose.family<TxListController, TxListState, String>(TxListController.new);
