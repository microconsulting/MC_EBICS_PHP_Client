<?php

namespace AndrewSvirin\Ebics;

use AndrewSvirin\Ebics\Contexts\BTDContext;
use AndrewSvirin\Ebics\Contexts\BTUContext;
use AndrewSvirin\Ebics\Contexts\FULContext;
use AndrewSvirin\Ebics\Contexts\HVDContext;
use AndrewSvirin\Ebics\Contexts\HVEContext;
use AndrewSvirin\Ebics\Contexts\HVTContext;
use AndrewSvirin\Ebics\Contracts\EbicsClientInterface;
use AndrewSvirin\Ebics\Contracts\HttpClientInterface;
use AndrewSvirin\Ebics\Contracts\OrderDataInterface;
use AndrewSvirin\Ebics\Contracts\SignatureInterface;
use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Exceptions\NoDownloadDataAvailableException;
use AndrewSvirin\Ebics\Factories\DocumentFactory;
use AndrewSvirin\Ebics\Factories\EbicsExceptionFactory;
use AndrewSvirin\Ebics\Factories\OrderResultFactory;
use AndrewSvirin\Ebics\Factories\RequestFactory;
use AndrewSvirin\Ebics\Factories\RequestFactoryV24;
use AndrewSvirin\Ebics\Factories\RequestFactoryV25;
use AndrewSvirin\Ebics\Factories\RequestFactoryV3;
use AndrewSvirin\Ebics\Factories\SegmentFactory;
use AndrewSvirin\Ebics\Factories\SignatureFactory;
use AndrewSvirin\Ebics\Factories\TransactionFactory;
use AndrewSvirin\Ebics\Handlers\OrderDataHandler;
use AndrewSvirin\Ebics\Handlers\OrderDataHandlerV24;
use AndrewSvirin\Ebics\Handlers\OrderDataHandlerV25;
use AndrewSvirin\Ebics\Handlers\OrderDataHandlerV3;
use AndrewSvirin\Ebics\Handlers\ResponseHandler;
use AndrewSvirin\Ebics\Handlers\ResponseHandlerV24;
use AndrewSvirin\Ebics\Handlers\ResponseHandlerV25;
use AndrewSvirin\Ebics\Handlers\ResponseHandlerV3;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\Document;
use AndrewSvirin\Ebics\Models\DownloadOrderResult;
use AndrewSvirin\Ebics\Models\DownloadSegment;
use AndrewSvirin\Ebics\Models\DownloadTransaction;
use AndrewSvirin\Ebics\Models\Http\Request;
use AndrewSvirin\Ebics\Models\Http\Response;
use AndrewSvirin\Ebics\Models\InitializationOrderResult;
use AndrewSvirin\Ebics\Models\InitializationSegment;
use AndrewSvirin\Ebics\Models\InitializationTransaction;
use AndrewSvirin\Ebics\Models\Keyring;
use AndrewSvirin\Ebics\Models\UploadOrderResult;
use AndrewSvirin\Ebics\Models\UploadTransaction;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Services\CryptService;
use AndrewSvirin\Ebics\Services\CurlHttpClient;
use AndrewSvirin\Ebics\Services\XmlService;
use AndrewSvirin\Ebics\Services\ZipService;
use DateTime;
use DateTimeInterface;
use LogicException;
use DOMDocument;

/**
 * EBICS client representation.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author Andrew Svirin
 */
final class EbicsClient implements EbicsClientInterface
{
    private Bank $bank;
    private User $user;
    private Keyring $keyring;
    private OrderDataHandler $orderDataHandler;
    private ResponseHandler $responseHandler;
    private RequestFactory $requestFactory;
    private CryptService $cryptService;
    private ZipService $zipService;
    private XmlService $xmlService;
    private DocumentFactory $documentFactory;
    private OrderResultFactory $orderResultFactory;
    private SignatureFactory $signatureFactory;
    private ?X509GeneratorInterface $x509Generator;
    private HttpClientInterface $httpClient;
    private TransactionFactory $transactionFactory;
    private SegmentFactory $segmentFactory;

