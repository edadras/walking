/// Where opening a notification leads, shared by the inbox and push taps.
/// Server data only ever carries a type and an opaque id, never a raw route.
String? notificationRoute(Map<String, Object?> data) {
  final id = data['id'];
  return switch (data['type']) {
    'referral' => '/referral',
    'achievement' => '/achievements',
    'goal' || 'streak' => '/rewards',
    'challenge' when id is String && id.isNotEmpty => '/challenges/$id',
    'visit' => '/coupons',
    'order' when id is String && id.isNotEmpty => '/orders/$id',
    'support' when id is String && id.isNotEmpty => '/support/$id',
    'friend' => '/friends',
    'friend_challenge' when id is String && id.isNotEmpty => '/friend-challenges/$id',
    _ => null,
  };
}

/// Tab roots are replaced, everything else is pushed on top.
bool isTabRoute(String route) => const {'/home', '/activity', '/rewards', '/store', '/profile'}.contains(route);
