<?php

include_once('PathFile.php');
use AndrewSvirin\Ebics\Contexts\BTUContext;
use AndrewSvirin\Ebics\Contexts\BTDContext;

// modify the path of the php file to be found when running the script
new PathFile;

use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
include_once("AbstractEbicsClient.php");

// Class bringing together all the functions necessary for the ebics payments
Class Payments extends AbstractEblicsClient
{
    // Execute BTD order for download camt.054 files
    public function BTDOrder(int $credentialsId, string $startDate, string $endDate, X509GeneratorInterface $x509Generator = null)
    {
        $client = $this->setupClientV3($credentialsId, $x509Generator);

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

        $btd = $client->BTD($context, null, new DateTime($startDate), new DateTime($endDate));

        $responseHandler = $client->getResponseHandler();

        $code = $responseHandler->retrieveH00XReturnCode($btd->getTransaction()->getLastSegment()->getResponse());
        $reportText = $responseHandler->retrieveH00XReportText($btd->getTransaction()->getLastSegment()->getResponse());

        $code = $responseHandler->retrieveH00XReturnCode($btd->getTransaction()->getReceipt());
        $reportText = $responseHandler->retrieveH00XReportText($btd->getTransaction()->getReceipt());

        return $code;

    }

}
?>