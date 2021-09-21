<?php

declare(strict_types=1);

namespace kissj\Payment;

use kissj\Orm\Repository;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function assert;

class PaymentRepository extends Repository
{
    public function isVariableNumberExisting(string $variableNumber): bool
    {
        return $this->isExisting(['variable_symbol' => $variableNumber]);
    }

    /**
     * @return Payment[]
     */
    public function getWaitingPaymentsKeydByVariableSymbols(): array
    {
        $payments = $this->findBy(['status' => Payment::STATUS_WAITING]);

        $finalPayments = [];
        foreach ($payments as $payment) {
            assert($payment instanceof Payment);
            if (array_key_exists($payment->variableSymbol, $finalPayments)) {
                throw new RuntimeException(
                    'More payments with same variable symbol existing: ' . $payment->variableSymbol
                );
            }

            $finalPayments[$payment->variableSymbol] = $payment;
        }

        return $finalPayments;
    }

    public function getDuePayments()
    {
        /** @var Payment[] $waitingPayments */
        $waitingPayments = $this->findBy(['status' => Payment::STATUS_WAITING]);

        return array_filter(
            $waitingPayments,
            static fn (Payment $payment) => $payment->getElapsedPaymentDays() > $payment->getMaxElapsedPaymentDays()
        );
    }
}
