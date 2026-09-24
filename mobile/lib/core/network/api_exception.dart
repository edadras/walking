import 'package:dio/dio.dart';

/// Every failure surfaced to the UI is an [ApiException] with a Persian,
/// user-presentable [message]. Raw exception text never reaches the screen.
class ApiException implements Exception {
  const ApiException({required this.code, required this.message, this.status, this.fields = const {}, this.context = const {}});

  final String code;
  final String message;
  final int? status;
  final Map<String, List<String>> fields;
  final Map<String, dynamic> context;

  bool get isNetwork => code == 'network' || code == 'timeout';
  bool get isUnauthenticated => status == 401 && code != 'signature_invalid';

  String? fieldError(String field) => fields[field]?.first;

  static const network = ApiException(code: 'network', message: 'اتصال به اینترنت برقرار نیست. اتصال را بررسی کنید و دوباره تلاش کنید.');
  static const timeout = ApiException(code: 'timeout', message: 'پاسخ سرور طول کشید. دوباره تلاش کنید.');
  static const server = ApiException(code: 'server_error', message: 'در اتصال به سرور مشکلی پیش آمد. دوباره تلاش کنید.');
  static const unknown = ApiException(code: 'unknown', message: 'مشکلی پیش آمد. دوباره تلاش کنید.');

  factory ApiException.fromDio(DioException e) {
    if (e.error is ApiException) return e.error! as ApiException;

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return timeout;
      case DioExceptionType.connectionError:
        return network;
      case DioExceptionType.badCertificate:
        return const ApiException(code: 'bad_certificate', message: 'ارتباط امن با سرور برقرار نشد.');
      default:
        break;
    }

    final response = e.response;
    final data = response?.data;
    if (data is Map && data['error'] is Map) {
      final error = data['error'] as Map;
      final rawFields = error['fields'];
      return ApiException(
        code: (error['code'] as String?) ?? 'unknown',
        message: (error['message'] as String?) ?? unknown.message,
        status: response?.statusCode,
        fields: rawFields is Map
            ? rawFields.map((k, v) => MapEntry(k.toString(), (v as List).map((x) => x.toString()).toList()))
            : const {},
        context: error['context'] is Map ? Map<String, dynamic>.from(error['context'] as Map) : const {},
      );
    }

    final status = response?.statusCode ?? 0;
    if (status >= 500) return server;
    if (status == 0) return network;
    return ApiException(code: 'http_$status', message: unknown.message, status: status);
  }

  @override
  String toString() => 'ApiException($code, $status)';
}
