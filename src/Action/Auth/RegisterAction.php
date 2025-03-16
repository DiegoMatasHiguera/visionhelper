<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegisterAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * POST /auth/register
     * 
     * Registra a un usuario con contraseña, email y un rol, comprobando que no hay otro usuario con el mismo email
     * 
     * @param object $request El body, con los campos "user_email", "password" y "tipo" (Administrador, Elaboración ó Control de Calidad)
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user_email = $data['user_email'] ?? '';
        $password = $data['password'] ?? '';
        $tipo = $data['tipo'] ?? '';  

        if (empty($user_email) || empty($password) || empty($tipo)) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Missing user_email, password o tipo"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

         // Check if the email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
        if ($stmt->fetchColumn() > 0) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_CONFLICT);
            $data = [
                "error" => "User email already exists"
            ];
            return $this->renderer->json($response, $data);
        }

        // If the email does not exist, register the new user        
        $password = password_hash($data['password'], PASSWORD_BCRYPT); // Hashing  
        $stmt = $pdo->prepare("INSERT INTO usuarios (email, contrasena, tipo) VALUES (:user_email, :password, :tipo)");
        $result = $stmt->execute(['user_email' => $user_email, 'password' => $password, 'tipo' => $tipo]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error registering new user"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "User registered successfully"
        ];
        return $this->renderer->json($response, $data);
    }
}
