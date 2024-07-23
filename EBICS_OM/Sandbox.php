<?php
//use App\Factories\X509\MyCompanyX509Generator;
use AndrewSvirin\Ebics\Models\Data;
include_once("PathFile.php");

include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();

$code = array(
    'INI' => array(
        'fake' => false
        ),
    'HIA' => array(
        'fake' => false
        ),
    'HPB' => array(
        'fake' => false
        ),
    'Z54' => array(
        'fake' => false
        ),
    'BTU' => array(
        'fake'=> false
        ),
    'BTD' => array(
        'fake'=> false
        ),
    'HVE' => array(
        'fake'=> false
        ),  
    'HPD' => array(
        'fake'=> false
        ),
    'HKD' => array(
        'fake'=> false
        ),
    'HTD' => array(
        'fake'=> false
        ),
    );

$x509Generator = new MyCompanyX509Generator ;




//$connection->HPDOrder(2, $code, $x509Generator);

//$connection->HKDOrder(1, $code, $x509Generator);


$connection->BTUOrder(1, $code, $x509Generator);

//$connection->HVEOrder(1, $code, $x509Generator);


//$connection->BTDOrder(2, $code, $x509Generator);

//$connection->Z54Order(1, $code, $x509Generator);

//$connection->INIOrder(1, $code, $x509Generator);

//$connection->HIAOrder(1, $code, $x509Generator);

//$connection->HPBOrder(1, $code, $x509Generator);

//$connection->GenerateLetters(0, $x509Generator, '/Users/sarahmoreau/Documents/NewLetters.html');

// $a = [
//     'subject' => [
//        'DN' => [
//            'id-at-countryName' => 'FR',
//            'id-at-stateOrProvinceName' => 'State',
//            'id-at-localityName' => 'City',
//            'id-at-organizationName' => 'Your company',
//            'id-at-commonName' => 'yourwebsite.tld',
//            ]
//        ],
//        'extensions' => [
//            'id-ce-subjectAltName' => [
//            'value' => [
//                'dNSName' => '*.yourwebsite.tld',
//            ]
//        ],
//    ],
// ];

// $path ='/Users/sarahmoreau/MC_EBICS_PHP_Client/EBICS_OM/_data/certificate/X509.json';

// $test = json_decode(file_get_contents($path), true);
// echo'TEST <BR>';
// echo '<pre>'; print_r($test); echo '</pre>';


// $b = json_encode($a);

// echo $b;

// $c = json_decode($b);

// echo '<pre>'; print_r($c); echo '</pre>';

?>