<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as ResponseMW;
use \Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . "/entidades/manejadora.php";
require __DIR__ . "/entidades/MW.php";


$app = AppFactory::create();

$app->get('/', \Manejadora::class . ':mostrarUsuarios');

$app->post('/', \Manejadora::class . ':crearJuguete')
    ->add(\MW::class . ':verificarjwt');

$app->post('/login[/]', \Manejadora::class . ':loginCorreoYclave')
    ->add(\MW::class . ':verificarCredencialesEnBd')
    ->add(\MW::class . ':verificarCamposCorreoYClave');

$app->get('/login[/]', \Manejadora::class . ':loginToken');


$app->group('/toys', function (RouteCollectorProxy $grupo) {       
    $grupo->delete('/{id_juguete}', \Manejadora::class . ':borrarJuguete');
    $grupo->post('/', \Manejadora::class . ':modificarJuguete');
})->add(\MW::class . ':verificarjwt');


$app->group('/tablas', function (RouteCollectorProxy $grupo) {       
    $grupo->get('/usuarios', \Manejadora::class . ':mostrarUsuarios')
    ->add(\MW::class . ':ListarTablaSinClave');
    $grupo->post('/usuarios', \Manejadora::class . ':mostrarUsuarios')
    ->add(\MW::class . ':ListarTablaSinClavePROP');
    $grupo->get('/juguetes',  \Manejadora::class . ':mostrarJuguetes')
    ->add(\MW::class . ':ListarTablaJuguetes');
})->add(\MW::class . ':verificarjwt');

$app->post('/usuarios', \Manejadora::class . ':crearUsuario')
    ->add(\MW::class .':verificarCorreoExistente')
    ->add(\MW::class .':verificarCamposCorreoYClave')
    ->add(\MW::class .':verificarjwt');

//CORRE LA APLICACIÓN.
$app->run();
