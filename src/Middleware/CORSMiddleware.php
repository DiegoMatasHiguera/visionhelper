<?php

namespace App\Middleware;


use App\Renderer\JsonRenderer;
use App\Domain\Conexion;
use App\Domain\JWTCreator;
use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Exception;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * CORS Middleware
 *
 * Permite el acceso a la API desde cualquier origen
 */
final class CORSMiddleware implements MiddlewareInterface
{

    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory) {
        $this->responseFactory = $responseFactory;
    }

    /**
     * 
     * @return  string  Añade headers a la respuesta para permitir el acceso a la API desde cualquier origen
    */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse();
        } else {
            $response = $handler->handle($request);
        }
    
        $response = $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', '*')
            // Necesario para que el navegador permita leer nuestros headers personalizados de la respuesta:
            ->withHeader('Access-Control-Expose-Headers', 'user_email, user_name, tipo')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    
        if (ob_get_contents()) {
            ob_clean();
        }
    
        return $response;
    }
}
