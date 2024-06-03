<?php
use App\Factories\X509\MyCompanyX509Generator;
include_once("PathFile.php");
new PathFile() ;
include_once("Connection.php");
include_once("MyCompanyX509Generator.php");
$connection = new Connection();

$code = array(
    'INI' => array(
        'fake' => false
        ),
    'HIA' => array(
        'fake' => false
        ),
    );

$x509Generator = new MyCompanyX509Generator ;
//$connection->INIOrder(0, $code, $x509Generator);

$connection->HIAOrder(0, $code, $x509Generator);

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

// $b = json_encode($a);

// echo $b;

// $c = json_decode($b);

// echo '<pre>'; print_r($c); echo '</pre>';

?>