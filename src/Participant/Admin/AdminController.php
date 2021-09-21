<?php

declare(strict_types=1);

namespace kissj\Participant\Admin;

use DateTimeImmutable;
use kissj\AbstractController;
use kissj\BankPayment\BankPayment;
use kissj\BankPayment\BankPaymentRepository;
use kissj\BankPayment\FioBankPaymentService;
use kissj\Event\ContentArbiterFreeParticipant;
use kissj\Event\ContentArbiterGuest;
use kissj\Event\ContentArbiterIst;
use kissj\Event\ContentArbiterPatrolLeader;
use kissj\Event\ContentArbiterPatrolParticipant;
use kissj\Participant\Guest\GuestService;
use kissj\Participant\Ist\IstService;
use kissj\Participant\ParticipantService;
use kissj\Participant\Patrol\PatrolService;
use kissj\Payment\PaymentRepository;
use kissj\Payment\PaymentService;
use kissj\User\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

use function htmlspecialchars;

use const ENT_QUOTES;

class AdminController extends AbstractController
{
    public function __construct(
        private ParticipantService $participantService,
        private PaymentService $paymentService,
        private PaymentRepository $paymentRepository,
        private BankPaymentRepository $bankPaymentRepository,
        private FioBankPaymentService $bankPaymentService,
        private PatrolService $patrolService,
        private IstService $istService,
        private GuestService $guestService,
        private AdminService $adminService,
        private ContentArbiterPatrolLeader $contentArbiterPatrolLeader,
        private ContentArbiterPatrolParticipant $contentArbiterPatrolParticipant,
        private ContentArbiterIst $contentArbiterIst,
        private ContentArbiterFreeParticipant $contentArbiterFreeParticipant,
        private ContentArbiterGuest $contentArbiterGuest,
    ) {
    }

    public function showDashboard(Response $response): Response
    {
        return $this->view->render(
            $response,
            'admin/dashboard-admin.twig',
            [
                'patrols' => $this->patrolService->getAllPatrolsStatistics(),
                'ists' => $this->istService->getAllIstsStatistics(),
                'guests' => $this->guestService->getAllGuestsStatistics(),
            ]
        );
    }

