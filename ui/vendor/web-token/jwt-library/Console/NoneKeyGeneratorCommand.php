<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'key:generate:none',
    description: 'Generate a none key (JWK format). This key type is only supposed to be used with the "none" algorithm.',
)]
final class NoneKeyGeneratorCommand extends GeneratorCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $this->getOptions($input);

        $jwk = JWKFactory::createNoneKey($args);
        $this->prepareJsonOutput($input, $output, $jwk);

        return self::SUCCESS;
    }
}
