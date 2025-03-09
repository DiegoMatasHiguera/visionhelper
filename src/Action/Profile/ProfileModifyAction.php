<?php

namespace App\Action\Profile;

use App\Action\Auth\LogoutAction;
use App\Renderer\JsonRenderer;
use App\Domain\Conexion;
use App\Domain\Usuario;

use Fig\Http\Message\StatusCodeInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Import GD functions
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function imagecreatetruecolor;
use function imagealphablending;
use function imagesavealpha;
use function imagecopyresampled;
use function imagepng;
use function imagejpeg;
use function imagedestroy;

final class ProfileModifyAction
{
    private JsonRenderer $renderer;

    public function __construct(JsonRenderer $jsonRenderer)
    {
        $this->renderer = $jsonRenderer;        
}

    /**
     * API:
     * POST /profile/{user_email}
     * 
     * Modifica los datos de un usuario (excepto el nombre)
     * 
     * @param object $request Con header con el campo "tipo" para comprobar si es administrador, 
     *                  y campo "user_email" para comprobar si es el mismo usuario que se quiere modificar
     * @param object $request Con body con los campos a modificar 
     * @return string Un JSON con un nuevo access y refresh token, o el mensaje de error correspondiente.
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $user_email = (string) $args['user_email'];
        $data = $request->getParsedBody();
        $header_email = $request->getHeaders()['user_email'][0] ?? '';
        $header_tipo = $request->getHeaders()['tipo'][0] ?? '';

        // Accesos
        if (!$user_email) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_BAD_GATEWAY);
            $data = [
                "error" => "Bad route: enter a email"
            ];
            return $this->renderer->json($response, $data);
        } else if ($header_email !== $user_email) {
            // Si no eres el mismo usuario, necesitas ser administrador
            if ($header_tipo !== "Administrador") {
                $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
                $data = [
                    "error" => "Unauthorized to modify this user profile, you need higher privileges",
                    "your_email" => $header_email,
                    "user_email" => $user_email
                ];
                return $this->renderer->json($response, $data);
            }
        }

        // Cargamos los datos de usuario para modificarlos      
        $conex = new Conexion();
        $pdo = $conex->getDatabaseConnection();
        $usuario = new Usuario($pdo);
        $resultLoadUsuario = $usuario->load($user_email);

        if (!$resultLoadUsuario['success']) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
            $data = [
                "error" => "Error loading user:" . $resultLoadUsuario['error']
            ];
            return $this->renderer->json($response, $data);
        }
        
        // Modificamos
        if (isset($data['contrasena'])) {       
            if (password_verify($data["contrasena_vieja"], $usuario->contrasena)) {
                $usuario->contrasena = password_hash($data['contrasena'], PASSWORD_BCRYPT); // Hashing
                LogoutAction::logoutInterno($user_email);
            } else {
                $response = $response->withStatus(StatusCodeInterface::STATUS_UNAUTHORIZED);
                $data = [
                    "error" => "Old password does not match"
                ];
                return $this->renderer->json($response, $data);
            } 
        }
        // Exclusivo Administrador
        if ($header_tipo === "Administrador") {
            $usuario->tipo = $data['tipo'] ?? $usuario->tipo;
            $usuario->nombre = $data['nombre'] ?? $usuario->nombre;
            // Ver si el usuario con email cambiado ya existe
            if (isset($data['email']) && $data['email'] !== $usuario->email) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
                $stmt->execute(['email' => $data['email']]);
                if ($stmt->fetchColumn() > 0) {
                    $response = $response->withStatus(StatusCodeInterface::STATUS_CONFLICT);
                    $data = [
                        "error" => "Email ya asociado a otro usuario"
                    ];
                    return $this->renderer->json($response, $data);
                } else {
                    $usuario->email = $data['email'];
                }              
            }
        }
        $usuario->fecha_nacimiento = $data['fecha_nacimiento'] ?? $usuario->fecha_nacimiento;
        $usuario->sexo = $data['sexo'] ?? $usuario->sexo;
        $usuario->corr_ocular = $data['corr_ocular'] ?? $usuario->corr_ocular;
        $usuario->fecha_rev_ocular = $data['fecha_rev_ocular'] ?? $usuario->fecha_rev_ocular;
        // Avatar
        if (isset($data['avatar_url'])) {
            $usuario->avatar_url = $this->cropAndZoomImage($data['avatar_url'], (int) $data['avatar_x'], (int) $data['avatar_y'], (int) $data['avatar_zoom'], $usuario->email);
        }

        // Guardamos los cambios
        $resultSaveUsuario = $usuario->save();
        if (!$resultSaveUsuario['success']) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            $data = [
                "error" => "Error updating user:" . $resultSaveUsuario['error']
            ];
            return $this->renderer->json($response, $data);
        }

        $data = [
            'message' => "User data updated successfully"
        ];
        return $this->renderer->json($response, $data);
    }


    /**
     * Crop and zoom an image and save it to the avatars directory
     * 
     * @param string $base64Image The base64-encoded image
     * @param int $x The x coordinate of the top-left corner of the crop area
     * @param int $y The y coordinate of the top-left corner of the crop area
     * @param float $zoom The zoom level (1.0 = 100%)
     * @param string $user_email The user's email
     * @return string|false The public URL of the saved image, or false on failure
     */
    private function cropAndZoomImage($base64Image, $x, $y, $zoom, $user_email) {
        if (strpos($base64Image, 'data:image/png;base64,') !== false) {
            $imgType = 'png';
        } else {
            $imgType = 'jpeg';
        }

        // Remove the header part from base64 string
        $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);

