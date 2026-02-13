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
use function is_string;

#[AsCommand(name: 'key:load:key', description: 'Loads a key from a key file (JWK format)',)]
final class KeyFileLoaderCommand extends GeneratorCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('file', InputArgument::REQUIRED, 'Filename of the key.')
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'Secret if the key is encrypted.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $password = $input->getOption('secret');
        if (! is_string($file)) {
            throw new InvalidArgumentException('Invalid file');
        }
        if ($password !== null && ! is_string($password)) {
            throw new InvalidArgumentException('Invalid secret');
        }
        $args = $this->getOptions($input);

        $jwk = JWKFactory::createFromKeyFile($file, $password, $args);
        $this->prepareJsonOutput($input, $output, $jwk);

        return self::SUCCESS;
    }
}
