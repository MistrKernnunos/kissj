<?php

declare(strict_types=1);

namespace kissj\PaymentImport;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

use function array_flip;
use function array_map;
use function count;
use function fclose;
use function fgetcsv;
use function fopen;
use function iconv;

class MoneyS3CSV implements ManualPaymentImporter
{
    protected string $event;

    public function __construct(protected string $file, string $event = '')
    {
        $this->event = $event;
    }

    public function getName(): string
    {
        return 'Money S3 CSV';
    }

    protected function encode($str)
    {
        return iconv('Windows-1250', 'UTF-8', $str);
    }

    /**
     * @return array of kissj\PaymentImport\Payment, array of string
     */
    public function getPayments(): array
    {
        $payments = [];
        $errors   = [];
        if (($handle = fopen($this->file, 'r')) !== false) {
            $header_found = false;
            while (($header = fgetcsv($handle, 0, ';')) !== false) {
                if (count((array) $header) > 0 && $header[0] === 'Detail 1') {
                    $header_found = true;
                    break;
                }
            }

            if (! $header_found || $header === false || count((array) $header) < 35 || $header[0] !== 'Detail 1' || $header[1] !== '0') {
                throw new RuntimeException('File ' . $this->file . ' is not a properly formatted Money S3 CSV.');
            }

            $header = array_map([$this, 'encode'], $header);

            $fields        = array_flip($header);
            $header_length = count($header);

            $vsField              = $fields['Variabilní symbol'];
            $senderNameField      = $fields['Název firmy odběratele'];
            $amountField          = $fields['Celková částka - valuty'];
            $currencyField        = $fields['Měna'];
            $noteForReceiverField = $fields['Popis dokladu'];
            $dateReceivedField    = $fields['Datum platby'];

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count((array) $data) < $header_length || $data[0] !== 'Detail 1' || $data[1] !== '1') {
                    continue;
                }

                try {
                    $data = array_map([$this, 'encode'], $data);

                    $payment                  = new Payment();
                    $payment->event           = $this->event;
                    $payment->variableSymbol  = (int) $data[$vsField];
                    $payment->senderName      = $data[$senderNameField];
                    $payment->senderAccountNr = '';
                    $payment->amount          = (float) $data[$amountField];
                    $payment->currency        = $data[$currencyField];
                    $payment->noteForReceiver = $data[$noteForReceiverField];
                    $payment->dateReceived    = new DateTimeImmutable($data[$dateReceivedField]);
                    $payments[]               = $payment;
                } catch (Throwable) {
                    $errors[] = $data;
                }
            }

            fclose($handle);
        }

        return [$payments, $errors];
    }
}
