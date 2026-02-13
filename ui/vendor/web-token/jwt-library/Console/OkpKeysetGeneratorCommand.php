<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_string;

#[AsCommand(
    name: 'keyset:generate:okp',
    description: 'Generate a key set with Octet Key Pairs keys (JWKSet format)',
)]
final class OkpKeysetGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('quantity', InputArgument::REQUIRED, 'Quantity of keys in the key set.')
            ->addArgument('curve', InputArgument::REQUIRED, 'Curve of the keys.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quantity = (int) $input->getArgument('quantity');
        $curve = $input->getArgument('curve');
        if ($quantity < 1) {
            throw new InvalidArgumentException('Invalid quantity');
        }
        if (! is_string($curve)) {
            throw new InvalidArgumentException('Invalid curve');
        }

        $keyset = new JWKSet([]);
        for ($i = 0; $i < $quantity; ++$i) {
            $args = $this->getOptions($input);
            $keyset = $keyset->with(JWKFactory::createOKPKey($curve, $args));
        }
        $this->prepareJsonOutput($input, $output, $keyset);

        return self::SUCCESS;
    }
}
