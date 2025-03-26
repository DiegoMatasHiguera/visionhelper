<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestsGetAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
}

    /**
     * API:
     * POST /tests/
     * 
     * Recupera los tests disponibles para un tipo de usuario o un usuario específico
     * 
     * @param object $request Con los campos "tipo_usuario" y "user_email" para filtrar los tests
     * @return string Un JSON array con los datos de todos los tests disponibles, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $tipo_usuario = $data['tipo_usuario'] ?? null;
        $user_email = $data['user_email'] ?? null;

        if (!$tipo_usuario && !$user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_GATEWAY);
            $data = [
                "error" => "Bad route: Introduzca el tipo de usuario o el email del usuario"
            ];
            return $this->renderer->json($response, $data);
        }

        // Cogemos los tests
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        // El administrador puede ver todos los tests, los usuarios sólo veran los que sean exclusivos para ellos
        if ($tipo_usuario === "Administrador") {
            $stmt = $pdo->prepare("SELECT * FROM tests");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE (exclusivo_de IS NULL OR exclusivo_de = :user_email)");
            $stmt->execute(['user_email' => $user_email]);
        }
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Comprobamos si hay algún examen pasado de fecha
        $fecha_actual = date('Y-m-d');
        foreach ($tests as $test) {
            if (str_contains($test['nombre_muestreo'], "Examen") && $test['fecha_objetivo'] < $fecha_actual && $test['estado'] != "Aceptado") {
                $stmt = $pdo->prepare("UPDATE usuarios SET cualificado = 0 WHERE email = :user_email");
                $stmt->execute(['user_email' => $user_email]);
                break;
            }
        }

        // Cogemos los tipos de muestreo correspondientes
        $stmt = $pdo->prepare("SELECT * FROM tipos_muestreo");
        $stmt->execute();
        $tipos_muestreo = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$tipos_muestreo) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No se han creado tipos de muestreo"
            ];
            return $this->renderer->json($response, $data);
        }

        // Filtramos los que pueden ver dependiendo del rol
        $tests_filtrados = [];
        // El administrador puede ver todos los tests
        if ($tipo_usuario === "Administrador") {
            $tests_filtrados = $tests;
        } else {
            foreach ($tests as $test) {
                foreach ($tipos_muestreo as $tipo_muestreo) {
                    if ($test['nombre_muestreo'] === $tipo_muestreo['nombre']) {
                        if ($tipo_muestreo['exclusivo_de'] === "" || $tipo_muestreo['exclusivo_de'] === $tipo_usuario || $tipo_muestreo['exclusivo_de'] === "Usuario") {                            
                            $tests_filtrados[] = $test;
                        }
                        break;
                    }
                }                
            }
        }

        return $this->renderer->json($response, $tests_filtrados);
    }
}
