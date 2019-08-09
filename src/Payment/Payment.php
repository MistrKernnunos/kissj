<?php

namespace kissj\Payment;

use kissj\Participants\Participant;
use kissj\User\Role;


/**
 * @property int       $id
 * @property string    $event
 * @property string    $variableSymbol
 * @property string    $price
 * @property string    $currency
 * @property string    $status
 * @property string    $purpose
 * @property string    $accountNumber
 * @property \DateTime $generatedDate m:passThru(dateFromString|dateToString)
 * @property Role      $role          m:hasOne
 */
class Payment extends Participant {

}
