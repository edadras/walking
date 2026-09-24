import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gamyar/core/network/api_client.dart';
import 'package:gamyar/core/network/api_exception.dart';
import 'package:gamyar/core/storage/secure_store.dart';
import 'package:gamyar/features/auth/application/session_controller.dart';
import 'package:gamyar/features/config/data/app_config.dart';
import 'package:gamyar/features/profile/data/me.dart';
import 'package:gamyar/features/store/data/store_models.dart';
import 'package:gamyar/features/store/data/store_repository.dart';
import 'package:gamyar/features/store/presentation/addresses_page.dart';
import 'package:gamyar/features/store/presentation/orders_page.dart';
import 'package:gamyar/features/store/presentation/product_page.dart';
import 'package:gamyar/features/store/presentation/store_page.dart';
import 'package:gamyar/features/wallet/data/wallet_models.dart';
import 'package:gamyar/features/wallet/data/wallet_repository.dart';

import '../../helpers/test_app.dart';

final me = Me.fromJson({
  'id': '01h', 'public_name': 'سارا', 'display_name': 'سارا', 'status': 'active', 'level': 2, 'xp': 10,
  'referral_code': 'X', 'timezone': 'Asia/Tehran', 'profile': <String, dynamic>{}, 'settings': <String, dynamic>{},
});

Map<String, dynamic> productJson({String slug = 'bottle', String type = 'physical', int price = 250, int minLevel = 1, bool inStock = true, int? stock, int? max}) => {
      'id': '01HZZZZZZZZZZZZZZZZZZZZZZZ', 'slug': slug, 'name': slug == 'bottle' ? 'قمقمه ورزشی' : 'کارت هدیه', 'summary': 'فولادی', 'type': type,
      'type_label': type == 'physical' ? 'کالای فیزیکی' : 'کد دیجیتال', 'point_price': price, 'in_stock': inStock, 'stock': stock, 'max_per_user': max,
      'min_level': minLevel, 'needs_address': type == 'physical', 'image_url': null, 'category': 'sport', 'sponsor': null,
      'description': '<p>آب را <b>خنک</b> نگه می‌دارد.</p><ul><li>فولادی</li></ul>', 'images': <String>[],
    };

Map<String, dynamic> orderJson({String status = 'delivered', List<String> codes = const ['GIFT-1234'], bool cancellable = false}) => {
      'id': 'o1', 'number': '#ABC123', 'status': status, 'status_label': status == 'paid' ? 'ثبت‌شده' : 'تحویل‌شده', 'total_points': 500,
      'placed_at': '2026-09-24T08:00:00Z', 'item_count': 1, 'title': 'کارت هدیه', 'cancellable': cancellable,
      'items': [{'name': 'کارت هدیه', 'type': 'digital_code', 'quantity': 1, 'unit_point_price': 500, 'image_url': null, 'codes': [for (final c in codes) {'code': c}], 'coupon_id': null}],
      'shipping_address': null, 'tracking_code': null,
      'history': [{'status': 'paid', 'label': 'ثبت‌شده', 'note': null, 'at': '2026-09-24T08:00:00Z'}, {'status': status, 'label': 'تحویل‌شده', 'note': 'تحویل خودکار', 'at': '2026-09-24T08:00:01Z'}],
    };

class FakeStoreRepository extends StoreRepository {
  FakeStoreRepository() : super(ApiClient(store: MemorySecureStore(), deviceKey: FakeDeviceKey(), appVersion: '1'));

  final keys = <String>[];
  int failFirst = 0;
  final saved = <Address>[];

  @override
  Future<List<StoreCategory>> categories() async => [const StoreCategory(id: 'sport', name: 'ورزشی')];

  @override
  Future<List<Product>> products(ProductQuery query) async =>
      [Product.fromJson(productJson()), Product.fromJson(productJson(slug: 'card', type: 'digital_code', minLevel: 5, price: 1200))];

  @override
  Future<Product> product(String slug) async => Product.fromJson(productJson(slug: slug, type: slug == 'bottle' ? 'physical' : 'digital_code', stock: 3, max: 2));

  @override
  Future<List<Address>> addresses() async => [
        Address.fromJson({'id': 'a1', 'title': 'خانه', 'recipient': 'سارا', 'phone': '09121234567', 'province': 'تهران', 'city': 'تهران', 'line': 'آزادی ۱', 'postal_code': '1234567890', 'is_default': true}),
      ];

  @override
  Future<Order> placeOrder({required String productId, required int quantity, required String idempotencyKey, String? addressId, String? note}) async {
    keys.add('$idempotencyKey|$quantity|$addressId');
    if (failFirst-- > 0) throw const ApiException(code: 'network', message: 'اتصال برقرار نشد.');
    return Order.fromJson(orderJson());
  }

  @override
  Future<Order> order(String id) async => Order.fromJson(orderJson());

  @override
  Future<Address> saveAddress(Address a) async {
    saved.add(a);
    return a;
  }
}

WalletBalance balance(int n) => WalletBalance.fromJson({'available': n, 'pending': 0, 'rial_per_point': 500, 'rial_value': n * 500, 'pending_rial_value': 0, 'lifetime_earned': n, 'lifetime_spent': 0, 'next_release_at': null});

