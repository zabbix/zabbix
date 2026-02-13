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

#[AsCommand(name: 'keyset:generate:oct', description: 'Generate a key set with octet keys (JWK format)',)]
final class OctKeysetGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('quantity', InputArgument::REQUIRED, 'Quantity of keys in the key set.')
            ->addArgument('size', InputArgument::REQUIRED, 'Key size.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quantity = (int) $input->getArgument('quantity');
        $size = (int) $input->getArgument('size');
        if ($quantity < 1) {
            throw new InvalidArgumentException('Invalid quantity');
        }
        if ($size < 1) {
            throw new InvalidArgumentException('Invalid size');
        }

        $keyset = new JWKSet([]);
        for ($i = 0; $i < $quantity; ++$i) {
            $args = $this->getOptions($input);
            $keyset = $keyset->with(JWKFactory::createOctKey($size, $args));
        }
        $this->prepareJsonOutput($input, $output, $keyset);

        return self::SUCCESS;
    }
}
