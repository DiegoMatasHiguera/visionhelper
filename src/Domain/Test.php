<?php

namespace App\Domain;

use PDO;

/**
 * Clase que representa a un test
 */
class Test 
{
    private $pdo;
    
    // Propiedades
    public $id;
    public $lote;
    public $nombre_muestreo;
    public $estructura_muestreo;    
    public $fecha_creacion;
    public $fecha_objetivo;
    public $fecha_fin;
    public $estado;
    public $ult_usuario;
    public $exclusivo_de;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo La conexión a la base de datos
     */
    public function __construct(PDO $pdo) 
    {
        $this->pdo = $pdo;
    }

    /**
     * Crea un nuevo test en la base de datos
     * 
     * @return array Array with 'success' boolean, 'id' of new test if successful, and 'error' message if applicable
     */
    public function create() 
    {
        try {
            $fields = [];
            $placeholders = [];
            $values = [];
            
            foreach (get_object_vars($this) as $prop => $value) {
                if ($prop !== 'pdo' && $prop !== 'id' && isset($value)) {
                    $fields[] = $prop;
                    $placeholders[] = ":$prop";
                    $values[$prop] = $value;
                }
            }
            
            if (empty($fields)) {
                return [
                    'success' => false,
                    'error' => 'No fields to insert'
                ];
            }
            
            $sql = "INSERT INTO tests (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                return [
                    'success' => false,
                    'error' => $errorInfo[2], // Error message
                    'code' => $errorInfo[0] . ':' . $errorInfo[1] // SQLSTATE and driver-specific error code
                ];
            }
            
            $this->id = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'id' => $this->id
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Guarda los datos de un tests
     * 
     * @return array Array with 'success' boolean and 'error' message if applicable
     */
    public function save() 
    {
        try {
            $fields = [];
            $values = [];
            
            foreach (get_object_vars($this) as $prop => $value) {
                if ($prop !== 'pdo' && isset($value)) {
                    $fields[] = "$prop = :$prop";
                    $values[$prop] = $value;
                }
            }
            
            if (empty($fields)) {
                return [
                    'success' => false,
                    'error' => 'No fields to update'
                ];
            }
            
            $sql = "UPDATE tests SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                return [
                    'success' => false,
                    'error' => $errorInfo[2], // Error message
                    'code' => $errorInfo[0] . ':' . $errorInfo[1] // SQLSTATE and driver-specific error code
                ];
            }   
            
            return [
                'success' => true
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Carga los datos de un test
     * 
     * @param string $id El id del test
     * @return array Array with 'success' boolean and 'error' message if applicable
     */
    public function load($id) 
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM tests WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                return [
                    'success' => false,
                    'error' => $errorInfo[2], // Error message
                    'code' => $errorInfo[0] . ':' . $errorInfo[1] // SQLSTATE and driver-specific error code
                ];
            }
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $this->id = $data['id'];
                $this->lote = $data['lote'];
                $this->nombre_muestreo = $data['nombre_muestreo'];
                $this->estructura_muestreo = $data['estructura_muestreo'];
                $this->fecha_creacion = $data['fecha_creacion'];
                $this->fecha_objetivo = $data['fecha_objetivo'];
                $this->fecha_fin = $data['fecha_fin'];
                $this->estado = $data['estado'];
                $this->ult_usuario = $data['ult_usuario'];
                $this->exclusivo_de = $data['exclusivo_de'];
                return [
                    'success' => true
                ];
            }
            
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
}