List overrides(FakeStoreRepository repo, {int points = 600}) => [
      storeRepositoryProvider.overrideWithValue(repo),
      configProvider.overrideWithValue(const AppConfig(features: {'store': true}, settings: {}, updateRequired: false, serverTime: null)),
      walletBalanceProvider.overrideWith((_) async => balance(points)),
      meProvider.overrideWithValue(me),
    ];

void main() {
  testWidgets('catalogue shows products, prices and level locks', (tester) async {
    await tester.pumpWidget(testApp(const StorePage(), overrides: overrides(FakeStoreRepository())));
    await tester.pumpAndSettle();

    expect(find.text('قمقمه ورزشی'), findsOneWidget);
    expect(find.text('۲۵۰'), findsOneWidget);
    expect(find.text('از سطح ۵'), findsOneWidget);
    expect(find.text('ورزشی'), findsOneWidget);
    expect(find.text('۶۰۰'), findsOneWidget, reason: 'wallet balance in the app bar');
  });

  testWidgets('checkout reuses one idempotency key across retries and needs enough points', (tester) async {
    final repo = FakeStoreRepository()..failFirst = 1;
    await tester.pumpWidget(testApp(const ProductPage(slug: 'bottle'), overrides: overrides(repo)));
    await tester.pumpAndSettle();

    expect(find.textContaining('خنک'), findsOneWidget);
    expect(find.text('فقط ۳ عدد باقی مانده'), findsOneWidget);
    await tester.tap(find.text('خرید'));
    await tester.pumpAndSettle();

    expect(find.text('خانه'), findsOneWidget, reason: 'default address is preselected');
    await tester.tap(find.byIcon(Icons.add));
    await tester.pumpAndSettle();
    expect(find.text('موجودی پس از خرید: ۱۰۰'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.add));
    await tester.pumpAndSettle();
    expect(find.text('۲'), findsOneWidget, reason: 'capped at max_per_user');

    await tester.tap(find.text('پرداخت با ۵۰۰ امتیاز'));
    await tester.pumpAndSettle();
    expect(find.text('اتصال برقرار نشد.'), findsOneWidget);
    await tester.tap(find.text('پرداخت با ۵۰۰ امتیاز'));
    await tester.pump();

    expect(repo.keys, hasLength(2));
    expect(repo.keys.toSet(), hasLength(1), reason: 'same key, quantity and address on retry');
    expect(repo.keys.first, endsWith('|2|a1'));
  });

  testWidgets('checkout is disabled without enough points', (tester) async {
    await tester.pumpWidget(testApp(const ProductPage(slug: 'bottle'), overrides: overrides(FakeStoreRepository(), points: 100)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('خرید'));
    await tester.pumpAndSettle();
    expect(find.text('۱۵۰ امتیاز دیگر لازم داری'), findsOneWidget);
  });

  testWidgets('order detail reveals delivered codes and the timeline', (tester) async {
    await tester.pumpWidget(testApp(const OrderDetailPage(id: 'o1'), overrides: overrides(FakeStoreRepository())));
    await tester.pumpAndSettle();
    expect(find.text('GIFT-1234'), findsOneWidget);
    expect(find.text('تحویل‌شده'), findsWidgets);
    expect(find.text('لغو سفارش و بازگشت امتیاز'), findsNothing);
  });

  testWidgets('address form validates Iranian phone and postal code', (tester) async {
    tester.view.physicalSize = const Size(900, 2400);
    addTearDown(tester.view.reset);
    final repo = FakeStoreRepository();
    await tester.pumpWidget(testApp(const AddressFormPage(), overrides: overrides(repo)));
    await tester.pumpAndSettle();

    Future<void> type(String label, String value) => tester.enterText(find.widgetWithText(TextFormField, label), value);
    await type('نام گیرنده', 'سارا');
    await type('شماره تماس', '۰۹۱۲۱۲۳');
    await type('استان', 'تهران');
    await type('شهر', 'تهران');
    await type('نشانی کامل', 'خیابان آزادی');
    await type('کد پستی ۱۰ رقمی', '123');
    await tester.tap(find.text('ذخیره'));
    await tester.pumpAndSettle();
    expect(find.text('شماره تماس معتبر نیست.'), findsOneWidget);
    expect(find.text('کد پستی باید ۱۰ رقم باشد.'), findsOneWidget);
    expect(repo.saved, isEmpty);

    await type('شماره تماس', '۰۹۱۲۱۲۳۴۵۶۷');
    await type('کد پستی ۱۰ رقمی', '۱۲۳۴۵۶۷۸۹۰');
    await tester.tap(find.text('ذخیره'));
    await tester.pumpAndSettle();
    expect(repo.saved.single.phone, '09121234567', reason: 'Persian digits normalised');
    expect(repo.saved.single.postalCode, '1234567890');
  });

  test('rich descriptions become plain text', () {
    expect(Product.fromJson(productJson()).plainDescription, 'آب را خنک نگه می‌دارد.\n• فولادی');
  });
}
