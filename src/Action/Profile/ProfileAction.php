<?php

namespace App\Action\Profile;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProfileAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
}

    /**
     * API:
     * GET /profile/{user_email}
     * 
     * Recupera los datos de un usuario (excepto la contraseña y el email)
     * 
     * @param object $request el body, con el campo user_email
     * @return string Un JSON con los datos del usuario, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $user_email = (string) $args['user_email'];
        $header_email = $request->getHeaders()['user_email'][0] ?? '';

        if (!$user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_GATEWAY);
            $data = [
                "error" => "Bad route: enter a email"
            ];
            return $this->renderer->json($response, $data);
        } else if ($header_email !== $user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $data = [
                "error" => "Unauthorized to check this user",
                "your_email" => $header_email,
                "user_email" => $user_email
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT tipo, nombre, fecha_nacimiento, sexo, corr_ocular, fecha_rev_ocular FROM usuarios WHERE email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
        $user = $stmt->fetch();

        if (!$user) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No user with that email was found"
            ];
            return $this->renderer->json($response, $data);
        }

        return $this->renderer->json($response, $user);
    }
}
