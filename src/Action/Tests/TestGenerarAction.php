<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;
use App\Domain\TestService;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use App\Domain\Test;

final class TestGenerarAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * POST /tests/generar
     * 
     * Genera un nuevo test
     * 
     * @param object $request El body con el test a generar, en formato JSON
     * @return string Un JSON con el mensaje de error o éxito correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $header_tipo = $request->getHeaders()['tipo'][0] ?? '';
        $header_user_email = $request->getHeaders()['user_email'][0] ?? '';

        $data = $request->getParsedBody();

        if ($data['lote'] == null || $data['nombre_muestreo'] == null || $data['fecha_creacion'] == null || $data['estado'] == null) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Faltan datos en el JSON del test ('lote', 'nombre_muestreo', 'fecha_creacion' o 'estado')"
            ];
            return $this->renderer->json($response, $data);
        }

        // Si no eres el mismo usuario, necesitas ser administrador
        if ($header_tipo !== "Administrador") {
            $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
            $data = [
                "error" => "Unauthorized to create a test, you need higher privileges"
            ];
            return $this->renderer->json($response, $data);
        }

        // Cargamos los datos de usuario para modificarlos      
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $testService = new TestService($pdo);

        $resultCreateTest = $testService->createTest($data);

        if (!$resultCreateTest['success']) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "Error creating test:" . $resultCreateTest['error']
            ];
            return $this->renderer->json($response, $data);
        }
        
        $data = [
            'message' => "Creación correcta del test"
        ];
        return $this->renderer->json($response, $data);
    }
}
