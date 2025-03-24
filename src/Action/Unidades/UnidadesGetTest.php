<?php

namespace App\Action\Unidades;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UnidadesGetTest
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;
    }

    /**
     * API:
     * GET /unidades/{id_test}
     * 
     * Recolecta todas las unidades de un test
     * 
     * @param array $args Con el campo "id_test" que es el id del test
     * @return string Un JSON con las unidades del test, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id_test = (string) $args['id_test'];

        if (empty($id_test)) {            
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Missing id_test"
            ];
            return $this->renderer->json($response, $data);
        }

        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

        // Si existe, actualizamos la unidad. Y si no, la creamos
        $stmt = $pdo->prepare("SELECT * FROM unidades WHERE id_test = :id_test ORDER BY id_en_muestreo DESC");
        $result = $stmt->execute(['id_test' => $id_test]);
        if (!$result) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error retrieving unidades"
            ];
            return $this->renderer->json($response, $data);
        }

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->renderer->json($response, $data);
    }
}
