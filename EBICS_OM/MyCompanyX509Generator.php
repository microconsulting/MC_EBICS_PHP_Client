<?php

namespace App\Factories\X509;

use AndrewSvirin\Ebics\Models\X509\AbstractX509Generator;

class MyCompanyX509Generator extends AbstractX509Generator
{
    protected function getCertificateOptions() : array {
        $path = __DIR__ . '/_data/certificate/X509.json';
        return json_decode(file_get_contents($path), true);
    }
}

?>