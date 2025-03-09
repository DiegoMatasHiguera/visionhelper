<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogoutAllAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
    }

    /**
     * API:
     * POST /auth/logoutAll/
     * 
     * Elimina todos los tokens de refresco, forzando a todos los usuarios a loginearse de nuevo
     * 
     * @param object $request Con header con el campo "tipo" para comprobar si es administrador
     * @return string Un JSON con el "message" de logout conseguido, o el "error" correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $header_tipo = $request->getHeaders()['tipo'][0] ?? '';
        
        if ($header_tipo !== "Administrador") {
            $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $data = [
                "error" => "Unauthorized to force logout all, you need higher privileges"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("DELETE FROM refresh_tokens");
        $stmt->execute();

        $data = [
            "message" => "Forced logout successfully"
        ];
        return $this->renderer->json($response, $data);
    }
}
