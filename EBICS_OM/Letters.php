<?php
// Php script generates INI and HIA letters in HTML format

use App\Factories\X509\MyCompanyX509Generator;
include_once("PathFile.php");
include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();

$x509Generator = new MyCompanyX509Generator ;
$connection->GenerateLetters(0, $x509Generator, '/Users/sarahmoreau/Documents/NewLetters.html');
?>