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
    public function INIOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo 'INI Order <BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator);

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
    public function HIAOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo 'HIA Order <BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator);

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
    public function HPBOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo 'HPB Order <BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator);
        $hpb = $client->HPB();

        $responseHandler = $client->getResponseHandler();
        $code = $responseHandler->retrieveH00XReturnCode($hpb->getTransaction()->getInitializationSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($hpb->getTransaction()->getInitializationSegment()->getResponse());
        $this->saveKeyring($credentialsId, $client->getKeyring());

        return $code;
    }


     // Generate INI and HIA letters
     public function GenerateLetters(int $credentialsId, X509GeneratorInterface $x509Generator = null, string $pathHtml){

        //echo 'Generate INI and HIA letters <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator);

        $ebicsBankLetter = new \AndrewSvirin\Ebics\EbicsBankLetter();

        $bankLetter = $ebicsBankLetter->prepareBankLetter(
            $client->getBank(),
            $client->getUser(),
            $client->getKeyring()
        );

        $certificateA = substr($bankLetter->getSignatureBankLetterA()->getCertificateContent(), 29, strlen($bankLetter->getSignatureBankLetterA()->getCertificateContent())-28);
        $certificateA = substr($certificateA, 0, strlen($certificateA)-27);

        $certificateX = substr($bankLetter->getSignatureBankLetterX()->getCertificateContent(), 29, strlen($bankLetter->getSignatureBankLetterX()->getCertificateContent())-28);
        $certificateX = substr($certificateX, 0, strlen($certificateX)-27);
    
        $certificateE = substr($bankLetter->getSignatureBankLetterE()->getCertificateContent(), 29, strlen($bankLetter->getSignatureBankLetterE()->getCertificateContent())-28);
        $certificateE = substr($certificateE, 0, strlen($certificateE)-27);

        echo $bankLetter->getBank()->getHostId();
        echo ";";
        echo $bankLetter->getUser()->getUserId();
        echo ";";
        echo $bankLetter->getUser()->getPartnerId();
        echo ";";
        //echo $bankLetter->getSignatureBankLetterA()->getCertificateContent();
        echo $certificateA;
        echo ";";
        echo  $bankLetter->getSignatureBankLetterA()->getKeyHash();
        echo ";";
        //echo $bankLetter->getSignatureBankLetterX()->getCertificateContent();
        echo $certificateX;
        echo ";";
        echo  $bankLetter->getSignatureBankLetterX()->getKeyHash();
        echo ";";
        //echo $bankLetter->getSignatureBankLetterE()->getCertificateContent();
        echo $certificateE;
        echo ";";
        echo  $bankLetter->getSignatureBankLetterE()->getKeyHash();
    }


    public function BTUOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null){

        echo '*** BTU *** <BR><BR>';
        $client = $this->setupClientV3($credentialsId, $x509Generator);
        $customerCreditTransfer = $this->buildCustomerCreditTransfer('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');

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

        echo ' response Handler <BR>';
        var_dump($responseHandler);

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


    public function HVEOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        $client = $this->setupClientV3($credentialsId, $x509Generator);

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




    public function HPDOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HPD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator);

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


    public function HKDOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HKD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator);

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

    public function HTDOrder(int $credentialsId, X509GeneratorInterface $x509Generator = null)
    {
        echo '*** HKD *** <BR><BR>';

        $client = $this->setupClientV3($credentialsId, $x509Generator);

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