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
     * GET /tests/{tipo_usuario}
     * 
     * Recupera los tests disponibles para un tipo de usuario
     * 
     * @args Con path query "tipo_usuario" para comprobar el tipo de usuario
     * @return string Un JSON array con los datos de todos los tests disponibles, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $tipo_usuario = (string) $args['tipo_usuario'];

        if (!$tipo_usuario) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_GATEWAY);
            $data = [
                "error" => "Bad route: Introduzca el tipo de usuario"
            ];
            return $this->renderer->json($response, $data);
        }

        // Cogemos los tests
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        // El administrador puede ver todos los tests, los usuarios sólo veran los que no estén aceptados ni rechazados
        if ($tipo_usuario === "Administrador") {
            $stmt = $pdo->prepare("SELECT * FROM tests");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE estado != 'Aceptado' AND estado != 'Rechazado'");
        }
        $stmt->execute();
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
                        if ($tipo_muestreo['exclusivo_de'] === "" || $tipo_muestreo['exclusivo_de'] === $tipo_usuario) {                            
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
