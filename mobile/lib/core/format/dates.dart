import 'package:shamsi_date/shamsi_date.dart';

import 'numbers.dart';

/// Jalali presentation of dates. Storage and APIs always use UTC ISO-8601.
abstract final class FaDate {
  static const _months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

  /// Saturday-first, matching the Iranian week.
  static const weekdaysShort = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
  static const _weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

  static Jalali _j(DateTime d) => Jalali.fromDateTime(d.toLocal());

  /// ۳ مهر ۱۴۰۵
  static String long(DateTime d) {
    final j = _j(d);
    return '${Fa.digits(j.day)} ${_months[j.month - 1]} ${Fa.digits(j.year)}';
  }

  /// ۳ مهر
  static String dayMonth(DateTime d) {
    final j = _j(d);
    return '${Fa.digits(j.day)} ${_months[j.month - 1]}';
  }

  /// ۱۴۰۵/۰۷/۰۳
  static String numeric(DateTime d) {
    final j = _j(d);
    String two(int v) => v.toString().padLeft(2, '0');
    return Fa.digits('${j.year}/${two(j.month)}/${two(j.day)}');
  }

  static String weekday(DateTime d) => _weekdays[_j(d).weekDay - 1];

  static int dayOfMonth(DateTime d) => _j(d).day;

  static String weekdayShort(DateTime d) => weekdaysShort[_j(d).weekDay - 1];

  /// ۱ ساعت و ۵ دقیقه / ۱۲ دقیقه
  static String duration(Duration d) {
    final h = d.inHours;
    final m = d.inMinutes % 60;
    if (h == 0) return '${Fa.digits(d.inMinutes)} دقیقه';
    return m == 0 ? '${Fa.digits(h)} ساعت' : '${Fa.digits(h)} ساعت و ${Fa.digits(m)} دقیقه';
  }

  /// ۱۲:۳۴ (stopwatch)
  static String clock(Duration d) {
    String two(int v) => v.toString().padLeft(2, '0');
    final h = d.inHours;
    final body = '${two(d.inMinutes % 60)}:${two(d.inSeconds % 60)}';
    return Fa.digits(h > 0 ? '$h:$body' : body);
  }

  /// ۰۸:۲۰
  static String time(DateTime d) {
    final l = d.toLocal();
    return Fa.digits('${l.hour.toString().padLeft(2, '0')}:${l.minute.toString().padLeft(2, '0')}');
  }

  static String monthYear(DateTime d) {
    final j = _j(d);
    return '${_months[j.month - 1]} ${Fa.digits(j.year)}';
  }

  /// Relative time for lists: «همین حالا», «۵ دقیقه پیش», «دیروز», else the date.
  static String relative(DateTime d, {DateTime? now}) {
    final n = now ?? DateTime.now();
    final diff = n.difference(d);
    if (diff.inMinutes < 1) return 'همین حالا';
    if (diff.inMinutes < 60) return '${Fa.digits(diff.inMinutes)} دقیقه پیش';
    if (diff.inHours < 24 && n.day == d.toLocal().day) return '${Fa.digits(diff.inHours)} ساعت پیش';
    if (diff.inDays < 2) return 'دیروز';
    if (diff.inDays < 7) return '${Fa.digits(diff.inDays)} روز پیش';
    return long(d);
  }
}
