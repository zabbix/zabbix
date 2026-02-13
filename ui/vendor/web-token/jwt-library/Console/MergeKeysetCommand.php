<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;

#[AsCommand(name: 'keyset:merge', description: 'Merge several key sets into one.')]
final class MergeKeysetCommand extends ObjectOutputCommand
{
    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp(
                'This command merges several key sets into one. It is very useful when you generate e.g. RSA, EC and OKP keys and you want only one key set to rule them all.'
            )
            ->addArgument('jwksets', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The JWKSet objects');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $keySets */
        $keySets = $input->getArgument('jwksets');
        $newJwkset = new JWKSet([]);
        foreach ($keySets as $keySet) {
            $json = JsonConverter::decode($keySet);
            if (! is_array($json)) {
                throw new InvalidArgumentException('The argument must be a valid JWKSet.');
            }
            $jwkset = JWKSet::createFromKeyData($json);
            foreach ($jwkset->all() as $jwk) {
                $newJwkset = $newJwkset->with($jwk);
            }
        }
        $this->prepareJsonOutput($input, $output, $newJwkset);

        return self::SUCCESS;
    }
}
