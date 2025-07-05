<?php

namespace Mini\Framework\Exceptions\Ignition\Http\Controllers;

use Laminas\Diactoros\Response\EmptyResponse;
use Mini\Framework\Exceptions\Ignition\Exceptions\CannotExecuteSolutionForNonLocalIp;
use Mini\Framework\Exceptions\Ignition\Http\Requests\ExecuteSolutionRequest;
use Mini\Framework\Exceptions\Ignition\Support\RunnableSolutionsGuard;
use Mini\Framework\Routing\Controller;
use Psr\Http\Message\ServerRequestInterface;
use Spatie\Ignition\Contracts\SolutionProviderRepository;

class ExecuteSolutionController extends Controller
{
    public function __invoke(
        ExecuteSolutionRequest $request,
        SolutionProviderRepository $solutionProviderRepository
    ) {
        $this
            ->ensureRunnableSolutionsEnabled()
            ->ensureLocalRequest($request->request);

        $solution = $request->getRunnableSolution();

        $solution->run($request->getQueryParams()['parameters'] ?? []);

        return new EmptyResponse;
    }

    public function ensureRunnableSolutionsEnabled(): self
    {
        // Should already be checked in middleware but we want to be 100% certain.
        abort_unless(RunnableSolutionsGuard::check(), 400);

        return $this;
    }

    public function ensureLocalRequest(ServerRequestInterface $request): self
    {
        $ipIsPublic = filter_var(
            $request->getServerParams()['REMOTE_ADDR'],
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($ipIsPublic) {
            throw CannotExecuteSolutionForNonLocalIp::make();
        }

        return $this;
    }
}