    /**
     * Constructor.
     *
     * @param Bank $bank
     * @param User $user
     * @param Keyring $keyring
     */
    public function __construct(Bank $bank, User $user, Keyring $keyring)
    {
        $this->bank = $bank;
        $this->user = $user;
        $this->keyring = $keyring;

        if (Keyring::VERSION_24 === $keyring->getVersion()) {
            $this->requestFactory = new RequestFactoryV24($bank, $user, $keyring);
            $this->orderDataHandler = new OrderDataHandlerV24($bank, $user, $keyring);
            $this->responseHandler = new ResponseHandlerV24();
        } elseif (Keyring::VERSION_25 === $keyring->getVersion()) {
            $this->requestFactory = new RequestFactoryV25($bank, $user, $keyring);
            $this->orderDataHandler = new OrderDataHandlerV25($bank, $user, $keyring);
            $this->responseHandler = new ResponseHandlerV25();
        } elseif (Keyring::VERSION_30 === $keyring->getVersion()) {
            $this->requestFactory = new RequestFactoryV3($bank, $user, $keyring);
            $this->orderDataHandler = new OrderDataHandlerV3($bank, $user, $keyring);
            $this->responseHandler = new ResponseHandlerV3();
        } else {
            throw new LogicException(sprintf('Version "%s" is not implemented', $keyring->getVersion()));
        }

        $this->cryptService = new CryptService();
        $this->xmlService = new XmlService();
        $this->zipService = new ZipService();
        $this->signatureFactory = new SignatureFactory();
        $this->documentFactory = new DocumentFactory();
        $this->orderResultFactory = new OrderResultFactory();
        $this->transactionFactory = new TransactionFactory();
        $this->segmentFactory = new SegmentFactory();
        // Set default http client.
        $this->httpClient = new CurlHttpClient();
    }

    /**
     * @inheritDoc
     * @throws EbicsException
     */
    public function createUserSignatures(): void
    {
        $signatureA = $this->getUserSignature(SignatureInterface::TYPE_A, true);
        $this->keyring->setUserSignatureA($signatureA);

        $signatureE = $this->getUserSignature(SignatureInterface::TYPE_E, true);
        $this->keyring->setUserSignatureE($signatureE);

        $signatureX = $this->getUserSignature(SignatureInterface::TYPE_X, true);
        $this->keyring->setUserSignatureX($signatureX);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     */
    public function HEV(): Response
    {
        $request = $this->requestFactory->createHEV();
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH000ReturnCode($request, $response);

        return $response;
    }

    /**
     * @inheritDoc
     *
     * @throws EbicsException
     */
    public function INI(DateTimeInterface $dateTime = null, bool $createSignature = false): Response
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $signatureA = $this->getUserSignature(SignatureInterface::TYPE_A, $createSignature);

        $request = $this->requestFactory->createINI($signatureA, $dateTime);
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);
        $this->keyring->setUserSignatureA($signatureA);

