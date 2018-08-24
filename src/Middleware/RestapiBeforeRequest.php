<?php

namespace Haibian\Restapi\Middleware;

use App\Http\Requests\Request;
use Closure;
use Haibian\Restapi\Facades\Restapi;

class RestapiBeforeRequest
{
    public function handle($request, Closure $next)
    {

        if (!Restapi::checkServer($request)) {
            return response('Unauthorized.', 401);
        }

        $response = $next($request);
        return $response;
    }
}