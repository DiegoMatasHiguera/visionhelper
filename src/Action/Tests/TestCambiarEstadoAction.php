<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestCambiarEstadoAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * POST /tests/cambiarEstado/{id_test}
     * 
     * Cambia el estado de un test
     * 
     * @param array $args Con el campo "id_test" que es el id del test
     * @param object $request El body con "estado" y el estado correspondiente ('Nuevo','Muestrando','Visualizando','Disponible','Bloqueado','Aceptado','Rechazado') y la fecha fin (aplica solo a los estados Aceptado y Rechazado)
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id_test = (string) $args['id_test'];
        
        $header_tipo = $request->getHeaders()['tipo'][0] ?? '';
        $header_user_email = $request->getHeaders()['user_email'][0] ?? '';

        $data = $request->getParsedBody();
        $estado = $data['estado'] ?? '';
        $fecha_fin = $data['fecha_fin'] ?? '';

        if (!$estado) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Falta el JSON con el estado"
            ];
            return $this->renderer->json($response, $data);
        } else if (!in_array($estado, ['Nuevo','Muestrando','Visualizando','Disponible','Bloqueado','Aceptado','Rechazado'])) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "El estado no es válido"
            ];
            return $this->renderer->json($response, $data);
        } else if (($estado == 'Nuevo' || $estado == 'Bloqueado') && $header_tipo != 'Administrador') {
            $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $data = [
                "error" => "No tienes permisos para cambiar el estado a Nuevo o Bloqueado"
            ];
            return $this->renderer->json($response, $data);
        } else if (($estado == 'Aceptado' || $estado == 'Rechazado') && !$fecha_fin) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Falta la fecha de fin"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $stmt = $pdo->prepare("UPDATE tests SET estado = :estado, ult_usuario = :ult_usuario, fecha_fin = :fecha_fin WHERE id = :id_test");
        $result = $stmt->execute(['estado' => $estado, 'ult_usuario' => $header_user_email, 'fecha_fin' => (empty($fecha_fin) ? NULL : $fecha_fin), 'id_test' => $id_test]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error modificando el estado"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "Cambio correcto del estado del test"
        ];
        return $this->renderer->json($response, $data);
    }
}