    public function showApproving(
        Response $response,
        ParticipantService $participantService
    ): Response {
        return $this->view->render($response, 'admin/approving-admin.twig', [
            'closedPatrolLeaders' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_PATROL_LEADER, USER::STATUS_CLOSED),
            'closedIsts' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_IST, USER::STATUS_CLOSED),
            'closedFreeParticipants' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_FREE_PARTICIPANT, USER::STATUS_CLOSED),
            'closedGuests' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_GUEST, USER::STATUS_CLOSED),
            'caIst' => $this->contentArbiterIst,
            'caPl' => $this->contentArbiterPatrolLeader,
            'caPp' => $this->contentArbiterPatrolParticipant,
            'caFp' => $this->contentArbiterFreeParticipant,
            'caGuest' => $this->contentArbiterGuest,
        ]);
    }

    public function showPayments(
        Response $response,
        ParticipantService $participantService
    ): Response {
        return $this->view->render($response, 'admin/payments-admin.twig', [
            'approvedPatrolLeaders' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_PATROL_LEADER, USER::STATUS_APPROVED),
            'approvedIsts' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_IST, USER::STATUS_APPROVED),
            'approvedFreeParticipants' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_FREE_PARTICIPANT, USER::STATUS_APPROVED),
            'approvedGuests' => $participantService
                ->getAllParticipantsWithStatus(User::ROLE_GUEST, USER::STATUS_APPROVED),
        ]);
    }

    public function showCancelPayment(int $paymentId, Response $response): Response
    {
        $payment = $this->paymentRepository->find($paymentId);

        return $this->view->render($response, 'admin/cancelPayment-admin.twig', ['payment' => $payment]);
    }

    public function cancelPayment(int $paymentId, Request $request, Response $response): Response
    {
        $reason = htmlspecialchars($request->getParsedBody()['reason'], ENT_QUOTES);

        $payment = $this->paymentRepository->find($paymentId);
        $this->participantService->cancelPayment($payment, $reason);
        $this->flashMessages->info($this->translator->trans('flash.info.paymentCanceled'));
        $this->logger->info('Cancelled payment ID ' . $paymentId . ' for participant with reason: ' . $reason);

        return $this->redirect(
            $request,
            $response,
            'admin-show-payments',
            ['eventSlug' => $payment->participant->user->event->slug]
        );
    }

    public function cancelAllDuePayments(Request $request, Response $response): Response
    {
        $this->paymentService->cancelDuePayments(5);

        return $this->redirect(
            $request,
            $response,
            'admin-show-payments',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }

    public function confirmPayment(int $paymentId, Request $request, Response $response): Response
    {
        $payment = $this->paymentRepository->find($paymentId);
        $this->paymentService->confirmPayment($payment);
        $this->flashMessages->success($this->translator->trans('flash.success.comfirmPayment'));
        $this->logger->info('Payment ID ' . $paymentId . ' manually confirmed as paid');

        return $this->redirect(
            $request,
            $response,
            'admin-show-payments',
            ['eventSlug' => $payment->participant->user->event->slug]
        );
    }

    public function showFile(string $filename)
    {
        $file     = $this->fileHandler->getFile($filename);
        $response = new \Slim\Psr7\Response(200, null, $file->stream);
        $response = $response->withAddedHeader('Content-Type', $file->mimeContentType);

        return $response;
    }

    public function showAutoPayments(Response $response): Response
    {
        $arguments = [
            'bankPayments' => $this->bankPaymentRepository->findBy([], ['id' => false]),
            'bankPaymentsTodo' => $this->bankPaymentRepository->findBy(
                ['status' => BankPayment::STATUS_UNKNOWN],
                ['id' => false]
            ),
        ];

        return $this->view->render($response, 'admin/paymentsAuto-admin.twig', $arguments);
    }

    public function setBreakpointFromRoute(Request $request, Response $response): Response
    {
        $result = $this->bankPaymentService->setBreakpoint(new DateTimeImmutable('2020-05-31'));

        if ($result) {
            $this->flashMessages->success('Set breakpoint successfully');
        } else {
            $this->flashMessages->error('Something gone wrong, probably unvalid token :(');
        }

        return $this->redirect(
            $request,
            $response,
            'admin-show-auto-payments',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }

    public function updatePayments(Request $request, Response $response): Response
    {
        $this->paymentService->updatePayments(5);

        return $this->redirect(
            $request,
            $response,
            'admin-show-auto-payments',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }

    public function markBankPaymentPaired(Request $request, Response $response, int $paymentId): Response
    {
        $notice = htmlspecialchars($request->getParsedBody()['notice'], ENT_QUOTES);
        $this->bankPaymentService->setBankPaymentPaired($paymentId);
        $this->logger->info('Payment with ID ' . $paymentId . ' has been marked as paired with notice: ' . $notice);
        $this->flashMessages->info($this->translator->trans('flash.info.markedAsPaired'));

        return $this->redirect(
            $request,
            $response,
            'admin-show-auto-payments',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }

    public function markBankPaymentUnrelated(Request $request, Response $response, int $paymentId): Response
    {
        $this->bankPaymentService->setBankPaymentUnrelated($paymentId);
        $this->logger->info('Payment with ID ' . $paymentId . ' has been marked as unrelated');
        $this->flashMessages->info($this->translator->trans('flash.info.markedAsUnrelated'));

        return $this->redirect(
            $request,
            $response,
            'admin-show-auto-payments',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }

    public function showTransferPayment(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $emailFrom = $queryParams['emailFrom'];
        $emailTo   = $queryParams['emailTo'];

        $participantFrom = $this->participantService->findParticipantFromUserMail($emailFrom);
        $participantTo   = $this->participantService->findParticipantFromUserMail($emailTo);

        return $this->view->render($response, 'admin/transferPayment-admin.twig', [
            'emailFrom' => $emailFrom,
            'emailTo' => $emailTo,
            'from' => $participantFrom,
            'to' => $participantTo,
            'transferPossible' => $this->adminService->isPaymentTransferPossible(
                $participantFrom,
                $participantTo,
                $this->flashMessages
            ),
        ]);
    }

    public function transferPayment(Request $request, Response $response): Response
    {
        $queryParams = $request->getParsedBody();

        $participantFrom = $this->participantService->findParticipantFromUserMail($queryParams['emailFrom']);
        $participantTo   = $this->participantService->findParticipantFromUserMail($queryParams['emailTo']);

        // TODO refactor findParticipantFromUserMail into get method
        if ($participantFrom === null || $participantTo === null) {
            throw new RuntimeException('Found no participant');
        }

        if (
            ! $this->adminService->isPaymentTransferPossible(
                $participantFrom,
                $participantTo,
                $this->flashMessages
            )
        ) {
            throw new RuntimeException('Cannot do transfer');
        }

        $this->adminService->transferPayment($participantFrom, $participantTo);
        $this->flashMessages->success($this->translator->trans('flash.success.transfer'));

        return $this->redirect(
            $request,
            $response,
            'admin-dashboard',
            ['eventSlug' => $request->getAttribute('user')->event->slug]
        );
    }
}
