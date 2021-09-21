<?php

declare(strict_types=1);

namespace kissj\Middleware;

use kissj\User\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as ResponseHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function assert;

class OpenStatusOnlyMiddleware extends BaseMiddleware
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function process(Request $request, ResponseHandler $handler): Response
    {
        $user = $request->getAttribute('user');
        assert($user instanceof User);
        if ($user->status !== User::STATUS_OPEN) {
            $this->logger->warning(
                'User ' . $user->email . ' is trying to change data, even he has role "' . $user->status . '"'
            );

            throw new RuntimeException('You cannot change your data when you are not in editing status');
        }

        return $handler->handle($request);
    }
}
