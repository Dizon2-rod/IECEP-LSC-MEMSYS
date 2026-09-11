<?php
declare(strict_types=1);

namespace App\Lib;

/**
 * PaymentStatus - Canonical Payment Status Centralizer
 * 
 * Defines and unifies payment status definitions across two distinct domains:
 * 
 * 1. Transaction Status (`transactions.status`):
 *    Represents the operational outcome of a financial payment operation
 *    (e.g., GCash, Maya, Bank Transfer, Manual Treasury Audit).
 *    Paid states: ['paid', 'completed', 'verified', 'settled', 'success', 'approved']
 * 
 * 2. Member Payment Status (`members.payment_status`):
 *    Represents the individual student's membership dues standing.
 *    Paid states: ['paid', 'active', 'completed', 'verified']
 */
class PaymentStatus
{
    /**
     * States indicating a successful/settled financial transaction
     */
    public const TRANSACTION_PAID_STATES = [
        'paid',
        'completed',
        'verified',
        'settled',
        'success',
        'approved'
    ];

    /**
     * States indicating an individual member in good financial standing
     */
    public const MEMBER_PAID_STATES = [
        'paid',
        'active',
        'completed',
        'verified'
    ];

    /**
     * Check if a transaction status represents a successful payment
     *
     * @param string|null $status
     * @return bool
     */
    public static function isTransactionPaid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }
        return in_array(strtolower(trim($status)), self::TRANSACTION_PAID_STATES, true);
    }

    /**
     * Check if a member's payment status indicates paid/good standing
     *
     * @param string|null $status
     * @return bool
     */
    public static function isMemberPaid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }
        return in_array(strtolower(trim($status)), self::MEMBER_PAID_STATES, true);
    }
}
