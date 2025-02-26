<?php

namespace App\Domain;

use PDO;
use PDOException;

/**
 * Gestiona la conexión con la base de datos, incluidos los datos necesarios
 */
class Conexion {

    private $settings;

    public function __construct() {
        $this->settings = require __DIR__ . '/../../config/settings.php';
    }
        
    /**
     * Conecta con la base de datos.
     *
     * @return PDO El objeto de conexión.
     */
    public function getDatabaseConnection() {
        try {
            return new PDO("mysql:host=".$this->settings['db']['host'].";dbname=".$this->settings['db']['name'].";charset=utf8",
                $this->settings['db']['username'],
                $this->settings['db']['password'],
                $this->settings['db']['options']
             );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Conexión fallida: ' . $e->getMessage()]));
        }
    }
}