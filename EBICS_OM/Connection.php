<?php

include_once('PathFile.php');

// modify the path of the php file to be found when running the script
new PathFile;

use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
include_once("AbstractEbicsClient.php");

Class Connection extends AbstractEblicsClient
{
    public function INIOrder(int $credentialsId, array $codes, X509GeneratorInterface $x509Generator = null)
    {
        try{
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
        catch (Exception $e) {
            echo '<font color="red"> - Caught exception : ' . $e->getMessage() . "</font><br>";
            echo '<font color="red"> - Stack : ' . str_replace('#', '<br>&emsp; • #',$e->getTraceAsString()) . "</font><br><br>";
        }
        catch(TypeError $e){
            echo '<font color="red"> - Caught TypeError : ' . $e->getMessage() . "</font><br>";
            echo '<font color="red"> - Stack : ' . str_replace('#', '<br>&emsp; • #',$e->getTraceAsString()) . "</font><br><br>";
        }
        catch (\Throwable $e) {
            echo '<font color="red"> - Caught Throwable Error : ' . $e->getMessage() . "</font><br>";
            echo '<font color="red"> - Stack : ' . str_replace('#', '<br>&emsp; • #',$e->getTraceAsString()) . "</font><br><br>";
        }
    }

}

?>