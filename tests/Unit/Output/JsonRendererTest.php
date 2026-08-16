<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class JsonRendererTest extends TestCase
{
    private JsonRenderer $renderer;
    private BufferedOutput $output;


    protected function setUp(): void {
        $this->output   = new BufferedOutput();
        $this->renderer = new JsonRenderer($this->output);
    }


    public function testOutputsCompactJson(): void {
        $this->renderer->render(['key' => 'value', 'num' => 42]);
        $out = $this->output->fetch();

        self::assertSame('{"key":"value","num":42}' . "\n", $out);
    }


    public function testOutputEndsWithNewline(): void {
        $this->renderer->render(['x' => 1]);
        $out = $this->output->fetch();

        self::assertStringEndsWith("\n", (string) $out);
    }


    public function testUnicodeIsNotEscaped(): void {
        $this->renderer->render(['msg' => 'Olá, mundo!']);
        $out = $this->output->fetch();

        self::assertStringContainsString('Olá, mundo!', (string) $out);
    }


    public function testForwardSlashesAreNotEscaped(): void {
        $this->renderer->render(['url' => 'https://example.com/path']);
        $out = $this->output->fetch();

        self::assertStringContainsString('https://example.com/path', (string) $out);
        self::assertStringNotContainsString('https:\/\/', (string) $out);
    }


    public function testOutputHasNoExtraWhitespace(): void {
        $this->renderer->render(['a' => 1, 'b' => 2]);
        $out = $this->output->fetch();

        // Compact JSON has no spaces around colons or commas
        self::assertStringNotContainsString(': ', (string) $out);
        self::assertStringNotContainsString(', ', (string) $out);
    }


}
