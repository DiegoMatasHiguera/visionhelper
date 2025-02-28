<?php

namespace App\Domain;

use Firebase\JWT\JWT;


/**
 * Generador de tokens JWT
 */
class JWTCreator {
    private static $access_validez = 60 * 15; // 15 minutos
    public static $refresh_validez = 60 * 60 * 24 * 7; // 7 días

    /**
     * Genera un token de acceso para un usuario (de corta vida)
     *
     * @param   string  $userId     El email del usuario.
     * @param   string  $secret     La clave secreta.
     * @param   string  $algorithm  El algoritmo de encriptación.
     * @return  string  El token.
     */
    public static function generateAccessToken($userId, $tipo, $secret, $algorithm) {
        $payload = [
            "iat" => time(), // Issued at
            "exp" => time() + (self::$access_validez),
            "sub" => $userId,
            "tipo" => $tipo // Add the user's role to the payload
        ];

        return JWT::encode($payload, $secret, $algorithm);
    }


    /**
     * Genera un token de refresco para un usuario (de larga vida)
     *
     * @return  string  Cadena random de 128 caracteres.
     */
    public static function generateRefreshToken() {
        return bin2hex(random_bytes(64));
    }
}
