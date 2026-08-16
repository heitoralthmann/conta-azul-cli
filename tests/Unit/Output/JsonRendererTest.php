<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonRenderer;
use PHPUnit\Framework\TestCase;

final class JsonRendererTest extends TestCase
{
    private JsonRenderer $renderer;


    protected function setUp(): void {
        $this->renderer = new JsonRenderer();
    }


    public function testOutputsCompactJson(): void {
        ob_start();
        $this->renderer->render(['key' => 'value', 'num' => 42]);
        $out = ob_get_clean();

        self::assertSame('{"key":"value","num":42}' . "\n", $out);
    }


    public function testOutputEndsWithNewline(): void {
        ob_start();
        $this->renderer->render(['x' => 1]);
        $out = ob_get_clean();

        self::assertStringEndsWith("\n", (string) $out);
    }


    public function testUnicodeIsNotEscaped(): void {
        ob_start();
        $this->renderer->render(['msg' => 'Olá, mundo!']);
        $out = ob_get_clean();

        self::assertStringContainsString('Olá, mundo!', (string) $out);
    }


    public function testForwardSlashesAreNotEscaped(): void {
        ob_start();
        $this->renderer->render(['url' => 'https://example.com/path']);
        $out = ob_get_clean();

        self::assertStringContainsString('https://example.com/path', (string) $out);
        self::assertStringNotContainsString('https:\/\/', (string) $out);
    }


    public function testOutputHasNoExtraWhitespace(): void {
        ob_start();
        $this->renderer->render(['a' => 1, 'b' => 2]);
        $out = ob_get_clean();

        // Compact JSON has no spaces around colons or commas
        self::assertStringNotContainsString(': ', (string) $out);
        self::assertStringNotContainsString(', ', (string) $out);
    }


}
