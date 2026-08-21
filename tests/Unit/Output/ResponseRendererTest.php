<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonFormatter;
use ContaAzulCli\Output\MutableFormatterSelector;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\ToonFormatter;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function rtrim;

/**
 * Verifies that success rendering follows the shared formatter selector.
 */
final class ResponseRendererTest extends TestCase
{
  /** Commands that only pass an output stream default to TOON. */
  public function testDefaultsToToon(): void {
    $output = new BufferedOutput();

    (new ResponseRenderer($output))->render(['key' => 'value', 'num' => 42]);

    $rendered = $output->fetch();
    self::assertSame(['key' => 'value', 'num' => 42], Toon::decode(rtrim($rendered, "\n")));
    self::assertStringStartsWith('key:', $rendered);
    self::assertStringEndsWith("\n", $rendered);
  }

  /** Mutating the shared selector switches encoding without rebuilding the renderer. */
  public function testFollowsSelectorChanges(): void {
    $output   = new BufferedOutput();
    $selector = new MutableFormatterSelector(new ToonFormatter());
    $renderer = new ResponseRenderer($output, $selector);

    $renderer->render(['a' => 1]);
    self::assertSame(['a' => 1], Toon::decode(rtrim($output->fetch(), "\n")));

    $selector->select(new JsonFormatter());
    $renderer->render(['a' => 1]);
    self::assertSame("{\"a\":1}\n", $output->fetch());
  }

  /** The shell re-points renderers built during construction at run()'s output. */
  public function testRedirectSendsSubsequentRendersToTheNewOutput(): void {
    $first    = new BufferedOutput();
    $second   = new BufferedOutput();
    $renderer = new ResponseRenderer($first, new MutableFormatterSelector(new JsonFormatter()));

    $renderer->redirectTo($second);
    $renderer->render(['a' => 1]);

    self::assertSame('', $first->fetch());
    self::assertSame("{\"a\":1}\n", $second->fetch());
  }
}
