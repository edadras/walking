import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Resolves a remote image URL. Disk-cached in the app so catalogue and avatar
/// images aren't re-downloaded on every visit; tests swap it for local files.
final imageResolverProvider = Provider<ImageProvider Function(String url)>((ref) => (url) => CachedNetworkImageProvider(url));

/// Network image with a soft fade-in and a fallback when the URL is missing or fails.
class NetImage extends ConsumerWidget {
  const NetImage(this.url, {super.key, this.fit = BoxFit.cover, this.width, this.height, this.fallback});

  final String? url;
  final BoxFit fit;
  final double? width;
  final double? height;
  final Widget? fallback;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final fallback = this.fallback ?? SizedBox(width: width, height: height);
    if (url == null || url!.isEmpty) return fallback;
    return Image(
      image: ref.watch(imageResolverProvider)(url!),
      fit: fit,
      width: width,
      height: height,
      gaplessPlayback: true,
      errorBuilder: (_, _, _) => fallback,
      frameBuilder: (context, child, frame, sync) => sync
          ? child
          : AnimatedOpacity(opacity: frame == null ? 0 : 1, duration: const Duration(milliseconds: 220), child: child),
    );
  }
}

/// [ImageProvider] for avatars (CircleAvatar.foregroundImage), through the same resolver.
ImageProvider? netImageProvider(WidgetRef ref, String? url) =>
    url == null || url.isEmpty ? null : ref.watch(imageResolverProvider)(url);

/// [CircleAvatar] whose photo goes through [imageResolverProvider]; [child] shows until it loads.
class NetAvatar extends ConsumerWidget {
  const NetAvatar({super.key, required this.url, this.radius, this.backgroundColor, this.child});

  final String? url;
  final double? radius;
  final Color? backgroundColor;
  final Widget? child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final image = netImageProvider(ref, url);
    return CircleAvatar(
      radius: radius,
      backgroundColor: backgroundColor,
      foregroundImage: image,
      onForegroundImageError: image == null ? null : (_, _) {},
      child: child,
    );
  }
}
