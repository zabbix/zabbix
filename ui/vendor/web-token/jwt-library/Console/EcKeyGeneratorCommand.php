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
use function is_string;

#[AsCommand(name: 'key:generate:ec', description: 'Generate an EC key (JWK format)',)]
final class EcKeyGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('curve', InputArgument::REQUIRED, 'Curve of the key.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $curve = $input->getArgument('curve');
        if (! is_string($curve)) {
            throw new InvalidArgumentException('Invalid curve');
        }
        $args = $this->getOptions($input);

        $jwk = JWKFactory::createECKey($curve, $args);
        $this->prepareJsonOutput($input, $output, $jwk);

        return self::SUCCESS;
    }
}
