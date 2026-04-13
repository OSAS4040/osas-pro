<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case Sale        = 'sale';
    case Purchase    = 'purchase';
    case Payment     = 'payment';
    case Refund      = 'refund';
    case Adjustment  = 'adjustment';
    case Reversal    = 'reversal';
    case WalletTopUp = 'wallet_top_up';
    case WalletDebit = 'wallet_debit';
    case VatOutput   = 'vat_output';
    case VatInput    = 'vat_input';
}
