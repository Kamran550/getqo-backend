<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyFunctionToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string)config('services.function_token');
        $provided = (string)($request->bearerToken() ?: $request->input('token'));

        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
