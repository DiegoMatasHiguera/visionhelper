<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MuestreoRegistrarAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * POST /tests/registerMuestreo/{id_test}
     * 
     * Registra un esquema de muestreo generado por un usuario para un test
     * 
     * @param array $args Con el campo "id_test" que es el id del test
     * @param object $request El body, en formato JSON
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id_test = (string) $args['id_test'];

        $data = $request->getParsedBody();
        $bandejas = $data['bandejas'] ?? '';

        if (!$bandejas) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Falta el JSON con las bandejas"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();        
        $stmt = $pdo->prepare("UPDATE tests SET estructura_muestreo = :estructura_muestreo WHERE id = :id_test");
        $result = $stmt->execute(['estructura_muestreo' => json_encode($data), 'id_test' => $id_test]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error insertando el muestreo"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "Muestreo para el test registrado correctamente"
        ];
        return $this->renderer->json($response, $data);
    }
}
