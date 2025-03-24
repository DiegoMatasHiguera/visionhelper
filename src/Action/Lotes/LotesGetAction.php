<?php

namespace App\Action\Lotes;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LotesGetAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
}

    /**
     * API:
     * POST /lotes/
     * 
     * Recupera la información de los ids de lote suministrados
     * 
     * @param object $request Con el campo "lotes" que es un array con los ids de los lotes.
     * @return string Un JSON array con los datos de todos esos productos, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        // Get the request body as parsed array
        $data = $request->getParsedBody();
        $id_lotes = $data['lotes'] ?? null;
    
        if (!$id_lotes || !is_array($id_lotes) || empty($id_lotes)) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
            $data = [
                "error" => "Bad request: proporcione un array con los ids de los lotes"
            ];
            return $this->renderer->json($response, $data);
        }
    
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();

        // Creamos un array de placeholders (?) del mismo tamaño que el array de IDs
        $placeholders = implode(',', array_fill(0, count($id_lotes), '?'));
        
        $stmt = $pdo->prepare("SELECT * FROM lotes WHERE id IN ($placeholders)");
        
        // Metemos los valores de los IDs en el statement. Todo este lío lo hacemos para evitar SQL Injection
        foreach ($id_lotes as $index => $id) {
            $stmt->bindValue($index + 1, $id);
        }
        
        $stmt->execute();
        $lotes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
        return $this->renderer->json($response, $lotes);
    }
}
