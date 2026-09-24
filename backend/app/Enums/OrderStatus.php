<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'در انتظار پرداخت',
            self::Paid => 'ثبت‌شده',
            self::Processing => 'در حال آماده‌سازی',
            self::Shipped => 'ارسال‌شده',
            self::Delivered => 'تحویل‌شده',
            self::Cancelled => 'لغوشده',
            self::Refunded => 'بازگشت امتیاز',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Delivered, self::Refunded],
            self::Processing => [self::Shipped, self::Delivered, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            // e.g. an invalid gift code reported after delivery
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }
}
