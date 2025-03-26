<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;
use App\Domain\TestService;

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

        if ($estado == 'Aceptado') {                
            // Get info del test actual
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = :id_test");
            $stmt->execute(['id_test' => $id_test]);
            $test_actual = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $test_actual = $test_actual[0];
            // Si el test tiene programado la generación de otro test una vez aceptado, lo generamos
            $stmt = $pdo->prepare("SELECT genera FROM tipos_muestreo WHERE nombre = :nombre_muestreo");
            $stmt->execute(['nombre_muestreo' => $test_actual['nombre_muestreo']]);
            $generar_test = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($generar_test[0]["genera"]) {
                // Get info del tipo de muestreo xdel test a generar
                $stmt = $pdo->prepare("SELECT exclusivo_de, plazo FROM tipos_muestreo WHERE nombre = :nombre_muestreo");
                $stmt->execute(['nombre_muestreo' => $generar_test[0]["genera"]]);
                $tipo_muestreo_nuevo = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $tipo_muestreo_nuevo = $tipo_muestreo_nuevo[0];

                $test_exclusivo_de = '';
                if ($tipo_muestreo_nuevo['exclusivo_de'] == 'Usuario') {
                    $test_exclusivo_de = $header_user_email;
                }

                // Generar el nuevo test
                $testService = new TestService($pdo);
                $newTestData = [
                    'lote' => $test_actual['lote'],
                    'nombre_muestreo' => $generar_test[0]["genera"],
                    'fecha_creacion' => date('Y-m-d'), // current date
                    'estado' => 'Nuevo',
                    'exclusivo_de' => $test_exclusivo_de
                ];
                
                $testService->createTest($newTestData);
            }
        }

        $data = [
            'message' => "Cambio correcto del estado del test"
        ];
        return $this->renderer->json($response, $data);
    }
}
