<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\KeyManagement\Analyzer\KeyAnalyzerManager;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;
use function is_string;

#[AsCommand(name: 'key:analyze', description: 'JWK quality analyzer.')]
final class KeyAnalyzerCommand extends Command
{
    public function __construct(
        private readonly KeyAnalyzerManager $analyzerManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command will analyze a JWK object and find security issues.')
            ->addArgument('jwk', InputArgument::REQUIRED, 'The JWK object');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->getFormatter()
            ->setStyle('success', new OutputFormatterStyle('white', 'green'));
        $output->getFormatter()
            ->setStyle('high', new OutputFormatterStyle('white', 'red', ['bold']));
        $output->getFormatter()
            ->setStyle('medium', new OutputFormatterStyle('yellow'));
        $output->getFormatter()
            ->setStyle('low', new OutputFormatterStyle('blue'));
        $jwk = $this->getKey($input);

        $result = $this->analyzerManager->analyze($jwk);
        if ($result->count() === 0) {
            $output->writeln('<success>All good! No issue found.</success>');
        } else {
            foreach ($result->all() as $message) {
                $output->writeln(
                    '<' . $message->getSeverity() . '>* ' . $message->getMessage() . '</' . $message->getSeverity() . '>'
                );
            }
        }

        return self::SUCCESS;
    }

    private function getKey(InputInterface $input): JWK
    {
        $jwk = $input->getArgument('jwk');
        if (! is_string($jwk)) {
            throw new InvalidArgumentException('Invalid JWK');
        }
        $json = JsonConverter::decode($jwk);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid JWK.');
        }

        return new JWK($json);
    }
}
