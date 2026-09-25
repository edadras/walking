int _i(Object? v) => (v as num?)?.toInt() ?? 0;

class StoreCategory {
  const StoreCategory({required this.id, required this.name, this.parent});

  factory StoreCategory.fromJson(Map<String, dynamic> j) => StoreCategory(id: j['id'] as String, name: j['name'] as String, parent: j['parent'] as String?);

  final String id;
  final String name;
  final String? parent;
}

class Product {
  const Product({
    required this.id,
    required this.slug,
    required this.name,
    required this.type,
    required this.typeLabel,
    required this.pointPrice,
    required this.inStock,
    required this.minLevel,
    required this.needsAddress,
    this.summary,
    this.stock,
    this.maxPerUser,
    this.imageUrl,
    this.sponsor,
    this.description,
    this.images = const [],
    this.rialPrice,
  });

  factory Product.fromJson(Map<String, dynamic> j) => Product(
        id: j['id'] as String,
        slug: j['slug'] as String,
        name: j['name'] as String,
        summary: j['summary'] as String?,
        type: j['type'] as String,
        typeLabel: j['type_label'] as String? ?? '',
        pointPrice: _i(j['point_price']),
        inStock: j['in_stock'] != false,
        stock: (j['stock'] as num?)?.toInt(),
        maxPerUser: (j['max_per_user'] as num?)?.toInt(),
        minLevel: _i(j['min_level']) == 0 ? 1 : _i(j['min_level']),
        needsAddress: j['needs_address'] == true,
        imageUrl: j['image_url'] as String?,
        sponsor: j['sponsor'] as String?,
        description: j['description'] as String?,
        images: ((j['images'] as List?) ?? const []).cast<String>(),
        rialPrice: (j['rial_price'] as num?)?.toInt(),
      );

  final String id;
  final String slug;
  final String name;
  final String? summary;
  final String type; // physical | digital_code | coupon | service
  final String typeLabel;
  final int pointPrice;
  final bool inStock;
  final int? stock;
  final int? maxPerUser;
  final int minLevel;
  final bool needsAddress;
  final String? imageUrl;
  final String? sponsor;
  final String? description;
  final List<String> images;

  /// Set only while rial checkout is offered for this product.
  final int? rialPrice;

  bool get instant => type == 'digital_code' || type == 'coupon';
  int get maxQuantity => [10, ?maxPerUser, ?stock, if (type == 'coupon') 1].reduce((a, b) => a < b ? a : b);

