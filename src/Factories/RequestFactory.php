<?php

namespace AndrewSvirin\Ebics\Factories;

use AndrewSvirin\Ebics\Builders\Request\BodyBuilder;
use AndrewSvirin\Ebics\Builders\Request\DataEncryptionInfoBuilder;
use AndrewSvirin\Ebics\Builders\Request\DataTransferBuilder;
use AndrewSvirin\Ebics\Builders\Request\HeaderBuilder;
use AndrewSvirin\Ebics\Builders\Request\MutableBuilder;
use AndrewSvirin\Ebics\Builders\Request\OrderDetailsBuilder;
use AndrewSvirin\Ebics\Builders\Request\RequestBuilder;
use AndrewSvirin\Ebics\Builders\Request\StaticBuilder;
use AndrewSvirin\Ebics\Builders\Request\TransferReceiptBuilder;
use AndrewSvirin\Ebics\Builders\Request\XmlBuilder;
use AndrewSvirin\Ebics\Contexts\BTDContext;
use AndrewSvirin\Ebics\Contexts\BTUContext;
use AndrewSvirin\Ebics\Contexts\FULContext;
use AndrewSvirin\Ebics\Contexts\HVDContext;
use AndrewSvirin\Ebics\Contexts\HVEContext;
use AndrewSvirin\Ebics\Contexts\HVTContext;
use AndrewSvirin\Ebics\Contexts\RequestContext;
use AndrewSvirin\Ebics\Contracts\SignatureInterface;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Handlers\AuthSignatureHandler;
use AndrewSvirin\Ebics\Handlers\OrderDataHandler;
use AndrewSvirin\Ebics\Handlers\UserSignatureHandler;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\CustomerH3K;
use AndrewSvirin\Ebics\Models\CustomerHIA;
use AndrewSvirin\Ebics\Models\CustomerINI;
use AndrewSvirin\Ebics\Models\Http\Request;
use AndrewSvirin\Ebics\Models\Keyring;
use AndrewSvirin\Ebics\Models\UploadTransaction;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Models\UserSignature;
use AndrewSvirin\Ebics\Services\CryptService;
use AndrewSvirin\Ebics\Services\DigestResolver;
use DateTimeInterface;

/**
 * Class RequestFactory represents producers for the @see Request.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author Andrew Svirin
 */
abstract class RequestFactory
{
    protected RequestBuilder $requestBuilder;
    protected OrderDataHandler $orderDataHandler;
    protected DigestResolver $digestResolver;
    protected AuthSignatureHandler $authSignatureHandler;
    protected UserSignatureHandler $userSignatureHandler;
    protected CryptService $cryptService;
    protected Bank $bank;
    protected User $user;
    protected Keyring $keyring;

    /**
     * Constructor.
     *
     * @param Bank $bank
     * @param User $user
     * @param Keyring $keyring
     */
    public function __construct(Bank $bank, User $user, Keyring $keyring)
    {
        $this->requestBuilder = new RequestBuilder();
        $this->cryptService = new CryptService();
        $this->bank = $bank;
        $this->user = $user;
        $this->keyring = $keyring;
    }

    abstract protected function createRequestBuilderInstance(): RequestBuilder;

    abstract protected function addOrderType(
        OrderDetailsBuilder $orderDetailsBuilder,
        string $orderType
    ): OrderDetailsBuilder;

    public function createHEV(): Request
    {
        $context = (new RequestContext())
            ->setBank($this->bank);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerHEV(function (XmlBuilder $builder) use ($context) {
                $builder->addHostId($context->getBank()->getHostId());
            })
            ->popInstance();

        return $request;
    }

