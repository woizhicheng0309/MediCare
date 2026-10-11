<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use L5Swagger\GeneratorFactory;
use Symfony\Component\HttpFoundation\Response;

// Match the Demo: generate documentation when first requested, not at service startup.
class GenerateSwaggerDocsIfMissing
{
    public function __construct(private readonly GeneratorFactory $generatorFactory) {}

    public function handle(Request $request, Closure $next): Response
    {
        $config = $request->offsetGet('config');
        $file = $config['paths']['docs'].'/'.$config['paths']['docs_json'];
        if (! is_file($file)) {
            $this->generatorFactory->make($request->offsetGet('documentation'))->generateDocs();
        }

        return $next($request);
    }
}