        // Decode base64 to binary data
        $imageData = base64_decode($base64Image);
        
        // Create image from string
        $source = imagecreatefromstring($imageData);
        if (!$source) {
            return false;
        }
        
        // Get original dimensions
        $width = imagesx($source);
        $height = imagesy($source);
        
        // Dimensions are always 400px.
        $newWidth = 400;
        $newHeight = 400;
        
        // Calculate the zoom factor (convert from percentage if needed)
        $zoomFactor = $zoom / 100;
        
        // Convert the x,y coordinates from the preview space to the original image space
        // The received x,y coordinates are relative to the preview crop area, so we need to 
        // convert them to the source image coordinates based on zoom
        $srcX = $x / $zoomFactor;
        $srcY = $y / $zoomFactor;
        
        // Calculate source width and height based on zoom
        $srcWidth = $newWidth / $zoomFactor;
        $srcHeight = $newHeight / $zoomFactor;
        
        // Ensure we don't exceed the source image dimensions
        if ($srcX < 0) $srcX = 0;
        if ($srcY < 0) $srcY = 0;
        if ($srcX + $srcWidth > $width) $srcX = $width - $srcWidth;
        if ($srcY + $srcHeight > $height) $srcY = $height - $srcHeight;
        
        // Create destination image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // For PNG support with transparency
        if ($imgType === 'png') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }
        
        // Crop and resize in one step
        imagecopyresampled(
            $destination,    // Destination image
            $source,         // Source image
            0, 0,            // Destination position (top-left corner)
            $srcX, $srcY,    // Source position (where to start the crop)
            $newWidth,       // Destination width (400px)
            $newHeight,      // Destination height (400px)
            $srcWidth,       // Source width (adjusted for zoom)
            $srcHeight       // Source height (adjusted for zoom)
        );
        
        // Create avatars directory if it doesn't exist
        $avatarsDir = __DIR__ . '/../../../public/avatars/';
        if (!is_dir($avatarsDir)) {
            mkdir($avatarsDir, 0755, true);
        }

        $filename = $user_email . "." . $imgType;
        $filePath = $avatarsDir . $filename;
        $publicPath = "/public/avatars/{$filename}";

        // Save the image to file
        if ($imgType === 'png') {
            imagepng($destination, $filePath, 9);
        } else {
            imagejpeg($destination, $filePath, 90);
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($destination);
        
        return $publicPath;
    } 
}