  /// Description is admin-authored rich text; the app shows it as plain paragraphs.
  String get plainDescription => (description ?? '')
      .replaceAll(RegExp(r'<\s*(br|/p|/li)\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'<li[^>]*>', caseSensitive: false), '• ')
      .replaceAll(RegExp(r'<[^>]+>'), '')
      .replaceAll('&nbsp;', ' ')
      .replaceAll('&amp;', '&')
      .replaceAll(RegExp(r'\n{3,}'), '\n\n')
      .trim();
}

class Address {
  const Address({required this.id, required this.recipient, required this.phone, required this.province, required this.city, required this.line, required this.postalCode, required this.isDefault, this.title});

  factory Address.fromJson(Map<String, dynamic> j) => Address(
        id: j['id'] as String? ?? '',
        title: j['title'] as String?,
        recipient: j['recipient'] as String,
        phone: j['phone'] as String,
        province: j['province'] as String,
        city: j['city'] as String,
        line: j['line'] as String,
        postalCode: j['postal_code'] as String,
        isDefault: j['is_default'] == true,
      );

  final String id;
  final String? title;
  final String recipient;
  final String phone;
  final String province;
  final String city;
  final String line;
  final String postalCode;
  final bool isDefault;

  String get oneLine => '$province، $city، $line';

  Map<String, dynamic> toJson() => {
        'title': title,
        'recipient': recipient,
        'phone': phone,
        'province': province,
        'city': city,
        'line': line,
        'postal_code': postalCode,
        'is_default': isDefault,
      };
}

class OrderItem {
  const OrderItem({required this.name, required this.type, required this.quantity, required this.unitPointPrice, required this.codes, this.imageUrl, this.couponId});

  factory OrderItem.fromJson(Map<String, dynamic> j) => OrderItem(
        name: j['name'] as String,
        type: j['type'] as String,
        quantity: _i(j['quantity']),
        unitPointPrice: _i(j['unit_point_price']),
        imageUrl: j['image_url'] as String?,
        codes: ((j['codes'] as List?) ?? const []).map((e) => (e as Map<String, dynamic>)['code'] as String).toList(),
        couponId: j['coupon_id'] as String?,
      );

  final String name;
  final String type;
  final int quantity;
  final int unitPointPrice;
  final String? imageUrl;
  final List<String> codes;
  final String? couponId;
}

class Order {
  const Order({
    required this.id,
    required this.number,
    required this.status,
    required this.statusLabel,
    required this.totalPoints,
    required this.placedAt,
    required this.itemCount,
    required this.cancellable,
    this.title,
    this.imageUrl,
    this.paymentMode = 'points',
    this.totalRial = 0,
    this.payment,
    this.items = const [],
    this.shippingAddress,
    this.trackingCode,
    this.history = const [],
  });

  factory Order.fromJson(Map<String, dynamic> j) => Order(
        id: j['id'] as String,
        number: j['number'] as String,
        status: j['status'] as String,
        statusLabel: j['status_label'] as String? ?? '',
        totalPoints: _i(j['total_points']),
        placedAt: DateTime.parse(j['placed_at'] as String),
        itemCount: _i(j['item_count']),
        title: j['title'] as String?,
        imageUrl: j['image_url'] as String?,
        paymentMode: j['payment_mode'] as String? ?? 'points',
        totalRial: _i(j['total_rial']),
        payment: j['payment'] == null ? null : OrderPayment.fromJson(j['payment'] as Map<String, dynamic>),
        cancellable: j['cancellable'] == true,
        items: ((j['items'] as List?) ?? const []).map((e) => OrderItem.fromJson(e as Map<String, dynamic>)).toList(),
        shippingAddress: j['shipping_address'] == null ? null : Address.fromJson(j['shipping_address'] as Map<String, dynamic>),
        trackingCode: j['tracking_code'] as String?,
        history: ((j['history'] as List?) ?? const [])
            .map((e) => e as Map<String, dynamic>)
            .map((e) => (status: e['status'] as String, label: e['label'] as String, note: e['note'] as String?, at: DateTime.parse(e['at'] as String)))
            .toList(),
      );

  final String id;
  final String number;
  final String status;
  final String statusLabel;
  final int totalPoints;
  final DateTime placedAt;
  final int itemCount;
  final String? title;
  final String? imageUrl;
  final String paymentMode;
  final int totalRial;
  final OrderPayment? payment;
  final bool cancellable;

  bool get isMoney => paymentMode == 'money';
  bool get awaitingPayment => status == 'awaiting_payment';
  final List<OrderItem> items;
  final Address? shippingAddress;
  final String? trackingCode;
  final List<({String status, String label, String? note, DateTime at})> history;
}

/// Rial payment state of an order (gateway reference, resumable page).
class OrderPayment {
  const OrderPayment({required this.status, required this.amountRial, this.refId, this.payUrl});

  factory OrderPayment.fromJson(Map<String, dynamic> j) => OrderPayment(
        status: j['status'] as String,
        amountRial: _i(j['amount_rial']),
        refId: j['ref_id'] as String?,
        payUrl: j['pay_url'] as String?,
      );

  final String status; // pending | paid | failed | cancelled
  final int amountRial;
  final String? refId;

  /// Gateway page, while the payment can still be completed.
  final String? payUrl;
}