        return $response;
    }

    /**
     * @inheritDoc
     * @throws EbicsException
     */
    public function HIA(DateTimeInterface $dateTime = null, bool $createSignature = false): Response
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $signatureE = $this->getUserSignature(SignatureInterface::TYPE_E, $createSignature);
        $signatureX = $this->getUserSignature(SignatureInterface::TYPE_X, $createSignature);

        $request = $this->requestFactory->createHIA($signatureE, $signatureX, $dateTime);
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);
        $this->keyring->setUserSignatureE($signatureE);
        $this->keyring->setUserSignatureX($signatureX);

        return $response;
    }

    /**
     * @inheritDoc
     * @throws EbicsException
     */
    // @codingStandardsIgnoreStart
    public function H3K(DateTimeInterface $dateTime = null, bool $createSignature = false): Response
    {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $signatureA = $this->getUserSignature(SignatureInterface::TYPE_A, $createSignature);
        $signatureE = $this->getUserSignature(SignatureInterface::TYPE_E, $createSignature);
        $signatureX = $this->getUserSignature(SignatureInterface::TYPE_X, $createSignature);

        $request = $this->requestFactory->createH3K($signatureA, $signatureE, $signatureX, $dateTime);
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);
        $this->keyring->setUserSignatureA($signatureA);
        $this->keyring->setUserSignatureE($signatureE);
        $this->keyring->setUserSignatureX($signatureX);

        return $response;
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function HPB(DateTimeInterface $dateTime = null): InitializationOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->initializeTransaction(
            function () use ($dateTime) {
                return $this->requestFactory->createHPB($dateTime);
            }
        );

        $orderResult = $this->createInitializationOrderResult($transaction);

        $signatureX = $this->orderDataHandler->retrieveAuthenticationSignature($orderResult->getDataDocument());
        $signatureE = $this->orderDataHandler->retrieveEncryptionSignature($orderResult->getDataDocument());
        $this->keyring->setBankSignatureX($signatureX);
        $this->keyring->setBankSignatureE($signatureE);

        return $orderResult;
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function BTD(
        BTDContext $btfContext,
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        // try {
            $transaction = $this->downloadTransaction(
                function ($segmentNumber, $isLastSegment) use ($dateTime, $btfContext, $startDateTime, $endDateTime) {
                    return $this->requestFactory->createBTD(
                        $dateTime,
                        $btfContext,
                        $startDateTime,
                        $endDateTime,
                        $segmentNumber,
                        $isLastSegment
                    );
                }
            );

            return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_TEXT);  
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function BTU(BTUContext $btuContext, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $btuContext) {
            $transaction->setOrderData($btuContext->getFileData());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createBTU(
                $btuContext,
                $dateTime,
                $transaction
            );
        });

        //return $this->createUploadOrderResult($transaction, $btuContext->getFileDocument());
        return $this->createUploadOrderResult($transaction);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function HPD(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHPD(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function HKD(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHKD(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function HTD(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHTD(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function PTK(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createPTK(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_TEXT);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function HAA(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHAA(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function VMK(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createVMK(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_TEXT);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function STA(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createSTA(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_TEXT);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function C52(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createC52(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function C53(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createC53(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function C54(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createC54(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function Z52(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createZ52(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function Z53(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createZ53(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    // @codingStandardsIgnoreStart
    public function Z54(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        // @codingStandardsIgnoreEnd
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createZ54(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function ZSR(
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null
    ): DownloadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime, $startDateTime, $endDateTime) {
                return $this->requestFactory->createZSR(
                    $dateTime,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_ZIP_FILES);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function FDL(
        $fileFormat,
        $parserFormat = self::FILE_PARSER_FORMAT_TEXT,
        $countryCode = self::COUNTRY_CODE_DE,
        DateTimeInterface $dateTime = null,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        $storeClosure = null
    ): DownloadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function (
                $segmentNumber,
                $isLastSegment
            ) use (
                $dateTime,
                $fileFormat,
                $countryCode,
                $startDateTime,
                $endDateTime
            ) {
                return $this->requestFactory->createFDL(
                    $dateTime,
                    $fileFormat,
                    $countryCode,
                    $startDateTime,
                    $endDateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            },
            $storeClosure
        );

        return $this->createDownloadOrderResult($transaction, $parserFormat);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsException
     */
    public function FUL(
        string $fileFormat,
        OrderDataInterface $orderData,
        FULContext $fulContext,
        DateTimeInterface $dateTime = null
    ): UploadOrderResult {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use (
            $orderData,
            $fileFormat,
            $fulContext,
            $dateTime
        ) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createFUL(
                $dateTime,
                $fileFormat,
                $fulContext,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function CCT(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($orderData, $dateTime) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createCCT(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function CDD(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createCDD(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function CDB(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createCDB(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function XE2(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createXE2(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function XE3(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createXE3(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function YCT(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createYCT(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function CIP(OrderDataInterface $orderData, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadTransaction(function (UploadTransaction $transaction) use ($dateTime, $orderData) {
            $transaction->setOrderData($orderData->getContent());
            $transaction->setDigest($this->cryptService->hash($transaction->getOrderData()));

            return $this->requestFactory->createCIP(
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadOrderResult($transaction, $orderData);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function HVU(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHVU($dateTime, $segmentNumber, $isLastSegment);
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function HVZ(DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($dateTime) {
                return $this->requestFactory->createHVZ(
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws EbicsException
     */
    public function HVE(HVEContext $hveContext, DateTimeInterface $dateTime = null): UploadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->uploadESTransaction(function (UploadTransaction $transaction) use (
            $dateTime,
            $hveContext
        ) {
            $transaction->setDigest($hveContext->getDigest());

            return $this->requestFactory->createHVE(
                $hveContext,
                $dateTime,
                $transaction
            );
        });

        return $this->createUploadESResult($transaction, $hveContext->getDigest());
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function HVD(HVDContext $hvdContext, DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($hvdContext, $dateTime) {
                return $this->requestFactory->createHVD(
                    $hvdContext,
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * @inheritDoc
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    public function HVT(HVTContext $hvtContext, DateTimeInterface $dateTime = null): DownloadOrderResult
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }

        $transaction = $this->downloadTransaction(
            function ($segmentNumber, $isLastSegment) use ($hvtContext, $dateTime) {
                return $this->requestFactory->createHVT(
                    $hvtContext,
                    $dateTime,
                    $segmentNumber,
                    $isLastSegment
                );
            }
        );

        return $this->createDownloadOrderResult($transaction, self::FILE_PARSER_FORMAT_XML);
    }

    /**
     * Mark download or upload transaction as receipt or not.
     *
     * @throws EbicsException
     * @throws Exceptions\EbicsResponseException
     */
    private function transferReceipt(DownloadTransaction $transaction, bool $acknowledged): void
    {
        $request = $this->requestFactory->createTransferReceipt($transaction->getId(), $acknowledged);
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);

        $transaction->setReceipt($response);
    }

    /**
     * Upload transaction segments and mark transaction as transfer.
     *
     * @throws EbicsException
     * @throws Exceptions\EbicsResponseException
     */
    private function transferTransfer(UploadTransaction $uploadTransaction): void
    {
        foreach ($uploadTransaction->getSegments() as $segment) {
            $request = $this->requestFactory->createTransferTransfer(
                $segment->getTransactionId(),
                $segment->getTransactionKey(),
                $segment->getOrderData(),
                $segment->getSegmentNumber(),
                $segment->getIsLastSegment()
            );
            $response = $this->httpClient->post($this->bank->getUrl(), $request);

            $this->checkH00XReturnCode($request, $response);

            $segment->setResponse($response);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @throws Exceptions\IncorrectResponseEbicsException
     */
    private function checkH00XReturnCode(Request $request, Response $response): void
    {
        $errorCode = $this->responseHandler->retrieveH00XBodyOrHeaderReturnCode($response);

        if ('000000' === $errorCode) {
            return;
        }

        // For Transaction Done.
        if ('011000' === $errorCode) {
            return;
        }

        $reportText = $this->responseHandler->retrieveH00XReportText($response);
        EbicsExceptionFactory::buildExceptionFromCode($errorCode, $reportText, $request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @throws Exceptions\IncorrectResponseEbicsException
     */
    private function checkH000ReturnCode(Request $request, Response $response): void
    {
        $errorCode = $this->responseHandler->retrieveH000ReturnCode($response);

        if ('000000' === $errorCode) {
            return;
        }

        $reportText = $this->responseHandler->retrieveH000ReportText($response);
        EbicsExceptionFactory::buildExceptionFromCode($errorCode, $reportText, $request, $response);
    }

    /**
     * Walk by segments to build transaction.
     *
     * @param callable $requestClosure
     *
     * @return InitializationTransaction
     * @throws Exceptions\EbicsResponseException
     */
    private function initializeTransaction(callable $requestClosure): InitializationTransaction
    {
        $transaction = $this->transactionFactory->createInitializationTransaction();

        $request = call_user_func($requestClosure);

        $segment = $this->retrieveInitializationSegment($request);
        $transaction->setInitializationSegment($segment);

        return $transaction;
    }

    /**
     * @throws Exceptions\EbicsResponseException
     */
    private function retrieveInitializationSegment(Request $request): InitializationSegment
    {
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);

        return $this->responseHandler->extractInitializationSegment($response, $this->keyring);
    }

    /**
     * Walk by segments to build transaction.
     *
     * @param callable $requestClosure
     * @param callable|null $storeClosure Custom closure to handle acknowledge.
     *
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    private function downloadTransaction(callable $requestClosure, callable $storeClosure = null): DownloadTransaction
    {
        $transaction = $this->transactionFactory->createDownloadTransaction();

        $segmentNumber = null;
        $isLastSegment = null;

        $request = call_user_func_array($requestClosure, [$segmentNumber, $isLastSegment]);

        $segment = $this->retrieveDownloadSegment($request);
        $transaction->addSegment($segment);
        while (!$transaction->getLastSegment()->isLastSegmentNumber()) {
            $request = call_user_func_array(
                $requestClosure,
                [
                    $transaction->getLastSegment()->getNextSegmentNumber(),
                    $transaction->getLastSegment()->isLastNextSegmentNumber(),
                ]
            );

            $segment = $this->retrieveDownloadSegment($request);
            $transaction->addSegment($segment);
        }

        if (null !== $storeClosure) {
            $acknowledged = call_user_func_array($storeClosure, [$transaction]);
        } else {
            $acknowledged = true;
        }

        $this->transferReceipt($transaction, $acknowledged);

        return $transaction;
    }

    /**
     * @throws Exceptions\EbicsResponseException
     * @throws EbicsException
     */
    private function retrieveDownloadSegment(Request $request): DownloadSegment
    {
        $response = $this->httpClient->post($this->bank->getUrl(), $request);

        $this->checkH00XReturnCode($request, $response);

        return $this->responseHandler->extractDownloadSegment($response, $this->keyring);
    }

    /**
     * @param callable $requestClosure
     *
     * @return UploadTransaction
     * @throws Exceptions\IncorrectResponseEbicsException
     */
    private function uploadESTransaction(callable $requestClosure): UploadTransaction
    {
        $transaction = $this->transactionFactory->createUploadTransaction();
        $transaction->setKey($this->cryptService->generateTransactionKey());
        $transaction->setNumSegments(0);

        $request = call_user_func_array($requestClosure, [$transaction]);

        $response = $this->httpClient->post($this->bank->getUrl(), $request);
        $this->checkH00XReturnCode($request, $response);

        $uploadSegment = $this->responseHandler->extractUploadSegment($request, $response);
        $transaction->setInitialization($uploadSegment);

        $segment = $this->segmentFactory->createTransferSegment();
        $segment->setTransactionKey($transaction->getKey());
        $segment->setSegmentNumber(1);
        $segment->setIsLastSegment(true);
        $segment->setNumSegments($transaction->getNumSegments());
        $segment->setOrderData('');
        $segment->setTransactionId($transaction->getInitialization()->getTransactionId());
        $transaction->addSegment($segment);
        $transaction->setKey($transaction->getInitialization()->getTransactionId());

        $this->transferTransfer($transaction);

        return $transaction;
    }

    function getTransactionPhase(DOMDocument $doc): ?string {
        // Cherche l'élément root ebicsRequest
        $root = $doc->getElementsByTagName('ebicsRequest')->item(0);

        if (!$root) {
            $root = $doc->getElementsByTagName('ebicsResponse')->item(0);
        }

        if ($root) {
            // Cherche l'élément header sous ebicsRequest
            $header = $root->getElementsByTagName('header')->item(0);
    
            if ($header) {
                // Cherche l'élément mutable sous header
                $mutable = $header->getElementsByTagName('mutable')->item(0);
    
                if ($mutable) {
                    // Cherche l'élément TransactionPhase sous mutable
                    $transactionPhase = $mutable->getElementsByTagName('TransactionPhase')->item(0);
    
                    if ($transactionPhase) {
                        // Récupère et retourne la valeur de TransactionPhase
                        return $transactionPhase->nodeValue;
                    }
                }
            }
        }
    
        return "";
    }

    /**
     * @param callable $requestClosure
     *
     * @throws EbicsException
     * @throws Exceptions\EbicsResponseException
     */
    private function uploadTransaction(callable $requestClosure): UploadTransaction
    {
        echo '<BR><BR> uploadTransaction <BR>';

        $transaction = $this->transactionFactory->createUploadTransaction();
        $transaction->setKey($this->cryptService->generateTransactionKey());
        $transaction->setNumSegments(1);

        var_dump($transaction);

        $request = call_user_func_array($requestClosure, [$transaction]);

        file_put_contents("request_".$this->getTransactionPhase($request).".xml",$request->getContent());

        $response = $this->httpClient->post($this->bank->getUrl(), $request);
        
        file_put_contents("response_".$this->getTransactionPhase($response).".xml",$response->getContent());

        $this->checkH00XReturnCode($request, $response);

        $uploadSegment = $this->responseHandler->extractUploadSegment($request, $response);
        $transaction->setInitialization($uploadSegment);

        $segment = $this->segmentFactory->createTransferSegment();
        $segment->setTransactionKey($transaction->getKey());
        $segment->setSegmentNumber(1);
        $segment->setIsLastSegment(true);
        $segment->setNumSegments($transaction->getNumSegments());
        $segment->setOrderData($transaction->getOrderData());
        $segment->setTransactionId($transaction->getInitialization()->getTransactionId());
        $transaction->addSegment($segment);
        $transaction->setKey($transaction->getInitialization()->getTransactionId());

        echo "<BR><BR> Transaction : <BR>";
        var_dump($transaction);

        $this->transferTransfer($transaction);

        return $transaction;
    }

    private function createInitializationOrderResult(InitializationTransaction $transaction): InitializationOrderResult
    {
        $orderResult = $this->orderResultFactory->createInitializationOrderResult();
        $orderResult->setTransaction($transaction);
        $orderResult->setData($transaction->getOrderData());
        $orderResult->setDataDocument($this->extractOrderDataDocument($orderResult->getData()));

        return $orderResult;
    }

    private function createDownloadOrderResult(
        DownloadTransaction $transaction,
        string $parserFormat
    ): DownloadOrderResult {
        $orderResult = $this->orderResultFactory->createDownloadOrderResult();
        $orderResult->setTransaction($transaction);
        $orderResult->setData($transaction->getOrderData());

        switch ($parserFormat) {
            case self::FILE_PARSER_FORMAT_TEXT:
                break;
            case self::FILE_PARSER_FORMAT_XML:
                $orderResult->setDataDocument($this->extractOrderDataDocument($orderResult->getData()));
                break;
            case self::FILE_PARSER_FORMAT_XML_FILES:
                $orderResult->setDataFiles($this->extractOrderDataXmlFiles($orderResult->getData()));
                break;
            case self::FILE_PARSER_FORMAT_ZIP_FILES:

                $orderResult->setDataFiles($this->extractOrderDataZipFiles($orderResult->getData()));
                break;
            default:
                throw new LogicException('Incorrect format');
        }

        return $orderResult;
    }

    //TODO:
    private function createUploadOrderResult(
        UploadTransaction $transaction,
        OrderDataInterface $document = null
    ): UploadOrderResult {
        $orderResult = $this->orderResultFactory->createUploadOrderResult();
        $orderResult->setTransaction($transaction);
        // $orderResult->setDataDocument($document);
        // $orderResult->setData($document->getContent());
        $orderResult->setData(file_get_contents("/Users/sarahmoreau/Desktop/Virements_20240717_112958.xml"));

        echo '<BR> **** Order Result **** <BR>';
        var_dump($orderResult);

        return $orderResult;
    }

    private function createUploadESResult(
        UploadTransaction $transaction,
        string $es
    ): UploadOrderResult {
        $orderResult = $this->orderResultFactory->createUploadOrderResult();
        $orderResult->setTransaction($transaction);
        $orderResult->setData($es);

        return $orderResult;
    }

    private function extractOrderDataDocument(string $orderData): Document
    {
        return $this->documentFactory->create($orderData);
    }

    /**
     * @return Document[]
     */
    private function extractOrderDataXmlFiles(string $orderData): array
    {
        $files = $this->xmlService->extractFilesFromString($orderData);

        return $this->documentFactory->createMultiple($files);
    }

    /**
     * @return Document[]
     */
    private function extractOrderDataZipFiles(string $orderData): array
    {
        $files = $this->zipService->extractFilesFromString($orderData);

        return $this->documentFactory->createMultiple($files);
    }

    /**
     * @return Keyring
     */
    public function getKeyring(): Keyring
    {
        return $this->keyring;
    }

    /**
     * @return Bank
     */
    public function getBank(): Bank
    {
        return $this->bank;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @inheritDoc
     */
    public function setX509Generator(X509GeneratorInterface $x509Generator = null): void
    {
        $this->x509Generator = $x509Generator;
    }

    /**
     * @inheritDoc
     */
    public function setHttpClient(HttpClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get user signature.
     *
     * @param string $type One of allowed user signature type.
     * @param bool $createNew Flag to generate new signature force.
     *
     * @return SignatureInterface
     * @throws EbicsException
     */
    private function getUserSignature(string $type, bool $createNew = false): SignatureInterface
    {
        switch ($type) {
            case SignatureInterface::TYPE_A:
                $signature = $this->keyring->getUserSignatureA();
                break;
            case SignatureInterface::TYPE_E:
                $signature = $this->keyring->getUserSignatureE();
                break;
            case SignatureInterface::TYPE_X:
                $signature = $this->keyring->getUserSignatureX();
                break;
            default:
                throw new LogicException(sprintf('Type "%s" not allowed', $type));
        }

        if (!$signature || $createNew) {
            $newSignature = $this->createUserSignature($type);
        }

        return $newSignature ?? $signature;
    }

    /**
     * Create new signature.
     *
     * @param string $type
     *
     * @return SignatureInterface
     * @throws EbicsException
     */
    private function createUserSignature(string $type): SignatureInterface
    {
        switch ($type) {
            case SignatureInterface::TYPE_A:
                $signature = $this->signatureFactory->createSignatureAFromKeys(
                    $this->cryptService->generateKeys($this->keyring->getPassword()),
                    $this->keyring->getPassword(),
                    $this->bank->isCertified() ? $this->x509Generator : null
                );
                break;
            case SignatureInterface::TYPE_E:
                $signature = $this->signatureFactory->createSignatureEFromKeys(
                    $this->cryptService->generateKeys($this->keyring->getPassword()),
                    $this->keyring->getPassword(),
                    $this->bank->isCertified() ? $this->x509Generator : null
                );
                break;
            case SignatureInterface::TYPE_X:
                $signature = $this->signatureFactory->createSignatureXFromKeys(
                    $this->cryptService->generateKeys($this->keyring->getPassword()),
                    $this->keyring->getPassword(),
                    $this->bank->isCertified() ? $this->x509Generator : null
                );
                break;
            default:
                throw new LogicException(sprintf('Type "%s" not allowed', $type));
        }

        return $signature;
    }

    public function getResponseHandler(): ResponseHandler
    {
        return $this->responseHandler;
    }
}
