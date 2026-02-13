<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'key:generate:rsa', description: 'Generate a RSA key (JWK format)',)]
final class RsaKeyGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('size', InputArgument::REQUIRED, 'Key size.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $size = (int) $input->getArgument('size');
        $args = $this->getOptions($input);
        if ($size < 1) {
            throw new InvalidArgumentException('Invalid size');
        }

        $jwk = JWKFactory::createRSAKey($size, $args);
        $this->prepareJsonOutput($input, $output, $jwk);

        return self::SUCCESS;
    }
}
