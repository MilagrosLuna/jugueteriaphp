<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;

require_once __DIR__ . "/autentificadora.php";
require_once __DIR__ . "/accesoDatos.php";
require_once __DIR__ . "/juguete.php";
require_once __DIR__ . "/usuario.php";

class Manejadora{
    // ok
    public function mostrarUsuarios(Request $request, Response $response, array $args) : Response {
        $users = Usuario::TraerTodosUsuarios();
        $std= new stdclass();
        if($users){
            $std->exito = true;
            $std->mensaje = "Usuarios obtenidos!";
            $std->tabla = json_encode($users);
            $newResponse = $response->withStatus(200);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }else{
            $std->exito = true;
            $std->mensaje = "Usuarios NO obtenidos!";
            $std->tabla = '{"tabla":"no obtenida"}';
            $newResponse = $response->withStatus(424);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }
    }
    // ok
    public function crearJuguete(Request $request, Response $response, array $args):Response{
        $array = $request->getParsedBody();
        $Recibido = json_decode($array["juguete_json"]);        
        
        $juguete = new Juguete();
        $juguete->marca = $Recibido->marca;
        $juguete->precio = $Recibido->precio;    

        $archivos = $request->getUploadedFiles(); 
        $destino = "../src/fotos/";
        $extension = explode(".", $archivos['foto']->getClientFilename()); //nombre de la ext
        $path =  $juguete->marca . "." . $extension[1];

        $juguete->path_foto = $path;    
        
        $juguete->id = -1;     
        $std= new stdclass();
        
        if($juguete->Agregar()){            
            $archivos['foto']->moveTo($destino . $path);
            $std->exito = true;
            $std->mensaje = "juguete  agregado!";
            $newResponse = $response->withStatus(200);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }else{
            $std->exito = false;
            $std->mensaje = "ERROR! juguete no agregado"; 
            $newResponse = $response->withStatus(418);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }
    }
    // ok
    public function loginCorreoYclave(Request $request, Response $response, array $args) : Response {

        $arrayDeParametros = $request->getParsedBody();
        $array = json_decode($arrayDeParametros["user"]); 
       
        $user = Usuario::ValidarUsuario($array->correo,$array->clave);
        if($user){
            $usuariopayload= new stdclass();    
            $usuariopayload->id = $user->id;
            $usuariopayload->correo = $user->correo;
            $usuariopayload->nombre = $user->nombre;
            $usuariopayload->apellido = $user->apellido;
            $usuariopayload->foto = $user->foto;
            $usuariopayload->perfil = $user->perfil;
    
            $token = Autentificadora::crearJWT($usuariopayload, 120);
            $std= new stdclass();                 
            $std->exito = true;
            $std->jwt = json_encode($token);
            $newResponse = $response->withStatus(200);
            $newResponse->getBody()->write(json_encode($std));     
        }else{
            $std= new stdclass();                 
            $std->exito = false;
            $std->jwt = json_encode("false");
            $newResponse = $response->withStatus(403);
            $newResponse->getBody()->write(json_encode($std));   
        }     
    
        return $newResponse->withHeader('Content-Type', 'application/json');
    }
    // ok
    public function loginToken(Request $request, Response $response, array $args) : Response {

        $token = $request->getHeader("token")[0];

        $obj_rta = Autentificadora::verificarJWT($token);

        $status = $obj_rta->verificado ? 200 : 403;

        $newResponse = $response->withStatus($status);

        $newResponse->getBody()->write(json_encode($obj_rta));
    
        return $newResponse->withHeader('Content-Type', 'application/json');
    }
    // ok
    public function borrarJuguete(Request $request, Response $response, array $args) : Response {       
        $id = $args['id_juguete'];
        $token = $request->getHeaderLine('token');
        $obj_rta = Autentificadora::verificarJWT($token);
        if(!$obj_rta->verificado){
            $std = new stdClass();
            $std->éxito = false;
            $std->mensaje = 'Token inválido';
            $newResponse = new ResponseMW();
            $newResponse->withStatus(418);
            $newResponse->getBody()->write(json_encode($std));        
        }else{
            $juguete  = Juguete::TraerUno($id);
            if(Juguete::Eliminar($id)){
                $rutaFoto = '../src/fotos/' . $juguete->path_foto;
                if (file_exists($rutaFoto)) {
                    unlink($rutaFoto);
                }
                $std = new stdClass();
                $std->éxito = true;
                $std->mensaje = 'Juguete borrado exitosamente';
                $newResponse = new ResponseMW();
                $newResponse->withStatus(200);
                $newResponse->getBody()->write(json_encode($std));
            }else{
                $std = new stdClass();
                $std->éxito = false;
                $std->mensaje = 'Juguete NO existe ';
                $newResponse = new ResponseMW();
                $newResponse->withStatus(418);
                $newResponse->getBody()->write(json_encode($std));
            }           
        }
        return $newResponse->withHeader('Content-Type', 'application/json');
    }
    //ok
    public function modificarJuguete(Request $request, Response $response, array $args) : Response {
        $token = $request->getHeaderLine('token');
        $obj_rta = Autentificadora::verificarJWT($token);
        var_dump($obj_rta);
        if ($obj_rta->verificado){
            $array = $request->getParsedBody();
            $juguete = json_decode($array["juguete"]);
            if ($juguete && isset($juguete->marca) && isset($juguete->precio)) {
                // Aquí puedes acceder a los campos modificados del juguete
                $marca = $juguete->marca;
                $precio = $juguete->precio;
                $id = $juguete->id;
                $archivos = $request->getUploadedFiles(); 
                $destino = "../src/fotos/";
                $extension = explode(".", $archivos['foto']->getClientFilename()); //nombre de la ext
                $path =  $juguete->marca . "_modificada." . $extension[1];              
                $jugueteViejo  = Juguete::TraerUno($id);
                if(Juguete::Modificar($id,$marca,$precio,$path)){    
                    $rutaFoto = '../src/fotos/' . $jugueteViejo->path_foto;
                    if (file_exists($rutaFoto)) {
                        unlink($rutaFoto);
                    }                
                    $archivos['foto']->moveTo($destino . $path);
                    $std = new stdClass();
                    $std->éxito = true;
                    $std->mensaje = 'Juguete modificado exitosamente';
                    $newResponse = new ResponseMW();
                    $newResponse->withStatus(200);
                    $newResponse->getBody()->write(json_encode($std));
                }    
                else{
                    $std = new stdClass();
                    $std->éxito = false;
                    $std->mensaje = 'hubo un problema';
                    $newResponse = new ResponseMW();
                    $newResponse->withStatus(400);
                    $newResponse->getBody()->write(json_encode($std));
                }
               
            } else {
                $std = new stdClass();
                $std->éxito = false;
                $std->mensaje = 'Datos de juguete inválidos';
                $newResponse = new ResponseMW();
                $newResponse->withStatus(400);
                $newResponse->getBody()->write(json_encode($std));
            }

           
        } else {
            $std = new stdClass();
            $std->éxito = false;
            $std->mensaje = 'Token inválido';
            $newResponse = new ResponseMW();
            $newResponse->withStatus(418);
            $newResponse->getBody()->write(json_encode($std));
        }
        return $newResponse->withHeader('Content-Type', 'application/json');
    }
    //ok
    public function mostrarJuguetes(Request $request, Response $response, array $args) : Response {
        $Juguete = Juguete::TraerTodos();
        $std= new stdclass();
        if($Juguete){
            $std->exito = true;
            $std->mensaje = "Juguete obtenidos!";
            $std->tabla = json_encode($Juguete);
            $newResponse = $response->withStatus(200);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }else{
            $std->exito = false;
            $std->mensaje = "Juguete NO obtenidos!";
            $std->tabla = '{"tabla":"no obtenida"}';
            $newResponse = $response->withStatus(424);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }
    }
    
