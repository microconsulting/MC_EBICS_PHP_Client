<?php
// Php script allowing the execution of the INI command

use AndrewSvirin\Ebics\Exceptions\IncompatibleOrderAttributeException;
use AndrewSvirin\Ebics\Exceptions\TxMessageReplayException;
use App\Factories\X509\MyCompanyX509Generator;
include_once("PathFile.php");
include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();

$code = array(
    'INI' => array(
        'fake' => false
        ),
    );

$x509Generator = new MyCompanyX509Generator ;

echo '********* <BR>';
var_dump($argv);

// Incorrect number of parameters
if (sizeof($argv)!=2) {
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

$connection->INIOrder($credentialsID, $code, $x509Generator);

?>