<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\JsonConverter;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;
use function is_string;

#[AsCommand(name: 'key:thumbprint', description: 'Get the thumbprint of a JWK key.',)]
final class GetThumbprintCommand extends ObjectOutputCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('jwk', InputArgument::REQUIRED, 'The JWK key.')
            ->addOption('hash', null, InputOption::VALUE_OPTIONAL, 'The hashing algorithm.', 'sha256');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jwk = $input->getArgument('jwk');
        if (! is_string($jwk)) {
            throw new InvalidArgumentException('Invalid JWK');
        }
        $hash = $input->getOption('hash');
        if (! is_string($hash)) {
            throw new InvalidArgumentException('Invalid hash algorithm');
        }
        $json = JsonConverter::decode($jwk);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid input.');
        }
        $key = new JWK($json);
        $output->write($key->thumbprint($hash));

        return self::SUCCESS;
    }
}
