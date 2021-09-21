<?php

declare(strict_types=1);

namespace kissj\Mailer;

use kissj\Participant\Participant;
use kissj\Payment\Payment;
use kissj\User\User;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\Views\Twig;
use Throwable;

use function ob_get_clean;
use function ob_start;
use function strip_tags;

class PhpMailerWrapper
{
    private string $eventName;

    public function __construct(private Twig $renderer, private MailerSettings $settings)
    {
        $this->eventName = 'Korbo 2020'; // TODO make dynamic
    }

    public function sendLoginToken(User $user, string $link): void
    {
        $this->sendMailFromTemplate(
            $user->email,
            'odkaz pro přihlášení', // TODO make translatable
            'login-token',
            ['link' => $link, 'event' => $user->event]
        );
    }

    public function sendRegistrationClosed(User $user): void
    {
        $this->sendMailFromTemplate($user->email, 'registrace uzamčena', 'closed', []);
    }

    public function sendDeniedRegistration(Participant $participant, string $reason): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'zamítnutá registrace', // TODO make translatable
            'denial',
            ['reason' => $reason, 'event' => $participant->user->event]
        );
    }

    public function sendRegistrationApprovedWithPayment(Participant $participant, Payment $payment): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'informace o platbě', // TODO make translatable
            'payment-info',
            [
                'event' => $participant->user->event,
                'participant' => $participant,
                'payment' => $payment,
            ]
        );
    }

    public function sendGuestRegistrationFinished(Participant $participant): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'registrace dokončena', // TODO make translatable
            'finished',
            [
                'event' => $participant->user->event,
                'participant' => $participant,
            ]
        );
    }

    public function sendCancelledPayment(Participant $participant, string $reason): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'platba zrušena', // TODO make translatable
            'cancel-payment',
            ['reason' => $reason, 'event' => $participant->user->event]
        );
    }

    public function sendRegistrationPaid(Participant $participant): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'platba úspěšně zaplacena!', // TODO make translatable
            'payment-successful',
            ['event' => $participant->user->event]
        );
    }

    public function sendPaymentTransferedFromYou(Participant $participant): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'platba převedena na jiného účastníka',
            'payment-transfered-from-you',
            []
        );
    }

    public function sendWelcomeFreeParticipantMessage(Participant $participant): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'registrace potvrzena', // TODO make translatable
            'welcome-message-free-participant',
            ['event' => $participant->user->event]
        );
    }

    public function sendDuePaymentDenied(Participant $participant): void
    {
        $this->sendMailFromTemplate(
            $participant->user->email,
            'platba neobdržena -> registrace zrušena', // TODO make translatable
            'cancel-payment',
            ['reason' => 'neobdrželi jsme tvou platbu v termínu pro zaplacení', 'event' => $participant->user->event]
        );
    }

    private function sendMailFromTemplate(
        string $recipientEmail,
        string $subject,
        string $templateName,
        array $parameters
    ): void {
        $messageBody = $this->renderer->fetch('emails/' . $templateName . '.twig', $parameters);
        $mailer      = new PHPMailer(true);

        try {
            // phpamiler echoing debug, content-length middleware addds length header,
            // thus browser do not redirect, but shows content (debug) of that length
            ob_start();
            $mailer->SMTPDebug = $this->settings->debugOutputLevel; // Enable debug output
            if ($this->settings->smtp) {
                $mailer->isSMTP();
            } else {
                $mailer->isMail();
            }

            if ($this->settings->disableTls) {
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mailer->Host       = $this->settings->smtpServer; // Specify main and backup SMTP servers
            $mailer->Port       = $this->settings->smtpPort; // TCP port to connect to
            $mailer->SMTPAuth   = $this->settings->smtpAuth; // Enable SMTP authentication
            $mailer->Username   = $this->settings->smtpUsername; // SMTP username
            $mailer->Password   = $this->settings->smtpPassword; // SMTP password
            $mailer->SMTPSecure = $this->settings->smtpSecure; // Enable TLS encryption, `ssl` or null also accepted
            $mailer->CharSet    = 'UTF-8';

            //Recipients
            $mailer->setFrom($this->settings->fromMail, $this->settings->fromName);
            if (! empty($this->settings->bccMail)) {
                $mailer->addCC($this->settings->bccMail, $this->settings->bccName);
            }

            if ($this->settings->sendMailToMainRecipient) {
                $mailer->addAddress($recipientEmail);
            }

            // Content
            $mailer->isHTML();
            $mailer->Subject = $this->eventName . ' - ' . $subject;
            $mailer->Body    = $messageBody;
            $mailer->AltBody = strip_tags($messageBody);

            $mailer->send();
        } catch (Throwable $e) {
            throw new Exception('Error sending email', $e->getCode(), $e);
        } finally {
            ob_get_clean();
        }
    }
}
