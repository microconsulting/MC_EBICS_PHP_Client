<?php
// Php script allowing the execution of the HBP command

use App\Factories\X509\MyCompanyX509Generator;
include_once("PathFile.php");
include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();

$code = array(
    'HPB' => array(
        'fake' => false
        ),
    );

$x509Generator = new MyCompanyX509Generator ;
$connection->HPBOrder(0, $code, $x509Generator);

?>