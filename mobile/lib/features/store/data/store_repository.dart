import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import 'store_models.dart';

typedef ProductQuery = ({String? category, String? q, String sort});

class StoreRepository {
  StoreRepository(this._api);

  final ApiClient _api;

  Future<List<StoreCategory>> categories() async =>
      ((await _api.get('/store/categories'))['data'] as List).map((e) => StoreCategory.fromJson(e as Map<String, dynamic>)).toList();

  Future<List<Product>> products(ProductQuery query) async {
    final r = await _api.get('/store/products', query: {
      if (query.category != null) 'category': query.category,
      if (query.q != null && query.q!.isNotEmpty) 'q': query.q,
      'sort': query.sort,
    });
    return (r['data'] as List).map((e) => Product.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Product> product(String slug) async => Product.fromJson((await _api.get('/store/products/$slug'))['data'] as Map<String, dynamic>);

  /// The key is created once per checkout so retries never buy twice.
  Future<Order> placeOrder({required String productId, required int quantity, required String idempotencyKey, String? addressId, String? note, bool money = false}) async {
    final r = await _api.post(
      '/orders',
      data: {
        'items': [
          {'product_id': productId, 'quantity': quantity},
        ],
        'address_id': ?addressId,
        if (money) 'payment_mode': 'money',
        if (note != null && note.isNotEmpty) 'note': note,
      },
      options: Req.signed(Options(headers: {'Idempotency-Key': idempotencyKey})),
    );
    return Order.fromJson(r['data'] as Map<String, dynamic>);
  }

  Future<List<Order>> orders() async => ((await _api.get('/orders'))['data'] as List).map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();

  Future<Order> order(String id) async => Order.fromJson((await _api.get('/orders/$id'))['data'] as Map<String, dynamic>);

  Future<Order> cancel(String id) async => Order.fromJson((await _api.post('/orders/$id/cancel', options: Req.signed()))['data'] as Map<String, dynamic>);

  Future<List<Address>> addresses() async => ((await _api.get('/addresses'))['data'] as List).map((e) => Address.fromJson(e as Map<String, dynamic>)).toList();

  Future<Address> saveAddress(Address a) async {
    final r = a.id.isEmpty ? await _api.post('/addresses', data: a.toJson()) : await _api.patch('/addresses/${a.id}', data: a.toJson());
    return Address.fromJson(r['data'] as Map<String, dynamic>);
  }

  Future<void> deleteAddress(String id) => _api.delete('/addresses/$id');
}

final storeRepositoryProvider = Provider<StoreRepository>((ref) => StoreRepository(ref.watch(apiClientProvider)));

final storeCategoriesProvider = FutureProvider.autoDispose<List<StoreCategory>>((ref) => ref.watch(storeRepositoryProvider).categories());

final productsProvider = FutureProvider.autoDispose.family<List<Product>, ProductQuery>((ref, q) => ref.watch(storeRepositoryProvider).products(q));

final productProvider = FutureProvider.autoDispose.family<Product, String>((ref, slug) => ref.watch(storeRepositoryProvider).product(slug));

final ordersProvider = FutureProvider.autoDispose<List<Order>>((ref) => ref.watch(storeRepositoryProvider).orders());

final orderProvider = FutureProvider.autoDispose.family<Order, String>((ref, id) => ref.watch(storeRepositoryProvider).order(id));

final addressesProvider = FutureProvider.autoDispose<List<Address>>((ref) => ref.watch(storeRepositoryProvider).addresses());
