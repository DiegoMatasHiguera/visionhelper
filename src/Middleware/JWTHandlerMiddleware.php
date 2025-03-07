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
 * JwtHandlerMiddleware
 *
 * Genera y valida tokens JWT para autenticarse en las solicitudes de APIs
 */
final class JwtHandlerMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;    
    private JsonRenderer $renderer;
    private $settings;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        JsonRenderer $jsonRenderer,
        $settings
    ) {
        $this->responseFactory = $responseFactory;
        $this->renderer = $jsonRenderer;
        $this->settings = $settings;
    }

    /**
     * Middleware para validar un token de acceso
     *
     * @return  string  Un JSON con el email del usuario ("user_email"), o el error correspondiente
    */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $headers = $request->getHeaders();
        $accessToken = $headers['access_token'][0] ?? '';

        if (!$accessToken) {
            $response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $response = $response->withAddedHeader('Content-Type', 'application/json');
            $data = [
                "error" => "Token not found"
            ];
            return $this->renderer->json($response, $data);
        }
        
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

        $user_email = '';
        try {
            $decode = JWT::decode($accessToken, new Key($this->settings["secret"], $this->settings["algorithm"]));
            $user_email = $decode->sub;           
        } catch (Exception $e) {
            // Comprobar si ha expirado el access token, y generar otro si el refresh token sigue siendo válido
            if ($e->getMessage() === "Expired token") {
                $data = [
                    "error1" => "Access token expired"
                ];

                $refreshToken = $headers['refresh_token'][0] ?? '';

                if (empty($refreshToken)) {
                    $response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_BAD_REQUEST);
                    $response = $response->withAddedHeader('Content-Type', 'application/json');
                    $data += [
                        "error2" => "Missing refresh token"
                    ];
                    return $this->renderer->json($response, $data);
                }

                $stmt = $pdo->prepare("SELECT user_email FROM refresh_tokens WHERE token = :token AND expires_at > NOW()");
                $stmt->execute(['token' => $refreshToken]);
                $row = $stmt->fetch();
        
                if (!$row) {
                    $response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_UNAUTHORIZED);
                    $response = $response->withAddedHeader('Content-Type', 'application/json');
                    $data += [
                        "error2" => "Invalid or expired refresh token"
                    ];
                    return $this->renderer->json($response, $data);
                } else {
                    $user_email = $row['user_email'];
                }
        
                $newAccessToken = JWTCreator::generateAccessToken($user_email, $this->settings["secret"], $this->settings["algorithm"]);
                // Almacenar el nuevo access token en el header
                $request = $request->withAddedHeader('access_token', $newAccessToken);
            } else {
                $response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_UNAUTHORIZED);
                $response = $response->withAddedHeader('Content-Type', 'application/json');
                $data = [
                    "error" => "Token not valid"
                ];
                return $this->renderer->json($response, $data);
            }
        }
        
        // Comprobar si el email del token se corresponde con alguno de la base de datos
        $stmt = $pdo->prepare("SELECT tipo, nombre FROM usuarios WHERE email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
        $user = $stmt->fetch();

        if (!$user) {
            $response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_NOT_FOUND);
            $response = $response->withAddedHeader('Content-Type', 'application/json');
            $data = [
                "error" => "User declared in access token is not found in database"
            ];
            return $this->renderer->json($response, $data);
        }

        // Continuar con el siguiente middleware
        return $handler->handle($request);
    }
}
