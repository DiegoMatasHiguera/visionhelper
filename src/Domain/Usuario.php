<?php

namespace App\Domain;

use PDO;

/**
 * Clase que representa a un usuario
 */
class Usuario 
{
    private $pdo;
    
    // Propiedades
    public $email;
    public $contrasena;
    public $tipo;    
    public $nombre;
    public $fecha_nacimiento;
    public $sexo;
    public $corr_ocular;
    public $fecha_rev_ocular;
    public $avatar_url;
    
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
     * Guarda los datos de un usuario
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
            
            $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE email = :email";
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
     * Carga los datos de un usuario
     * 
     * @param string $email El email del usuario
     * @return array Array with 'success' boolean and 'error' message if applicable
     */
    public function load($email) 
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $result = $stmt->execute([$email]);
            
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
                $this->email = $data['email'];
                $this->contrasena = $data['contrasena'];
                $this->tipo = $data['tipo'];
                $this->nombre = $data['nombre'];
                $this->fecha_nacimiento = $data['fecha_nacimiento'];
                $this->sexo = $data['sexo'];
                $this->corr_ocular = $data['corr_ocular'];
                $this->fecha_rev_ocular = $data['fecha_rev_ocular'];
                $this->avatar_url = $data['avatar_url'];
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