<?php

namespace App\Action\Unidades;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UnidadRegisterAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * POST /unidades/register
     * 
     * Registra o actualiza a una unidad
     * 
     * @param object $request El body, con los campos mínimos "id_test", "id_en_muestreo", "usuario_revision", "tiene_particula", "tiempo_invertido" y "fecha_creacion"
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $id_test = $data['id_test'] ?? '';
        $id_en_muestreo = $data['id_en_muestreo'] ?? '';
        $id = $data['id'] ?? $id_test.' | '.$id_en_muestreo;
        $usuario_revision = $data['usuario_revision'] ?? '';
        // Al ser boolean, hay que comprobarlo de forma distinta
        if (!array_key_exists('tiene_particula', $data)) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $error_data = [
                "error" => "Missing required parameter: tiene_particula"
            ];
            return $this->renderer->json($response, $error_data);
        }
        $tiene_particula = $data['tiene_particula'] ? 1 : 0;
        $descripcion = $data['descripcion'] ?? '';
        $tiempo_invertido = $data['tiempo_invertido'] ?? '';
        $campo_vision = $data['campo_vision'] ?? '';
        $fecha_creacion = $data['fecha_creacion'] ?? '';
        $retest_cada = $data['retest_cada'] ?? '';

        if (empty($id_test) || empty($id_en_muestreo) || empty($usuario_revision) || empty($tiempo_invertido) || empty($fecha_creacion)) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Missing id_test, id_en_muestreo, usuario_revision, tiempo_invertido or fecha_creacion"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

        // Si existe, actualizamos la unidad. Y si no, la creamos
        $stmt = $pdo->prepare("REPLACE INTO unidades (id_test, id_en_muestreo, id, usuario_revision, tiene_particula, tiempo_invertido, fecha_creacion, descripcion, campo_vision, retest_cada) 
            VALUES (:id_test, :id_en_muestreo, :id, :usuario_revision, :tiene_particula, :tiempo_invertido, :fecha_creacion, :descripcion, :campo_vision, :retest_cada)");
        $result = $stmt->execute(['id_test' => $id_test, 'id_en_muestreo' => $id_en_muestreo, 'id' => $id, 'usuario_revision' => $usuario_revision, 'tiene_particula' => $tiene_particula, 'tiempo_invertido' => $tiempo_invertido, 'fecha_creacion' => $fecha_creacion, 'descripcion' => (empty($descripcion) ? NULL : $descripcion), 'campo_vision' => (empty($campo_vision) ? NULL : $campo_vision), 'retest_cada' => (empty($retest_cada) ? NULL : $retest_cada)]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error registering new unidad"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "Unidad registered successfully"
        ];
        return $this->renderer->json($response, $data);
    }
}
