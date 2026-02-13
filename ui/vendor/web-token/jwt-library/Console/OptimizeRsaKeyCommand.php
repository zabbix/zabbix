<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\KeyManagement\KeyConverter\RSAKey;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;
use function is_string;

#[AsCommand(name: 'key:optimize', description: 'Optimize a RSA key by calculating additional primes (CRT).',)]
final class OptimizeRsaKeyCommand extends ObjectOutputCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('jwk', InputArgument::REQUIRED, 'The RSA key.');
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
            throw new InvalidArgumentException('Invalid JWK');
        }
        $key = RSAKey::createFromJWK(new JWK($json));
        $key->optimize();
        $this->prepareJsonOutput($input, $output, $key->toJwk());

        return self::SUCCESS;
    }
}
