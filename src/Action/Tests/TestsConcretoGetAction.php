<?php

namespace App\Action\Tests;

use App\Renderer\JsonRenderer;
use App\Domain\Conexion;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestsConcretoGetAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
}

    /**
     * API:
     * POST /tests/{id}
     * 
     * Recupera toda la información de un test concreto (lote, muestreo y producto)
     * 
     * @param array $args Con el campo "id" que es el id del test
     * @param object $request Con los campos "tipo_usuario" y "user_email" para filtrar los tests
     * @return string Un JSON array con los datos de todos los tests disponibles, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $id = (string) $args['id'];
        
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

        // Cogemos el test
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        // El administrador puede ver todos los tests, los usuarios sólo veran los que no estén aceptados ni rechazados, y los que sean exclusivos para ellos
        if ($tipo_usuario === "Administrador") {
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = :id");            
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE estado != 'Aceptado' AND estado != 'Rechazado' AND (exclusivo_de IS NULL OR exclusivo_de = :user_email) AND id = :id");
            $stmt->execute(['user_email' => $user_email, 'id' => $id]);
        }
        $test = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$test) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No se ha encontrado el test o no se tiene acceso a él"
            ];
            return $this->renderer->json($response, $data);
        }


        // Cogemos el tipo de muestreo correspondiente
        // El administrador puede ver todos los muestreos, los usuarios sólo veran los que permitan su rol
        if ($tipo_usuario === "Administrador") {
            $stmt = $pdo->prepare("SELECT * FROM tipos_muestreo WHERE nombre = :nombre_muestreo");        
            $stmt->execute(['nombre_muestreo' => $test[0]['nombre_muestreo']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tipos_muestreo WHERE nombre = :nombre_muestreo AND (exclusivo_de IS NULL OR exclusivo_de = 'Usuario' OR exclusivo_de = :tipo_usuario)");
            $stmt->execute(['nombre_muestreo' => $test[0]['nombre_muestreo'], 'tipo_usuario' => $tipo_usuario]);
        }
        $tipo_muestreo = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$tipo_muestreo) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No se ha encontrado el tipo de muestreo o no se tiene acceso a él"
            ];
            return $this->renderer->json($response, $data);
        }


        // Cogemos el lote correspondiente
        $stmt = $pdo->prepare("SELECT * FROM lotes WHERE id = :lote");
        $stmt->execute(['lote' => $test[0]['lote']]);
        $lote = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$lote) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No se ha encontrado el lote"
            ];
            return $this->renderer->json($response, $data);
        }


        // Cogemos el producto correspondiente
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :codigo_producto");
        $stmt->execute(['codigo_producto' => $lote[0]['codigo_producto']]);
        $producto = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$producto) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "No se ha encontrado el producto"
            ];
            return $this->renderer->json($response, $data);
        }
        

        // Devolvemos toda la información
        $toda_la_info = [
            'test' => $test,
            'tipo_muestreo' => $tipo_muestreo,
            'lote' => $lote,
            'producto' => $producto
        ];

        return $this->renderer->json($response, $toda_la_info);
    }
}
