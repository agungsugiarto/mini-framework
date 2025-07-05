<?php

namespace Mini\Framework\Exceptions\Ignition\Http\Controllers;

use Laminas\Diactoros\Response\JsonResponse;
use Mini\Framework\Exceptions\Ignition\Http\Requests\UpdateConfigRequest;
use Spatie\Ignition\Config\IgnitionConfig;

class UpdateConfigController
{
    public function __invoke(UpdateConfigRequest $request)
    {
        $result = (new IgnitionConfig)->saveValues($request->validated()->toArray());

        return new JsonResponse($result);
    }
}
