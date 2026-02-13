<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function is_bool;

abstract class GeneratorCommand extends ObjectOutputCommand
{
    #[Override]
    public function isEnabled(): bool
    {
        return class_exists(JWKFactory::class);
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('use', 'u', InputOption::VALUE_OPTIONAL, 'Usage of the key. Must be either "sig" or "enc".')
            ->addOption('alg', 'a', InputOption::VALUE_OPTIONAL, 'Algorithm for the key.')
            ->addOption(
                'random_id',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, a random key ID (kid) will be generated.'
            );
    }

    protected function getOptions(InputInterface $input): array
    {
        $args = [];
        $useRandomId = $input->getOption('random_id');
        if (! is_bool($useRandomId)) {
            throw new InvalidArgumentException('Invalid value for option "random_id"');
        }
        if ($useRandomId) {
            $args['kid'] = $this->generateKeyID();
        }
        foreach (['use', 'alg'] as $key) {
            $value = $input->getOption($key);
            if ($value !== null) {
                $args[$key] = $value;
            }
        }

        return $args;
    }

    private function generateKeyID(): string
    {
        return Base64UrlSafe::encode(random_bytes(32));
    }
}
