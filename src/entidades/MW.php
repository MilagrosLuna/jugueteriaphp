<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Dompdf\Dompdf;

require_once __DIR__ . "/autentificadora.php";
require_once __DIR__ . "/accesoDatos.php";
require_once __DIR__ . "/juguete.php";
require_once __DIR__ . "/usuario.php";

class MW{
    // ok
    public function verificarCamposCorreoYClave(Request $request, RequestHandler $handler) : ResponseMW{

        $arrayDeParametros = $request->getParsedBody();
        if (!isset($arrayDeParametros["user"])) {
            $data = [
                'mensaje' => 'No se recibió el campo "user"',
            ];
            $response = new ResponseMW();
            $response = $response->withStatus(403, "ERROR");
            $response->getBody()->write(json_encode($data));
            return $response;
        }
        $array = json_decode($arrayDeParametros["user"]); 

        if ($array === null){
            $data = [
                'mensaje' => 'El campo "user" no es un JSON válido',
            ];
            $response = new ResponseMW();
            $response = $response->withStatus(403, "ERROR");
            $response->getBody()->write(json_encode($data));
            return $response;
        }

        $correo = $array->correo ?? '';
        $clave = $array->clave ?? '';

        if (empty($correo) || empty($clave)) {
            $data = [
                'mensaje' => 'Faltan el correo y/o la clave',
            ];
            $responseMW = new ResponseMW();
            $responseMW = $responseMW->withStatus(409, "ERROR");            
            $responseMW->getBody()->write(json_encode($data));
            return $responseMW;
        }else{
            $response = $handler->handle($request);            
            $contenidoAPI = (string) $response->getBody();
        }

        //GENERO UNA NUEVA RESPUESTA
        $response = new ResponseMW(200);
        $response->getBody()->write("$contenidoAPI");
        return $response;
    } 
    // ok
    public static function verificarCredencialesEnBd(Request $request, RequestHandler $handler): ResponseMW {

        $arrayDeParametros = $request->getParsedBody();
        $array = json_decode($arrayDeParametros["user"]);         
        $correo =  $array->correo;
        $clave = $array->clave;

        if (!Usuario::ValidarUsuario($correo,$clave)) {
            $data = [
                'mensaje' => 'Credenciales invalidas, no estan en la base',
            ];
            $response = new ResponseMW();
            $response = $response->withStatus(403, "ERROR");
            $response->getBody()->write(json_encode($data));
            return $response;
        }

        $response = $handler->handle($request);
        return $response;
    }
    //ok
    public function verificarjwt(Request $request, RequestHandler $handler) : ResponseMW{
        $token = $request->getHeaderLine('token');
        $obj_rta = Autentificadora::verificarJWT($token);
        if (!$obj_rta->verificado) {
            $std = new stdClass();
            $std->éxito = false;
            $std->mensaje = 'Token inválido';

            $response = new ResponseMW();
            $response->withStatus(403);
            $response->getBody()->write(json_encode($std));
    
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    public function ListarTablaSinClavePROP(Request $request, RequestHandler $handler): ResponseMW{
        $contenidoAPI = "";

        if (isset($request->getHeader("token")[0])) {
            $token = $request->getHeader("token")[0];
            $datos_token = Autentificadora::obtenerPayLoad($token);            
            $usuario_token = $datos_token->payload->data; 
            $perfil_usuario = $usuario_token->perfil;

            if($perfil_usuario == "propietario"){
                $response = $handler->handle($request);
                $contenidoAPI = (string) $response->getBody();
    
                $api_respuesta = json_decode($contenidoAPI);
                $array_usuarios = json_decode($api_respuesta->tabla);
    
                foreach ($array_usuarios as $usuario) {
                    unset($usuario->clave);
                }
    
                $contenidoAPI = MW::ArmarTablaSinClave($array_usuarios);
                 // Generar el PDF a partir del HTML
                $dompdf = new Dompdf();
                $dompdf->loadHtml($contenidoAPI);
                $dompdf->render();

                // Obtener el contenido del PDF generado
                $pdfContent = $dompdf->output();

                // Enviar el PDF como respuesta
                $response = new ResponseMW();
                $response->getBody()->write($pdfContent);
                return $response->withHeader('Content-Type', 'application/pdf');

            }else{
                $response = new ResponseMW();
                $std = new stdClass();
                $std->éxito = false;
                $std->mensaje = 'perfil no valido';
                $response = $response->withStatus(403);
                $response->getBody()->write(json_encode($std));
                return $response;
            }
           
        }
    }

    public function ListarTablaSinClave(Request $request, RequestHandler $handler): ResponseMW
    {
        $contenidoAPI = "";

        if (isset($request->getHeader("token")[0])) {
            $token = $request->getHeader("token")[0];
            $datos_token = Autentificadora::obtenerPayLoad($token);            
            $usuario_token = $datos_token->payload->data; 
            $perfil_usuario = $usuario_token->perfil;

            $response = $handler->handle($request);
            $contenidoAPI = (string) $response->getBody();

            $api_respuesta = json_decode($contenidoAPI);
            $array_usuarios = json_decode($api_respuesta->tabla);

            foreach ($array_usuarios as $usuario) {
                unset($usuario->clave);
            }

            $contenidoAPI = MW::ArmarTablaSinClave($array_usuarios);
        }

        $response = new ResponseMW();
        $response = $response->withStatus(200);
        $response->getBody()->write($contenidoAPI);
        return $response;
    }

    private static function ArmarTablaSinClave($listado): string
    {
        $tabla = "<table><thead><tr>";
        foreach ($listado[0] as $key => $value) {
            if ($key != "clave") {
                $tabla .= "<th>{$key}</th>";
            }
        }
        $tabla .= "</tr></thead><tbody>";

        foreach ($listado as $item) {
            $tabla .= "<tr>";
            foreach ($item as $key => $value) {
                if ($key == "foto") {
                    $tabla .= "<td><img src='{$value}' width=25px></td>";
                } else {
                    if ($key != "clave") {
                        $tabla .= "<td>{$value}</td>";
                    }
                }
            }
            $tabla .= "</tr>";
        }
        $tabla .= "</tbody></table> <br>";
        return $tabla;
    }

    public function ListarTablaJuguetes(Request $request, RequestHandler $handler): ResponseMW
    {
        $contenidoAPI = "";

        if (isset($request->getHeader("token")[0])) {
            $token = $request->getHeader("token")[0];

            $datos_token = Autentificadora::obtenerPayLoad($token);
            $usuario_token = $datos_token->payload->data;

            $response = $handler->handle($request);
            $contenidoAPI = (string) $response->getBody();

            $api_respuesta = json_decode($contenidoAPI);
            $array_juguetes = json_decode($api_respuesta->tabla);

            $contenidoAPI = MW::ArmarTablaJuguetes($array_juguetes);
        }

        $response = new ResponseMW();
        $response = $response->withStatus(200);
        $response->getBody()->write($contenidoAPI);
        return $response;
    }

    private static function ArmarTablaJuguetes($listado): string
    {
        $tabla = "<table><thead><tr>";
        foreach ($listado[0] as $key => $value) {
            $tabla .= "<th>{$key}</th>";
        }
        $tabla .= "</tr></thead><tbody>";

        foreach ($listado as $item) {
            $tabla .= "<tr>";
            foreach ($item as $key => $value) {
                if ($key == "path_foto") {
                    $tabla .= "<td><img src='{$value}' width=25px></td>";
                } else {
                    $tabla .= "<td>{$value}</td>";
                }
            }
            $tabla .= "</tr>";
        }
        $tabla .= "</tbody></table> <br>";
        return $tabla;
    }
    // ok
    public static function verificarCorreoExistente(Request $request, RequestHandler $handler): ResponseMW {

        $arrayDeParametros = $request->getParsedBody();
        $array = json_decode($arrayDeParametros["usuario"]);         
        $correo =  $array->correo;
        $clave = $array->clave;

        if (Usuario::ValidarCorreoExiste($correo)) {
            $data = [
                'mensaje' => 'CORREO EXISTENTE',
            ];
            $response = new ResponseMW();
            $response = $response->withStatus(403, "ERROR");
            $response->getBody()->write(json_encode($data));
            return $response;
        }

        $response = $handler->handle($request);
        return $response;
    }

}