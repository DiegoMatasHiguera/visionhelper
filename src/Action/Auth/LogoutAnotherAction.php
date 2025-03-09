<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogoutAnotherAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
    }

    /**
     * API:
     * POST /auth/logout/{user_email}
     * 
     * Elimina un token de refresco, forzando a otro usuario a loginearse de nuevo
     * 
     * @param object $request Con header con el campo "tipo" para comprobar si es administrador
     * @return string Un JSON con el "message" de logout conseguido, o el "error" correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $user_email = (string) $args['user_email'];
        $header_email = $request->getHeaders()['user_email'][0] ?? '';
        $header_tipo = $request->getHeaders()['tipo'][0] ?? '';

        if (!$user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_GATEWAY);
            $data = [
                "error" => "Bad route: enter a email"
            ];
            return $this->renderer->json($response, $data);
        } else if ($header_email !== $user_email) {            
            // Si no eres el mismo usuario, necesitas ser administrador
            if ($header_tipo !== "Administrador") {
                $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
                $data = [
                    "error" => "Unauthorized to force logout this user, you need higher privileges",
                    "your_email" => $header_email,
                    "user_email" => $user_email
                ];
                return $this->renderer->json($response, $data);
            }
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens WHERE user_email = :user_email");
        $stmt->execute(['user_email' => $user_email]);

        $data = [
            "message" => "Forced logout successfully"
        ];
        return $this->renderer->json($response, $data);
    }
}
