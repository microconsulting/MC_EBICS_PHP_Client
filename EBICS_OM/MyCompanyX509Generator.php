<?php

namespace App\Factories\X509;

use AndrewSvirin\Ebics\Models\X509\AbstractX509Generator;

class MyCompanyX509Generator extends AbstractX509Generator
{
    protected function getCertificateOptions() : array {
        
        return [
             'subject' => [
                'DN' => [
                    'id-at-countryName' => 'FR',
                    'id-at-stateOrProvinceName' => 'State',
                    'id-at-localityName' => 'City',
                    'id-at-organizationName' => 'Your company',
                    'id-at-commonName' => 'yourwebsite.tld',
                    ]
                ],
            //     'extensions' => [
            //         'id-ce-subjectAltName' => [
            //         'value' => [
            //             'dNSName' => '*.yourwebsite.tld',
            //         ]
            //     ],
            // ],
        ];
    }
}

?>