    public function createINI(SignatureInterface $certificateA, DateTimeInterface $dateTime): Request
    {
        $orderData = new CustomerINI();
        $this->orderDataHandler->handleINI(
            $orderData,
            $certificateA,
            $dateTime
        );

        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setDateTime($dateTime)
            ->setOrderData($orderData->getContent());

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerUnsecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this->addOrderType($orderDetailsBuilder, 'INI');
                            })
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable();
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder->addOrderData($context->getOrderData());
                    });
                });
            })
            ->popInstance();

        return $request;
    }

    public function createHIA(
        SignatureInterface $certificateE,
        SignatureInterface $certificateX,
        DateTimeInterface $dateTime
    ): Request {
        $orderData = new CustomerHIA();
        $this->orderDataHandler->handleHIA(
            $orderData,
            $certificateE,
            $certificateX,
            $dateTime
        );

        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setDateTime($dateTime)
            ->setOrderData($orderData->getContent());

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerUnsecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this->addOrderType($orderDetailsBuilder, 'HIA');
                            })
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable();
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder->addOrderData($context->getOrderData());
                    });
                });
            })
            ->popInstance();

        return $request;
    }

    public function createH3K(
        SignatureInterface $certificateA,
        SignatureInterface $certificateE,
        SignatureInterface $certificateX,
        DateTimeInterface $dateTime
    ): Request {
        $orderData = new CustomerH3K();
        $this->orderDataHandler->handleH3K(
            $orderData,
            $certificateA,
            $certificateE,
            $certificateX
        );

        $signatureData = new UserSignature();
        $this->userSignatureHandler->handle(
            $signatureData,
            $this->cryptService->hash($orderData->getContent())
        );

        $context = (new RequestContext())
            ->setKeyring($this->keyring)
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setDateTime($dateTime)
            ->setOrderData($orderData->getContent())
            ->setSignatureData($signatureData);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerUnsigned(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this->addOrderType($orderDetailsBuilder, 'H3K');
                            })
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable();
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder->addSignatureData($context->getSignatureData(), '');
                        $builder->addOrderData($context->getOrderData());
                    });
                });
            })
            ->popInstance();

        return $request;
    }

    /**
     * @param DateTimeInterface $dateTime
     *
     * @return Request
     * @throws EbicsException
     */
    public function createHPB(DateTimeInterface $dateTime): Request
    {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setDateTime($dateTime);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecuredNoPubKeyDigests(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this->addOrderType($orderDetailsBuilder, 'HPB');
                            })
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable();
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHPD(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HPD')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHKD(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HKD')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createPTK(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'PTK')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHTD(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HTD')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createFDL(
        DateTimeInterface $dateTime,
        string $fileFormat,
        string $countryCode,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setFileFormat($fileFormat)
            ->setCountryCode($countryCode)
            ->setStartDateTime($startDateTime)
            ->setEndDateTime($endDateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'FDL')
                                    ->addFDLOrderParams(
                                        $context->getFileFormat(),
                                        $context->getCountryCode(),
                                        $context->getStartDateTime(),
                                        $context->getEndDateTime()
                                    );
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createFUL(
        DateTimeInterface $dateTime,
        string $fileFormat,
        FULContext $fulContext,
        UploadTransaction $transaction
    ): Request {
        $signatureData = new UserSignature();
        $this->userSignatureHandler->handle($signatureData, $transaction->getDigest());

        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setFileFormat($fileFormat)
            ->setFULContext($fulContext)
            ->setTransactionKey($transaction->getKey())
            ->setNumSegments($transaction->getNumSegments())
            ->setSignatureData($signatureData);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'FUL')
                                    ->addFULOrderParams(
                                        $context->getFileFormat(),
                                        $context->getFULContext()
                                    );
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000)
                            ->addNumSegments($context->getNumSegments());
                    })->addMutable(function (MutableBuilder $builder) {
                        $builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
                    });
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder
                            ->addDataEncryptionInfo(function (DataEncryptionInfoBuilder $builder) use ($context) {
                                $builder
                                    ->addEncryptionPubKeyDigest($context->getKeyring())
                                    ->addTransactionKey($context->getTransactionKey(), $context->getKeyring());
                            })
                            ->addSignatureData($context->getSignatureData(), $context->getTransactionKey());
                    });
                });
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHAA(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HAA')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    abstract public function createBTD(
        DateTimeInterface $dateTime,
        BTDContext $btdContext,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createBTU(
        BTUContext $btuContext,
        DateTimeInterface $dateTime,
        UploadTransaction $transaction
    ): Request;

    /**
     * @throws EbicsException
     */
    public function createTransferReceipt(string $transactionId, bool $acknowledged): Request
    {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setTransactionId($transactionId)
            ->setReceiptCode(true === $acknowledged ?
                TransferReceiptBuilder::CODE_RECEIPT_POSITIVE : TransferReceiptBuilder::CODE_RECEIPT_NEGATIVE);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addTransactionId($context->getTransactionId());
                    })->addMutable(function (MutableBuilder $builder) {
                        $builder->addTransactionPhase(MutableBuilder::PHASE_RECEIPT);
                    });
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addTransferReceipt(function (TransferReceiptBuilder $builder) use ($context) {
                        $builder->addReceiptCode($context->getReceiptCode());
                    });
                });
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createTransferTransfer(
        string $transactionId,
        string $transactionKey,
        string $orderData,
        int $segmentNumber,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setTransactionId($transactionId)
            ->setTransactionKey($transactionKey)
            ->setOrderData($orderData)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addTransactionId($context->getTransactionId());
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_TRANSFER)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder->addOrderData($context->getOrderData(), $context->getTransactionKey());
                    });
                });
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    abstract public function createVMK(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createSTA(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createC52(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createC53(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createC54(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createZ52(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createZ53(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createZ54(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createZSR(
        DateTimeInterface $dateTime,
        DateTimeInterface $startDateTime = null,
        DateTimeInterface $endDateTime = null,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request;

    abstract public function createCCT(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    abstract public function createCDD(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    abstract public function createCDB(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    abstract public function createXE2(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    abstract public function createXE3(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    abstract public function createYCT(DateTimeInterface $dateTime, UploadTransaction $transaction): Request;

    /**
     * @throws EbicsException
     */
    public function createCIP(DateTimeInterface $dateTime, UploadTransaction $transaction): Request
    {
        $signatureData = new UserSignature();
        $this->userSignatureHandler->handle($signatureData, $transaction->getDigest());

        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setTransactionKey($transaction->getKey())
            ->setNumSegments($transaction->getNumSegments())
            ->setSignatureData($signatureData);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'CIP')
                                    ->addStandardOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000)
                            ->addNumSegments($context->getNumSegments());
                    })->addMutable(function (MutableBuilder $builder) {
                        $builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
                    });
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder
                            ->addDataEncryptionInfo(function (DataEncryptionInfoBuilder $builder) use ($context) {
                                $builder
                                    ->addEncryptionPubKeyDigest($context->getKeyring())
                                    ->addTransactionKey($context->getTransactionKey(), $context->getKeyring());
                            })
                            ->addSignatureData($context->getSignatureData(), $context->getTransactionKey());
                    });
                });
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHVU(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HVU')
                                    ->addHVUOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0200);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHVZ(
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HVZ')
                                    ->addHVZOrderParams();
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0200);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    /**
     * @throws EbicsException
     */
    public function createHVE(
        HVEContext $hveContext,
        DateTimeInterface $dateTime,
        UploadTransaction $transaction
    ): Request {
        $signatureData = new UserSignature();
        $this->userSignatureHandler->handle($signatureData, $transaction->getDigest());

        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setTransactionKey($transaction->getKey())
            ->setNumSegments($transaction->getNumSegments())
            ->setSignatureData($signatureData)
            ->setHVEContext($hveContext);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HVE')
                                    ->addHVEOrderParams($context->getHVEContext());
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0000)
                            ->addNumSegments($context->getNumSegments());
                    })->addMutable(function (MutableBuilder $builder) {
                        $builder->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION);
                    });
                })->addBody(function (BodyBuilder $builder) use ($context) {
                    $builder->addDataTransfer(function (DataTransferBuilder $builder) use ($context) {
                        $builder
                            ->addDataEncryptionInfo(function (DataEncryptionInfoBuilder $builder) use ($context) {
                                $builder
                                    ->addEncryptionPubKeyDigest($context->getKeyring())
                                    ->addTransactionKey($context->getTransactionKey(), $context->getKeyring());
                            })
                            ->addSignatureData($context->getSignatureData(), $context->getTransactionKey())
                            ->addDataDigest($context->getKeyring()->getUserSignatureAVersion());
                    });
                });
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    public function createHVD(
        HVDContext $hvdContext,
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment)
            ->setHVDContext($hvdContext);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HVD')
                                    ->addHVDOrderParams($context->getHVDContext());
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0200);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }

    public function createHVT(
        HVTContext $hvtContext,
        DateTimeInterface $dateTime,
        int $segmentNumber = null,
        bool $isLastSegment = null
    ): Request {
        $context = (new RequestContext())
            ->setBank($this->bank)
            ->setUser($this->user)
            ->setKeyring($this->keyring)
            ->setDateTime($dateTime)
            ->setSegmentNumber($segmentNumber)
            ->setIsLastSegment($isLastSegment)
            ->setHVTContext($hvtContext);

        $request = $this
            ->createRequestBuilderInstance()
            ->addContainerSecured(function (XmlBuilder $builder) use ($context) {
                $builder->addHeader(function (HeaderBuilder $builder) use ($context) {
                    $builder->addStatic(function (StaticBuilder $builder) use ($context) {
                        $builder
                            ->addHostId($context->getBank()->getHostId())
                            ->addRandomNonce()
                            ->addTimestamp($context->getDateTime())
                            ->addPartnerId($context->getUser()->getPartnerId())
                            ->addUserId($context->getUser()->getUserId())
                            ->addProduct('Ebics client PHP', 'de')
                            ->addOrderDetails(function (OrderDetailsBuilder $orderDetailsBuilder) use ($context) {
                                $this
                                    ->addOrderType($orderDetailsBuilder, 'HVT')
                                    ->addHVTOrderParams($context->getHVTContext());
                            })
                            ->addBankPubKeyDigests(
                                $context->getKeyring()->getBankSignatureXVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureX()),
                                $context->getKeyring()->getBankSignatureEVersion(),
                                $this->digestResolver->digest($context->getKeyring()->getBankSignatureE())
                            )
                            ->addSecurityMedium(StaticBuilder::SECURITY_MEDIUM_0200);
                    })->addMutable(function (MutableBuilder $builder) use ($context) {
                        $builder
                            ->addTransactionPhase(MutableBuilder::PHASE_INITIALIZATION)
                            ->addSegmentNumber($context->getSegmentNumber(), $context->getIsLastSegment());
                    });
                })->addBody();
            })
            ->popInstance();

        $this->authSignatureHandler->handle($request);

        return $request;
    }
}
