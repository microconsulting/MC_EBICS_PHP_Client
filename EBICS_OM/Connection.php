<?php

include_once('PathFile.php');

// modify the path of the php file to be found when running the script
new PathFile;

use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
include_once("AbstractEbicsClient.php");

// Class bringing together all the functions necessary for the ebics connection
Class Connection extends AbstractEblicsClient
{
    public function INIOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo 'INI Order <BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['INI']['fake']);

        // Check that keyring is empty and or wait on success or wait on exception.
        $userExists = $client->getKeyring()->getUserSignatureA();

        if ($userExists){
            $code='091002';
            $reportText= "[EBICS_INVALID_USER_OR_USER_STATE] Subscriber unknown or subscriber state inadmissible";
        }
        
        else{
            $ini = $client->INI();
            $responseHandler = $client->getResponseHandler();
            $this->saveKeyring($credentialsId, $client->getKeyring());
            $code = $responseHandler->retrieveH00XReturnCode($ini);
            $reportText = $responseHandler->retrieveH00XReportText($ini);
        }

        echo 'code : ', $code, '<BR>';
        echo 'reportText : ', $reportText, '<BR>';
    }


    public function HIAOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo 'HIA Order <BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HIA']['fake']);

        //Check that keyring is empty and or wait on success or wait on exception.
        $bankExists = $client->getKeyring()->getUserSignatureX();
               
        if ($bankExists) {
            $code='091002';
            $reportText= "[EBICS_INVALID_USER_OR_USER_STATE] Subscriber unknown or subscriber state inadmissible";
        }
    
        else{
            $hia = $client->HIA();
            $responseHandler = $client->getResponseHandler();
            $this->saveKeyring($credentialsId, $client->getKeyring());
            $code = $responseHandler->retrieveH00XReturnCode($hia);
            $reportText = $responseHandler->retrieveH00XReportText($hia);
        }

        echo 'code : ', $code, '<BR>';
        echo 'reportText : ', $reportText, '<BR>';
    }


    public function HPBOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo 'HPB Order <BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HPB']['fake']);

        //$this->assertExceptionCode($codes['HPB']['code']);

        $hpb = $client->HPB();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hpb->getTransaction()->getInitializationSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hpb->getTransaction()->getInitializationSegment()->getResponse());
        //$this->assertResponseOk($code, $reportText);
        $this->saveKeyring($credentialsId, $client->getKeyring());

        echo 'code : ', $code, '<BR>';
        echo 'reportText : ', $reportText, '<BR>';
    }


     // Generate INI and HIA letters
     public function GenerateLetters(int $credentialsId, X509GeneratorInterface $x509Generator = null, string $pathHtml){

        echo 'Generate INI and HIA letters';

        $client = $this->setupClientV3($credentialsId, $x509Generator, false);

        $ebicsBankLetter = new \AndrewSvirin\Ebics\EbicsBankLetter();

        $bankLetter = $ebicsBankLetter->prepareBankLetter(
            $client->getBank(),
            $client->getUser(),
            $client->getKeyring()
        );
     
        if(true){
           $Html = $ebicsBankLetter->formatBankLetter($bankLetter, $ebicsBankLetter->createHtmlBankLetterFormatter()); // Export HTML (Impotable dans Write PRO???? A tester WP New avec Source HTML pour voir comment s'est importé)

            // write the content of the letters in a file
            $htmlfile = fopen($pathHtml, "w");
            fwrite($htmlfile, $Html);
            
        }
        else{
            $pdf = $ebicsBankLetter->formatBankLetter($bankLetter, $ebicsBankLetter->createPdfBankLetterFormatter()); // Export pdf
            
            $Txt = $ebicsBankLetter->formatBankLetter($bankLetter, $ebicsBankLetter->createTxtBankLetterFormatter()); // Export TXT
        }
    }

}

?>