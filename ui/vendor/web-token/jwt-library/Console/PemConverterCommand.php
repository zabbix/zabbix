<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Core\Util\RSAKey;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;
use function is_string;

#[AsCommand(name: 'key:convert:pkcs1', description: 'Converts a RSA or EC key into PKCS#1 key.',)]
final class PemConverterCommand extends ObjectOutputCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('jwk', InputArgument::REQUIRED, 'The key');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jwk = $input->getArgument('jwk');
        if (! is_string($jwk)) {
            throw new InvalidArgumentException('Invalid JWK');
        }
        $json = JsonConverter::decode($jwk);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid JWK.');
        }
        $key = new JWK($json);

        $pem = match ($key->get('kty')) {
            'RSA' => RSAKey::createFromJWK($key)->toPEM(),
            'EC' => ECKey::convertToPEM($key),
            default => throw new InvalidArgumentException('Not a RSA or EC key.'),
        };
        $output->write($pem);

        return self::SUCCESS;
    }
}
