<?php
// Php script allowing the execution of the HBP command

//use App\Factories\X509\MyCompanyX509Generator;
include_once("PathFile.php");
include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();

$x509Generator = new MyCompanyX509Generator ;

// Incorrect number of parameters
if (sizeof($argv)!=2){
    throw new LengthException('Incorrect number of parameters');
}
else{
    $credentialsID = (int)$argv[1];
    // Incorrect value type
    if ($credentialsID==0){
        throw new UnexpectedValueException('Null Value');
    }
    else{
        // File does not exist
        if (!file_exists(__DIR__ . '/_data/credentials/credentials_'. $credentialsID .'.json')) {
            throw new InvalidArgumentException('File not found');
        }
    }
}

$connection->HPBOrder($credentialsID, $x509Generator);

?>