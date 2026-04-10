<?php

namespace App\Enums;

enum WalletTopUpPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Other = 'other';
}
