<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TopUp         = 'TOP_UP';
    case TransferOut   = 'TRANSFER_OUT';
    case TransferIn    = 'TRANSFER_IN';
    case InvoiceDebit  = 'INVOICE_DEBIT';
    case Refund        = 'REFUND';
    case AdjustmentAdd = 'ADJUSTMENT_ADD';
    case AdjustmentSub = 'ADJUSTMENT_SUB';
    case Reversal      = 'REVERSAL';

    public function isCredit(): bool
    {
        return in_array($this, [
            self::TopUp,
            self::TransferIn,
            self::Refund,
            self::AdjustmentAdd,
        ]);
    }

    public function isDebit(): bool
    {
        return in_array($this, [
            self::TransferOut,
            self::InvoiceDebit,
            self::AdjustmentSub,
        ]);
    }
}
