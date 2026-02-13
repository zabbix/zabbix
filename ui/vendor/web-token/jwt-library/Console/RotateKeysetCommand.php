<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function count;
use function is_array;
use function is_string;

#[AsCommand(name: 'keyset:rotate', description: 'Rotate a key set.')]
final class RotateKeysetCommand extends ObjectOutputCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command removes the last key in a key set a place a new one at the beginning.')
            ->addArgument('jwkset', InputArgument::REQUIRED, 'The JWKSet object')
            ->addArgument('jwk', InputArgument::REQUIRED, 'The new JWK object');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jwkset = $this->getKeyset($input)
            ->all();
        $jwk = $this->getKey($input);

        if (count($jwkset) !== 0) {
            array_pop($jwkset);
        }
        array_unshift($jwkset, $jwk);

        $this->prepareJsonOutput($input, $output, new JWKSet($jwkset));

        return self::SUCCESS;
    }

    private function getKeyset(InputInterface $input): JWKSet
    {
        $jwkset = $input->getArgument('jwkset');
        if (! is_string($jwkset)) {
            throw new InvalidArgumentException('Invalid JWKSet');
        }
        $json = JsonConverter::decode($jwkset);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid JWKSet');
        }

        return JWKSet::createFromKeyData($json);
    }

    private function getKey(InputInterface $input): JWK
    {
        $jwk = $input->getArgument('jwk');
        if (! is_string($jwk)) {
            throw new InvalidArgumentException('Invalid JWK');
        }
        $json = JsonConverter::decode($jwk);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid JWK');
        }

        return new JWK($json);
    }
}
