<?php

use AndrewSvirin\Ebics\Builders\CustomerCreditTransfer\CustomerSwissCreditTransferBuilder;
use AndrewSvirin\Ebics\Builders\CustomerDirectDebit\CustomerDirectDebitBuilder;
use AndrewSvirin\Ebics\Contracts\EbicsClientInterface;
use AndrewSvirin\Ebics\Contracts\X509GeneratorInterface;
use AndrewSvirin\Ebics\EbicsClient;
use AndrewSvirin\Ebics\Factories\SignatureFactory;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\CustomerCreditTransfer;
use AndrewSvirin\Ebics\Models\CustomerDirectDebit;
use AndrewSvirin\Ebics\Models\Keyring;
use AndrewSvirin\Ebics\Models\StructuredPostalAddress;
use AndrewSvirin\Ebics\Models\UnstructuredPostalAddress;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Services\FileKeyringManager;
use PHPUnit\Framework\TestCase;
//use RuntimeException;

abstract class AbstractEblicsClient
{
    protected $data = __DIR__.'/_data';
    
    protected function setupClientV3(
        int $credentialsId,
        X509GeneratorInterface $x509Generator = null,
        bool $fake = false
    ): EbicsClientInterface {
        return $this->setupClient(Keyring::VERSION_30, $credentialsId, $x509Generator, $fake);
    }

    private function setupClient(
        string $version,
        int $credentialsId,
        X509GeneratorInterface $x509Generator = null,
        bool $fake = false
    ): EbicsClientInterface {
        $credentials = $this->credentialsDataProvider($credentialsId);

        $bank = new Bank($credentials['hostId'], $credentials['hostURL']);
        $bank->setUsesUploadWithES(true);
        $bank->setIsCertified($credentials['hostIsCertified']);
        $bank->setServerName(sprintf('Server %d', $credentialsId));
        $user = new User($credentials['partnerId'], $credentials['userId']);
        $keyring = $this->loadKeyring($credentialsId, $version);

        $ebicsClient = new EbicsClient($bank, $user, $keyring);

        $ebicsClient->setX509Generator($x509Generator);

        if (true === $fake) {
            $ebicsClient->setHttpClient(new FakerHttpClient($this->fixtures));
        }

        return $ebicsClient;
    }

      /**
     * Client credentials data provider.
     *
     * @param int $credentialsId
     *
     * @return array
     */
    public function credentialsDataProvider(int $credentialsId): array
    {
        $path = sprintf('%s/credentials/credentials_%d.json', $this->data, $credentialsId);

        if (!file_exists($path)) {
            throw new RuntimeException('Credentials missing');
        }

        $credentialsEnc = json_decode(file_get_contents($path), true);

        return [
            'hostId' => $credentialsEnc['hostId'],
            'hostURL' => $credentialsEnc['hostURL'],
            'hostIsCertified' => (bool)$credentialsEnc['hostIsCertified'],
            'partnerId' => $credentialsEnc['partnerId'],
            'userId' => $credentialsEnc['userId'],
        ];
    }

    protected function loadKeyring(string $credentialsId, string $version): Keyring
    {
        $keyringRealPath = sprintf('%s/workspace/keyring_%d.json', $this->data, $credentialsId);
        $password = 'test123';
        $keyringManager = new FileKeyringManager();

        return $keyringManager->loadKeyring($keyringRealPath, $password, $version);
    }

    protected function saveKeyring(string $credentialsId, Keyring $keyring): void
    {
        $keyringRealPath = sprintf('%s/workspace/keyring_%d.json', $this->data, $credentialsId);
        $keyringManager = new FileKeyringManager();
        $keyringManager->saveKeyring($keyring, $keyringRealPath);
    }


    //TODO: adapter pour tous les fichiers pain.001
    protected function buildCustomerCreditTransfer(string $schema): CustomerCreditTransfer
    {
        $builder = new CustomerSwissCreditTransferBuilder();
        $customerCreditTransfer = $builder
            ->createInstance(
                $schema,
                'ZKBKCHZZ80A',
                'SE7500800000000000001123',
                'Debitor Name'
            )
            ->addBankTransaction(
                'MARKDEF1820',
                'DE09820000000083001503',
                new StructuredPostalAddress('CH', 'Triesen', '9495'),
                100.10,
                'CHF',
                'Test payment  1'
            )
            ->addSEPATransaction(
                'GIBASKBX',
                'SK4209000000000331819272',
                'Creditor Name 4',
                null, // new UnstructuredPostalAddress(),
                200.02,
                'EUR',
                'Test payment  2'
            )
            ->addForeignTransaction(
                'NWBKGB2L',
                'GB29 NWBK 6016 1331 9268 19',
                'United Development Ltd',
                new UnstructuredPostalAddress('GB', 'George Street', 'BA1 2FJ Bath'),
                65.10,
                'GBP',
                'Test payment 3'
            )
            ->popInstance();

        
        return $customerCreditTransfer;
    }

}

?>