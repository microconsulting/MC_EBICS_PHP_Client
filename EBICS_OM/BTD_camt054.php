<?php
// Php script allowing the execution of the INI command
include_once("PathFile.php");
include_once("Payments.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$payment = new Payments();

$x509Generator = new MyCompanyX509Generator ;

// Incorrect number of parameters
if (sizeof($argv)!=4) {
    throw new LengthException('Incorrect number of parameters');
}
else{
    $credentialsID = (int)$argv[1];
    $stratDate = $argv[2];
    $endDate = $argv[3];

    // Incorrect value type of $credentialID
    if ($credentialsID==0){
        throw new UnexpectedValueException('Null Value');
    }
    else{
        // File does not exist
        if (!file_exists(__DIR__ . '/_data/credentials/credentials_'. $credentialsID .'.json')) {
            throw new InvalidArgumentException('File not found');
        }
    }

    // TODO : check startDate and endDate
}

$payment->BTDOrder($credentialsID, $stratDate, $endDate, $x509Generator);

?>