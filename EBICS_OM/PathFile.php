<?php

//Rewrite spl_autoload_registrer function to access library classes with uses
class PathFile
{
    public function __construct()
    {
        spl_autoload_register(function($class){
            $paths = array(
                '',
                './',
                '../',
                './src/',
                '../src/'
             );
         
             $currentDir = getcwd();
             $classPath = str_replace('andrewsvirin/ebics/','',str_replace('\\', '/', strtolower($class)));
             $files = array();
         
             //echo '<b>Try to include class ' . $class . '</b><br>';
         
             foreach($paths as $path){
         
                 $file = $currentDir . "/" . $path . $classPath . '.php';
                 if(file_exists($file)){
                     $files = array($file);
                 }else{
                     //echo '<font color="orange">&emsp;' . $file . ': !!!File do not exists!!!</font><br>';
                 }
             }
             
             if(count($files)==0){
                 echo '<font color="red">&emsp;' . $file . ': !!!NOT LOADED!!!</font><br><BR>';
                 return false;
             }
         
             foreach($files as $file){
                 if(is_file($file)){
                     //echo '<font color="green">&emsp;' . $file . ': LOADED</font><br><BR>';
                     include_once($file);
                     return true;
                 }else{
                     echo '<font color="red">&emsp;' . $file . ': !!!NOT LOADED!!!</font><br><BR>';
                     return false;
                 }
             }
         });
    }
}

?>