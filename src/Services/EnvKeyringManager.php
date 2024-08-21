<?php

namespace AndrewSvirin\Ebics\Services;

use AndrewSvirin\Ebics\Models\Keyring;
use LogicException;

/**
 * EBICS Keyring representation manage one key ring stored in an environment.
 */
final class EnvKeyringManager extends KeyringManager
{
    /**
     * @inheritDoc
     */
    public function loadKeyring($resource, string $passphrase, string $defaultVersion = Keyring::VERSION_25): Keyring
    {
        $result = is_string($resource) ? json_decode(getenv($resource, true)) : $_ENV;

        $result = $this->keyringFactory->createKeyringFromData($result);
        $result->setPassword($passphrase);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function saveKeyring(Keyring $keyring, &$resource): void
    {
        if (is_string($resource)) {
            throw new LogicException('Saving Keyring to environment is not supported.');
        }
    }
}
