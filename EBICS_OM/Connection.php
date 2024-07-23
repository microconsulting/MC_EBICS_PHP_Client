<?php

include_once('PathFile.php');
use AndrewSvirin\Ebics\Contexts\BTUContext;
use AndrewSvirin\Ebics\Contexts\BTDContext;
use AndrewSvirin\Ebics\Contexts\HVEContext;

// modify the path of the php file to be found when running the script
new PathFile;

use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
include_once("AbstractEbicsClient.php");

// Class bringing together all the functions necessary for the ebics connection
Class Connection extends AbstractEblicsClient
{
    // Execute the INI order
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

        return $code;
    }


    // Execute the HIA order
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

        return $code;
    }


    // Execute the HPB order
    public function HPBOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo 'HPB Order <BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HPB']['fake']);
        $hpb = $client->HPB();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hpb->getTransaction()->getInitializationSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hpb->getTransaction()->getInitializationSegment()->getResponse());
        $this->saveKeyring($credentialsId, $client->getKeyring());

        return $code;
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


    public function BTUOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null){

        echo '*** BTU *** <BR><BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['BTU']['fake']);
        //$customerCreditTransfer = $this->buildCustomerCreditTransfer('urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        // XE2
        $context = new BTUContext();
        $context->setServiceName('MCT');
        $context->setScope('CH');
        $context->setMsgName('pain.001');
        $context->setMsgNameVersion('03');
        $context->setFileName('Virements_20240717_112958.xml');
        $context->setFileData(file_get_contents('/Users/sarahmoreau/Desktop/Virements_20240717_112958.xml'));

        //$context->setFileName('xe2.pain001.xml');
        //$context->setFileData($customerCreditTransfer->getContent());
        //$context->setFileDocument($customerCreditTransfer);

        $btu = $client->BTU($context);

        $responseHandler = $client->getResponseHandler();

        $code = $responseHandler->retrieveH00XReturnCode($btu->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($btu->getTransaction()->getLastSegment()->getResponse());
        
        file_put_contents('/Users/sarahmoreau/Desktop/Data.xml',$btu->getData());
        file_put_contents('/Users/sarahmoreau/Desktop/Last.xml',$btu->getTransaction()->getLastSegment()->getResponse()->getContent());
        file_put_contents('/Users/sarahmoreau/Desktop/Init.xml',$btu->getTransaction()->getInitialization()->getResponse()->getContent());

        echo $reportText;

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM !!! <BR>';}
        
        $code = $responseHandler->retrieveH00XReturnCode($btu->getTransaction()->getInitialization()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($btu->getTransaction()->getInitialization()->getResponse());
        echo $reportText;

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM !!! <BR>';}

    }


    public function HVEOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HVE']['fake']);

        //$this->assertExceptionCode($codes['HVE']['code']);

        $context = new HVEContext();
        $context->setOrderId('N0KB');
        $context->setServiceName('SDD');
        $context->setOrderType('CDX');
        $context->setScope('DE');
        $context->setServiceOption('0CDX');
        $context->setMsgName('pain.008');
        $context->setPartnerId('PFC00591');
        $context->setDigest('--digset--');

        $hve = $client->HVE($context);

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hve->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hve->getTransaction()->getLastSegment()->getResponse());
        //$this->assertResponseOk($code, $reportText);
        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM !!! <BR>';}

        $code = $responseHandler->retrieveH00XReturnCode($hve->getTransaction()->getInitialization()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hve->getTransaction()->getInitialization()->getResponse());

        //$this->assertResponseDone($code, $reportText);
        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM !!! <BR>';}
    }


    // Execute BTU order
    public function BTDOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['BTD']['fake']);

        $context = new BTDContext();

        //pain.002
        // $context->setServiceName('PSR');
        // $context->setMsgName('pain.002');
        // $context->setMsgNameVersion('03');
        // $context->setScope('CH');
        // $context->setContainerType('ZIP');

        //camt.054
        $context->setServiceName('REP');
        $context->setMsgName('camt.054');
        $context->setMsgNameVersion('04');
        $context->setScope('CH');
        $context->setContainerType('ZIP');

        $btd = $client->BTD($context, null, new DateTime('2024-07-05'), new DateTime('2024-07-12'));

        $responseHandler = $client->getResponseHandler();

        $code = $responseHandler->retrieveH00XReturnCode($btd->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($btd->getTransaction()->getLastSegment()->getResponse());

        $code = $responseHandler->retrieveH00XReturnCode($btd->getTransaction()->getReceipt());
        $reportText = $responseHandler->retrieveH00XReportText($btd->getTransaction()->getReceipt());

        return $code;

    }

    public function HPDOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HPD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HPD']['fake']);

        //$this->assertExceptionCode($codes['HPD']['code']);
        $hpd = $client->HPD();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hpd->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hpd->getTransaction()->getLastSegment()->getResponse());
        //$this->assertResponseOk($code, $reportText);

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}

        $code = $responseHandler->retrieveH00XReturnCode($hpd->getTransaction()->getReceipt());
        $reportText = $responseHandler->retrieveH00XReportText($hpd->getTransaction()->getReceipt());

        //$this->assertResponseDone($code, $reportText);

        file_put_contents('/Users/sarahmoreau/Desktop/hpd.xml',$hpd->getTransaction()->getOrderData());

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}
    }


    public function HKDOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HKD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HKD']['fake']);

        //$this->assertExceptionCode($codes['HKD']['code']);
        $hkd = $client->HKD();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hkd->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hkd->getTransaction()->getLastSegment()->getResponse());
        //$this->assertResponseOk($code, $reportText);

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}

        $code = $responseHandler->retrieveH00XReturnCode($hkd->getTransaction()->getReceipt());
        $reportText = $responseHandler->retrieveH00XReportText($hkd->getTransaction()->getReceipt());

        file_put_contents('/Users/sarahmoreau/Desktop/hkd.xml',$hkd->getTransaction()->getOrderData());

        //$this->assertResponseDone($code, $reportText);

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}
    }

    public function HTDOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HKD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator, $codes['HTD']['fake']);

        //$this->assertExceptionCode($codes['HKD']['code']);
        $htd = $client->HTD();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($htd->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($htd->getTransaction()->getLastSegment()->getResponse());
        //$this->assertResponseOk($code, $reportText);

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}

        $code = $responseHandler->retrieveH00XReturnCode($htd->getTransaction()->getReceipt());
        $reportText = $responseHandler->retrieveH00XReportText($htd->getTransaction()->getReceipt());

        //$this->assertResponseDone($code, $reportText);

        if ($code == '000000'){
            echo '<BR> 1 : OK ! <BR>';
        } else {echo '<BR> 1 : PROBLEM : ', $reportText, '<BR>';}
    }

}

?>