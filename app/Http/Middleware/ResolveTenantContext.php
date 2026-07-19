<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\ResolvesTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        protected ResolvesTenant $resolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->resolver->resolve($request->user());

        return $next($request);
    }
}
