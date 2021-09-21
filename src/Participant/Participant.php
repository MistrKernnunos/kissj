<?php

declare(strict_types=1);

namespace kissj\Participant;

use DateTime;
use kissj\Orm\EntityDatetime;
use kissj\Payment\Payment;
use kissj\User\User;

use function assert;
use function explode;
use function implode;

/**
 * Master table for all participants, using Single Table Inheritance
 * All commons are here, entitis are seaprated of course (:
 *
 * @property int            $id
 * @property User|null      $user         m:hasOne
 * @property string|null    $role         needed for DB working (see Mapper.php)
 * @property string|null    $firstName
 * @property string|null    $lastName
 * @property string|null    $nickname
 * @property string|null    $permanentResidence
 * @property string|null    $telephoneNumber
 * @property string|null    $gender
 * @property string|null    $country
 * @property string|null    $email
 * @property string|null    $scoutUnit
 * @property string|null    $languages
 * @property string|null    $birthDate    m:passThru(dateFromString|dateToString)
 * @property string|null    $birthPlace
 * @property string|null    $healthProblems
 * @property string|null    $foodPreferences
 * @property string|null    $idNumber
 * @property string|null    $scarf
 * @property string|null    $swimming
 * @property string|null    $tshirt       m:useMethods
 * @property string|null    $arrivalDate  m:passThru(dateFromString|dateToString)
 * @property string|null    $departueDate m:passThru(dateFromString|dateToString)
 * @property string|null    $uploadedFilename
 * @property string|null    $uploadedOriginalFilename
 * @property string|null    $uploadedContenttype
 * @property string|null    $notes
 * @property Payment[]|null $payment m:belongsToMany
 */
class Participant extends EntityDatetime
{
    protected ?string $tshirtSize  = null;
    protected ?string $tshirtShape = null;

    protected const TSHIRT_DELIMITER = '-';

    public const FOOD_OTHER = 'other';
    public const SCARF_NO   = 'no';
    public const SCARF_YES  = 'yes';

    public function setUser(User $user): void
    {
        // else  LeanMapper \ Exception \ MemberAccessException
        // Cannot write to read-only property 'user' in entity kissj\Participant\Participant.
        $this->row->user_id = $user->id;
        $this->row->cleanReferencedRowsCache('user', 'user_id');
    }

    public function getTshirt(): ?string
    {
        return $this->row->tshirt;
    }

    public function setTshirt(?string $shape, ?string $size): void
    {
        $this->row->tshirt = implode(self::TSHIRT_DELIMITER, [$shape, $size]);
        $this->tshirtSize  = $size;
        $this->tshirtShape = $shape;
    }

    public function getTshirtShape(): ?string
    {
        return $this->getTshirtParsed()[0] ?? null;
    }

    public function getTshirtSize(): ?string
    {
        return $this->getTshirtParsed()[1] ?? null;
    }

    protected function getTshirtParsed(): array
    {
        $tshirtFromDb = $this->getTshirt();

        return explode(self::TSHIRT_DELIMITER, $tshirtFromDb);
    }

    public function getFullName(): string
    {
        return ($this->firstName ?? '') . ' ' . ($this->lastName ?? '') . ($this->nickname ? ' - ' . $this->nickname : '');
    }

    public function getAgeInYears(DateTime $eventStart): ?int
    {
        $birthDate = $this->getBirthDate();
        assert($birthDate instanceof DateTime);
        if ($birthDate === null) {
            return null;
        }

        return $birthDate->diff($eventStart)->format('%y');
    }

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->payment;
    }
}
