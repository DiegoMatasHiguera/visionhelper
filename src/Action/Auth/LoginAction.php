<?php

namespace App\Action\Auth;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;
use App\Domain\JWTCreator;

use Fig\Http\Message\StatusCodeInterface;

use DateTime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginAction
{
    private JsonRenderer $renderer;
    private $settings;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
        $this->settings = require __DIR__ . '/../../../config/settings.php';
    }

    /**
     * API:
     * POST /auth/login
     * 
     * Valida una contraseña de un usuario y genera un token de acceso para ejecutar los APIs endpoints y un token de refresco para generar un nuevo token de acceso
     * 
     * @param object $request El body, con los campos "user_email" y "password"
     * @return string Un JSON con ambos tokens ("access_token" y "refresh_token"), o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user_email = $data['user_email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($user_email) || empty($password)) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Missing user_email or password"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT contrasena, tipo FROM usuarios WHERE email = :user_email");
        $stmt->execute(['user_email' => $user_email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['contrasena'])) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $data = [
                "error" => "User not found or password incorrect"
            ];
            return $this->renderer->json($response, $data);
        }

        $accessToken = JWTCreator::generateAccessToken($user_email, $user['tipo'], $this->settings['jwt']['secret'], $this->settings['jwt']['algorithm']);
        $refreshToken = JWTCreator::generateRefreshToken();

        // Guardar el token de refresco en la base de datos
        $date_expiration = new DateTime('+'.JWTCreator::$refresh_validez.' seconds');
        $stmt = $pdo->prepare("INSERT INTO refresh_tokens (user_email, token, expires_at) VALUES (:user_email, :token, :expires_at)");
        $stmt->execute([
            'user_email' => $user_email,
            'token' => $refreshToken,
            'expires_at' => $date_expiration->format('Y-m-d H:i:s')
        ]);

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
        return $this->renderer->json($response, $data);
    }
}
