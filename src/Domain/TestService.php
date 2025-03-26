<?php

namespace App\Domain;

use App\Domain\Test;
use PDO;

class TestService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new test with the provided data
     * 
     * @param array $data Test data
     * @return array Result with success status and error message if any
     */
    public function createTest(array $data): array
    {
        $test = new Test($this->pdo);

        $test->lote = $data['lote'];
        $test->nombre_muestreo = $data['nombre_muestreo'];
        $test->estructura_muestreo = $data['estructura_muestreo'] ?? NULL;
        $test->fecha_creacion = $data['fecha_creacion'];
        $test->fecha_objetivo = $data['fecha_objetivo'] ?? NULL;
        $test->fecha_fin = $data['fecha_fin'] ?? NULL;
        $test->estado = $data['estado'];
        $test->ult_usuario = $data['ult_usuario'] ?? NULL;
        $test->exclusivo_de = $data['exclusivo_de'] ?? NULL;

        return $test->create();
    }

    /**
     * Get test data by ID
     * 
     * @param string $id_test Test ID
     * @return array|null Test data or null if not found
     */
    public function getTestById(string $id_test): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tests WHERE id = :id_test");
        $stmt->execute(['id_test' => $id_test]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
}