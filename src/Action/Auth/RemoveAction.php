<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RemoveAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * DELETE /auth/remove/{user_email}
     * 
     * Elimina a un usuario base, comprobando previamente que el ejecutor es administrador
     * 
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
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
            if ($header_tipo !== "administrador") {
                $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
                $data = [
                    "error" => "Unauthorized to remove this user, you need higher privileges",
                    "your_email" => $header_email,
                    "user_email" => $user_email
                ];
                return $this->renderer->json($response, $data);
            }
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

         // Check if the email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
        if ($stmt->fetchColumn() <= 0) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "User email does not exist"
            ];
            return $this->renderer->json($response, $data);
        }

        // If the email exist, remove the user        
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE email = :user_email");
        $result = $stmt->execute(['user_email' => $user_email]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error removing the user"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "User removed successfully"
        ];
        return $this->renderer->json($response, $data);
    }
}