    public function crearUsuario(Request $request, Response $response, array $args):Response{
        $array = $request->getParsedBody();
        $usu = json_decode($array["usuario"]);
        $archivos = $request->getUploadedFiles(); 
        $destino = "../src/fotos/";
        $user = new Usuario();
        $extension = explode(".", $archivos['foto']->getClientFilename()); 
        $user->correo = $usu->correo;
        $user->clave = $usu->clave;
        $user->nombre = $usu->nombre;
        $user->apellido = $usu->apellido;
        $user->perfil = $usu->perfil;        
        $user->id = -1;
        $path =  $usu->correo . "." . $extension[1];        
        $user->foto = $path;
     
        $std= new stdclass();
        $a= new stdclass();
        $a->nombre = $usu->nombre;
        $a->correo = $usu->correo;
        
        if($user->Agregar()){
          /*   $id = $user->obtenerId($usu->nombre,$usu->correo,$usu->clave,$usu->apellido);
            $path =  $usu->correo ."_". $id . "." . $extension[1];   */
           /// if(Usuario::actualizarPath($id,$path)){                
                $archivos['foto']->moveTo($destino . $path);
           // }
            $std->exito = true;
            $std->mensaje = "Usuario  agregado!";
            $std->user = $a;
            $newResponse = $response->withStatus(200);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }else{
            $std->exito = false;
            $std->mensaje = "ERROR! Usuario no agregado"; 
            $newResponse = $response->withStatus(418);
            $newResponse->getBody()->write(json_encode($std));            
            return $newResponse->withHeader('Content-Type', 'application/json');
        }
    }
  
  
}