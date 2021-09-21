<?php

declare(strict_types=1);

namespace kissj\Participant\Guest;

use DateTime;
use kissj\FlashMessages\FlashMessagesBySession;
use kissj\Mailer\PhpMailerWrapper;
use kissj\Participant\Admin\StatisticValueObject;
use kissj\User\User;
use kissj\User\UserService;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

class GuestService
{
    public function __construct(
        private GuestRepository $guestRepository,
        private FlashMessagesBySession $flashMessages,
        private PhpMailerWrapper $mailer,
        private UserService $userService,
    ) {
    }

    public function getGuest(User $user): Guest
    {
        if ($this->guestRepository->countBy(['user' => $user]) === 0) {
            $guest       = new Guest();
            $guest->user = $user;
            $this->guestRepository->persist($guest);
        }

        return $this->guestRepository->findOneBy(['user' => $user]);
    }

    public function addParamsIntoGuest(Guest $guest, array $params): Guest
    {
        $guest->firstName = $params['firstName'] ?? null;
        $guest->lastName  = $params['lastName'] ?? null;
        $guest->nickname  = $params['nickname'] ?? null;
        if ($params['birthDate'] !== null) {
            $guest->birthDate = new DateTime($params['birthDate']);
        }

        $guest->gender          = $params['gender'] ?? null;
        $guest->email           = $params['email'] ?? null;
        $guest->telephoneNumber = $params['telephoneNumber'] ?? null;
        $guest->country         = $params['country'] ?? null;
        /* $guest->setTshirt($params['tshirtShape'] ?? null, $params['tshirtSize'] ?? null); */
        $guest->foodPreferences = $params['foodPreferences'] ?? null;
        $guest->healthProblems  = $params['healthProblems'] ?? null;
        if ($params['arrivalDate'] !== null) {
            $guest->arrivalDate = new DateTime($params['arrivalDate']);
        }

        if ($params['departueDate'] !== null) {
            $guest->departueDate = new DateTime($params['departueDate']);
        }

        $guest->notes = $params['notes'] ?? null;

        return $guest;
    }

    public function isGuestValidForClose(Guest $guest): bool
    {
        if (
            $guest->firstName === null
            || $guest->lastName === null
            || $guest->birthDate === null
            || $guest->gender === null
            || $guest->email === null
            || $guest->telephoneNumber === null
            || $guest->country === null
            || $guest->foodPreferences === null
            || $guest->arrivalDate === null
            || $guest->departueDate === null
            /*|| $guest->getTshirtShape() === null
            || $guest->getTshirtSize() === null*/
        ) {
            return false;
        }

        return empty($guest->email) || filter_var($guest->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function isCloseRegistrationValid(Guest $guest): bool
    {
        if (! $this->isGuestValidForClose($guest)) {
            $this->flashMessages->warning('Cannot lock the registration - some details are wrong or missing (probably email or some date)');

            return false;
        }

        return true;
    }

    public function closeRegistration(Guest $guest): Guest
    {
        if ($this->isCloseRegistrationValid($guest)) {
            $this->userService->closeRegistration($guest->user);
            $this->mailer->sendRegistrationClosed($guest->user);
        }

        return $guest;
    }

    public function getAllGuestsStatistics(): StatisticValueObject
    {
        $ists = $this->guestRepository->findAll();

        return new StatisticValueObject($ists);
    }

    public function openRegistration(Guest $guest, $reason): Guest
    {
        $this->mailer->sendDeniedRegistration($guest, $reason);
        $this->userService->openRegistration($guest->user);

        return $guest;
    }

    public function finishRegistration(Guest $guest): Guest
    {
        $this->userService->payRegistration($guest->user);
        $this->mailer->sendGuestRegistrationFinished($guest);

        return $guest;
    }
}
