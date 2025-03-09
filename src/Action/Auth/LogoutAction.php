<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogoutAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
    }

    /**
     * API:
     * POST /auth/logout
     * 
     * Elimina un token de refresco, forzando al usuario a loginearse de nuevo
     * 
     * @param object $request Con header con el campo "user_email"
     * @return string Un JSON con el "message" de logout conseguido, o el "error" correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $headers = $request->getHeaders();
        $user_email = $headers['user_email'][0] ?? '';

        if (!$user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Missing user email for logout"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens WHERE user_email = :user_email");
        $stmt->execute(['user_email' => $user_email]);

        $data = [
            "message" => "Logged out successfully"
        ];
        return $this->renderer->json($response, $data);
    }

    public static function logoutInterno($user_email) {
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens WHERE user_email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
    }
}
