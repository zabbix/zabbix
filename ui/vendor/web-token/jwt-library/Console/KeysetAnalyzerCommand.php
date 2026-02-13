<?php

declare(strict_types=1);

namespace Jose\Component\Console;

use InvalidArgumentException;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\KeyManagement\Analyzer\KeyAnalyzerManager;
use Jose\Component\KeyManagement\Analyzer\KeysetAnalyzerManager;
use Jose\Component\KeyManagement\Analyzer\MessageBag;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_array;
use function is_string;
use function sprintf;

#[AsCommand(name: 'keyset:analyze', description: 'JWKSet quality analyzer.')]
final class KeysetAnalyzerCommand extends Command
{
    public function __construct(
        private readonly KeysetAnalyzerManager $keysetAnalyzerManager,
        private readonly KeyAnalyzerManager $keyAnalyzerManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command will analyze a JWKSet object and find security issues.')
            ->addArgument('jwkset', InputArgument::REQUIRED, 'The JWKSet object');
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

        $jwkset = $this->getKeyset($input);

        $messages = $this->keysetAnalyzerManager->analyze($jwkset);
        $this->showMessages($messages, $output);
        foreach ($jwkset as $kid => $jwk) {
            $output->writeln(sprintf('Analysing key with index/kid "%s"', $kid));
            $messages = $this->keyAnalyzerManager->analyze($jwk);
            $this->showMessages($messages, $output);
        }

        return self::SUCCESS;
    }

    private function showMessages(MessageBag $messages, OutputInterface $output): void
    {
        if ($messages->count() === 0) {
            $output->writeln('    <success>All good! No issue found.</success>');
        } else {
            foreach ($messages->all() as $message) {
                $output->writeln(
                    '    <' . $message->getSeverity() . '>* ' . $message->getMessage() . '</' . $message->getSeverity() . '>'
                );
            }
        }
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
}
