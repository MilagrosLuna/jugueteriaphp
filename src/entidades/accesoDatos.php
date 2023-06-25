<?php
namespace Poo;
use PDO;
use PDOException;

class AccesoDatos
{
    private static AccesoDatos $objetoAccesoDatos;
    private PDO $objetoPDO;
 
    private function __construct()
    {
        try {
 
            $usuario = 'root';
            $clave = '';

            $this->objetoPDO = new PDO('mysql:host=localhost;dbname=jugueteria_bd;charset=utf8', $usuario, $clave);
 
        } catch (PDOException $e) {
 
            print "Error!!!<br/>" . $e->getMessage();
 
            die();
        }
    }
 
    public function retornarConsulta(string $sql)
    {
        return $this->objetoPDO->prepare($sql);
    }
 
    public static function dameUnObjetoAcceso() : AccesoDatos //singleton
    {
        if (!isset(self::$objetoAccesoDatos)) {       
            self::$objetoAccesoDatos = new AccesoDatos(); 
        }
 
        return self::$objetoAccesoDatos;        
    }
 
    // Evita que el objeto se pueda clonar
    public function __clone()
    {
        trigger_error('La clonaci&oacute;n de este objeto no est&aacute; permitida!!!', E_USER_ERROR);
    }
}
