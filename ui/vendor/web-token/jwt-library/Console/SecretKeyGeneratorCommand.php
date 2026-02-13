<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function is_bool;
use function is_string;

#[AsCommand(
    name: 'key:generate:from_secret',
    description: 'Generate an octet key (JWK format) using an existing secret',
)]
final class SecretKeyGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('secret', InputArgument::REQUIRED, 'The secret')
            ->addOption(
                'is_b64',
                'b',
                InputOption::VALUE_NONE,
                'Indicates if the secret is Base64 encoded (useful for binary secrets)'
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $secret = $input->getArgument('secret');
        if (! is_string($secret)) {
            throw new InvalidArgumentException('Invalid secret');
        }
        $isBsae64Encoded = $input->getOption('is_b64');
        if (! is_bool($isBsae64Encoded)) {
            throw new InvalidArgumentException('Invalid option value for "is_b64"');
        }
        if ($isBsae64Encoded) {
            $secret = base64_decode($secret, true);
        }
        if (! is_string($secret)) {
            throw new InvalidArgumentException('Invalid secret');
        }
        $args = $this->getOptions($input);

        $jwk = JWKFactory::createFromSecret($secret, $args);
        $this->prepareJsonOutput($input, $output, $jwk);

        return self::SUCCESS;
    }
}
