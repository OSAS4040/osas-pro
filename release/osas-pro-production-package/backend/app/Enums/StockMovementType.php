<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PurchaseReceipt   = 'purchase_receipt';
    case SaleDeduction     = 'sale_deduction';
    case ManualAdd         = 'manual_add';
    case ManualDeduct      = 'manual_deduct';
    case SetAdjustment     = 'set_adjustment';
    case ReservationHold   = 'reservation_hold';
    case ReservationRelease= 'reservation_release';
    case TransferIn        = 'transfer_in';
    case TransferOut       = 'transfer_out';
    case Reversal          = 'reversal';
    case OpeningBalance    = 'opening_balance';
    case WriteOff          = 'write_off';

    public function label(): string
    {
        return match($this) {
            self::PurchaseReceipt    => 'Purchase Receipt',
            self::SaleDeduction      => 'Sale Deduction',
            self::ManualAdd          => 'Manual Addition',
            self::ManualDeduct       => 'Manual Deduction',
            self::SetAdjustment      => 'Set Adjustment',
            self::ReservationHold    => 'Reservation Hold',
            self::ReservationRelease => 'Reservation Release',
            self::TransferIn         => 'Transfer In',
            self::TransferOut        => 'Transfer Out',
            self::Reversal           => 'Reversal',
            self::OpeningBalance     => 'Opening Balance',
            self::WriteOff           => 'Write-Off',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [
            self::PurchaseReceipt,
            self::ManualAdd,
            self::ReservationRelease,
            self::TransferIn,
            self::OpeningBalance,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
