<?php

namespace AndrewSvirin\Ebics\Tests;

use AndrewSvirin\Ebics\Contracts\HttpClientInterface;
use AndrewSvirin\Ebics\Models\Http\Request;
use AndrewSvirin\Ebics\Models\Http\Response;
use LogicException;

/**
 * Class EbicsClientTest.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author Andrew Svirin
 *
 * @group ebics-client
 */
class FakerHttpClient implements HttpClientInterface
{

    /**
     * @var string
     */
    private $fixturesDir;

    public function __construct(string $fixturesDir)
    {
        $this->fixturesDir = $fixturesDir;
    }

    public function post(
        string $url,
        Request $request
    ): Response {
        $requestContent = $request->getContent();
        if (preg_match('/<OrderType>(?<order_type>.*)<\/OrderType>/', $requestContent, $matches) && !empty($matches)) {
            preg_match('/<FileFormat.*>(?<file_format>.*)<\/FileFormat>/', $requestContent, $fileFormatMatches);
            return $this->fixtureOrderType($matches['order_type'], [
                'file_format' => $fileFormatMatches['file_format'] ?? null,
            ]);
        } elseif (preg_match('/<TransactionPhase>(?<transaction_phase>.*)<\/TransactionPhase>/', $requestContent,
                $matches) || empty($matches)) {
            return $this->fixtureTransactionPhase($matches['transaction_phase']);
        } else {
            return new Response();
        }
    }

    /**
     * Fake Order type responses.
     *
     * @param string $orderType
     * @param array|null $options = [
     *     'file_format' => '<string>',
     * ]
     *
     * @return Response
     */
    private function fixtureOrderType(string $orderType, array $options = null): Response
    {
        switch ($orderType) {
            case 'FDL':
                $fileName = sprintf('fdl.%s.xml', $options['file_format']);
                break;
            case 'C53':
                $fileName = 'c53.xml';
                break;
            case 'STA':
                $fileName = 'sta.xml';
                break;
            case 'CCT':
                $fileName = 'cct.xml';
                break;
            case 'CDD':
                $fileName = 'cdd.xml';
                break;
            case 'CDB':
                $fileName = 'cdb.xml';
                break;
            default:
                throw new LogicException(sprintf('Faked order type `%s` not supported.', $orderType));
        }

        $fixturePath = $this->fixturesDir . '/' . $fileName;

        if (!is_file($fixturePath)) {
            throw new LogicException('Fixtures file does not exists.');
        }

        $response = new Response();

        $responseContent = file_get_contents($fixturePath);

        $response->loadXML($responseContent);

        return $response;
    }

    /**
     * Fake transaction phase responses.
     *
     * @param $transactionPhase
     *
     * @return Response
     */
    private function fixtureTransactionPhase($transactionPhase): Response
    {
        switch ($transactionPhase) {
            case 'Receipt':
                $fileName = 'receipt.xml';
                break;
            case 'Transfer':
                $fileName = 'transfer.xml';
                break;
            default:
                throw new LogicException(sprintf('Faked transaction phase `%s` not supported.', $transactionPhase));
        }

        $fixturePath = $this->fixturesDir . '/' . $fileName;

        if (!is_file($fixturePath)) {
            throw new LogicException('Fixtures file does not exists.');
        }

        $response = new Response();

        $responseContent = file_get_contents($fixturePath);

        $response->loadXML($responseContent);

        return $response;
    }
}
