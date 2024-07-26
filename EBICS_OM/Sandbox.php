<?php
//use App\Factories\X509\MyCompanyX509Generator;
use AndrewSvirin\Ebics\Models\Data;
include_once("PathFile.php");

include_once("Connection.php");
include_once("MyCompanyX509Generator.php");

new PathFile() ;
$connection = new Connection();


$x509Generator = new MyCompanyX509Generator ;




//$connection->HPDOrder(2, $x509Generator);

//$connection->HKDOrder(1, $x509Generator);


//$connection->BTUOrder(1, $x509Generator);

//$connection->HVEOrder(1, $x509Generator);


//$connection->BTDOrder(2, $x509Generator);

//$connection->Z54Order(1, $x509Generator);

//$connection->INIOrder(1, $x509Generator);

//$connection->HIAOrder(1,$x509Generator);

//$connection->HPBOrder(1, $x509Generator);

//$connection->GenerateLetters(1, $x509Generator, '/Users/sarahmoreau/Documents/NewLetters.html');

// $str ="-----BEGIN CERTIFICATE-----\r\nMIIEBjCCAu6gAwIBAgIUMTUzMjY1Mjc3OTIwNTU1NTUzODMwDQYJKoZIhvcNAQEL\r\nBQAwgYIxCzAJBgNVBAYMAkNIMQ0wCwYDVQQIDARWYXVkMR0wGwYDVQQHDBRMZSBN\r\nb250LXN1ci1MYXVzYW5uZTEcMBoGA1UECgwTTWljcm8gQ29uc3VsdGluZyBTQTEn\r\nMCUGA1UEAwweaHR0cHM6Ly93d3cubWljcm9jb25zdWx0aW5nLmNoMB4XDTI0MDcy\r\nMjExNTQyOVoXDTI1MDcyMzExNTQyOVowgYIxCzAJBgNVBAYMAkNIMQ0wCwYDVQQI\r\nDARWYXVkMR0wGwYDVQQHDBRMZSBNb250LXN1ci1MYXVzYW5uZTEcMBoGA1UECgwT\r\nTWljcm8gQ29uc3VsdGluZyBTQTEnMCUGA1UEAwweaHR0cHM6Ly93d3cubWljcm9j\r\nb25zdWx0aW5nLmNoMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlbFq\r\nwZTHyWj55XCvV9yJQF1RQjcSxs6LY2TNGi3hsvEirBPRngqa2qBJ+Wje49CAxX04\r\n6ZvTgAjlac2njiQaf75zpOfO1IRdG+eBQ0s1+SW9tNghoQb0hPnUH05koZPz+GDI\r\ngZVep+IMvsl1CktklAyxQ3I0Hn2cz4Qe1cmTF8oVwT+gdbEObt2NXKFH/QyXbtX8\r\nNCPZ1O+1IsayAykts1KLLCFCI3Eq2e20hTjW6/Kvw0adYn5+EfVQboC6TnpxdMQY\r\nZz4aIq0Ev+dn1wFzAUrCFtg7MquZy73b2dc1UyvBl8RusrqlnVb4btVLuKAILy4p\r\nd9MEXMBwFgOn8oNlKwIDAQABo3IwcDAdBgNVHQ4EFgQU/xP5I6GEwRYDZeGKw5fO\r\nxbUGp3cwCQYDVR0TBAIwADATBgNVHSUEDDAKBggrBgEFBQcDBDAOBgNVHQ8BAf8E\r\nBAMCBkAwHwYDVR0jBBgwFoAU/xP5I6GEwRYDZeGKw5fOxbUGp3cwDQYJKoZIhvcN\r\nAQELBQADggEBADnR7aRO9/QMKbzy9yd2mwtnXLh1V6sdBPpAn2O8sVEEHPiST2Fs\r\nvXeNQKHIjaCo19aGlJ9lShCj7M/UbyWyoeVrrf8j6FGEbHng8TmQ9JPV398Qiwg6\r\nt5ZoIkjhnNn9molUCnS5jcY4FgZRfFY68VH4GGsvamf5N5+o9SePdoPNk4TlGFsG\r\nq69ws6uLjKmgzrPsFUVibO1EQcQ/acFnjJrj1SZ+lA9m1Qtvg5XR88VLs8j1pSKn\r\n5aTPVMfAOWRLWtTGInwXD0CfB0J3CiRh8lxGEVKvHXXkLvUMlkZ3jDMKPnypGfVq\r\ncZaJFkBZoKqDweeuEfGj6ra33DgTZioGaeI=\r\n-----END CERTIFICATE-----";
// echo $str;
// echo "<BR><BR>";

// $certificateA = substr($str, 28, strlen($str)-28);
// echo $certificateA;

// $certificateA = substr($certificateA, 0, strlen($certificateA)-26);
// echo "<BR><BR>";
// echo $certificateA;

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