<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Tests\Support\PosixPermissions;
use PHPUnit\Framework\TestCase;

use function array_reverse;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class EnvFileWriterTest extends TestCase
{
  use PosixPermissions;

  private EnvFileWriter $writer;
  private string $directory = '';

  /** @var list<string> */
  private array $files = [];

  /** @var list<string> */
  private array $directories = [];

  protected function setUp(): void {
    $this->writer    = new EnvFileWriter();
    $this->directory = $this->trackDirectory(sys_get_temp_dir() . '/ca-env-writer-' . uniqid());
    mkdir($this->directory, 0700, true);
  }

  protected function tearDown(): void {
    foreach ($this->files as $file) {
      unlink($file);
    }

    foreach (array_reverse($this->directories) as $directory) {
      if (! is_dir($directory)) {
        continue;
      }

      rmdir($directory);
    }
  }

  /** The file carries credentials, so it must never be group- or world-readable. */
  public function testCreateWritesAPrivateFileInsideAPrivateDirectory(): void {
    $path = $this->trackFile($this->trackDirectory($this->directory . '/nested') . '/.env');

    $this->writer->create($path, "CA_CLIENT_ID=abc\n");

    // Creating the missing directory and writing the body are asserted on
    // every platform; only the mode itself is POSIX-only.
    self::assertFileExists($path);
    self::assertSame("CA_CLIENT_ID=abc\n", file_get_contents($path));
    self::assertPermissions('600', $path);
    self::assertPermissions('700', $this->directory . '/nested');
  }

  /** An upsert has to leave the hand-written guidance around a variable intact. */
  public function testUpsertReplacesInPlaceAndPreservesCommentsAndOrder(): void {
    $path = $this->writeFile(
        <<<'ENV'
        # Credenciais
        CA_CLIENT_ID=old-id
        CA_CLIENT_SECRET=old-secret

        # Endpoints
        # CA_API_BASE_URL=https://api-v2.contaazul.com
        ENV,
    );

    $this->writer->upsert($path, 'CA_CLIENT_ID', 'new-id');

    self::assertSame(
        <<<'ENV'
        # Credenciais
        CA_CLIENT_ID=new-id
        CA_CLIENT_SECRET=old-secret

        # Endpoints
        # CA_API_BASE_URL=https://api-v2.contaazul.com

        ENV,
        file_get_contents($path),
    );
  }

  /** A commented-out line is documentation, not an assignment: it stays put. */
  public function testUpsertAppendsWhenOnlyACommentedLineExists(): void {
    $path = $this->writeFile("# CA_SCOPE=exemplo\n");

    $this->writer->upsert($path, 'CA_SCOPE', 'openid profile');

    self::assertSame("# CA_SCOPE=exemplo\n\nCA_SCOPE='openid profile'\n", file_get_contents($path));
  }

  /**
   * A duplicate assignment further down would still win at load time, so the
   * first one is updated and the stale one is removed.
   */
  public function testUpsertDropsALaterDuplicateAssignment(): void {
    $path = $this->writeFile("CA_CLIENT_ID=first\n# comentário\nCA_CLIENT_ID=second\n");

    $this->writer->upsert($path, 'CA_CLIENT_ID', 'only');

    self::assertSame("CA_CLIENT_ID=only\n# comentário\n", file_get_contents($path));
  }

  /** An `export`-prefixed assignment is still the same variable. */
  public function testUpsertReplacesAnExportedAssignment(): void {
    $path = $this->writeFile("export CA_CLIENT_ID=old\n");

    $this->writer->upsert($path, 'CA_CLIENT_ID', 'new');

    self::assertSame("CA_CLIENT_ID=new\n", file_get_contents($path));
  }

  /** Values that would confuse the parser are quoted so they round-trip verbatim. */
  public function testUpsertQuotesValuesThatNeedIt(): void {
    $path = $this->writeFile("CA_SCOPE=\n");

    $this->writer->upsert($path, 'CA_SCOPE', 'openid profile #1 $HOME');

    self::assertSame("CA_SCOPE='openid profile #1 \$HOME'\n", file_get_contents($path));
  }

  /** A value carrying a single quote falls through to double quotes with escapes. */
  public function testUpsertEscapesValuesContainingSingleQuotes(): void {
    $path = $this->writeFile("CA_SCOPE=\n");

    $this->writer->upsert($path, 'CA_SCOPE', "it's $1 \"here\"");

    self::assertSame("CA_SCOPE=\"it's \\\$1 \\\"here\\\"\"\n", file_get_contents($path));
  }

  /** Empty is written bare, which the loader already reads back as absent. */
  public function testUpsertWritesAnEmptyValueBare(): void {
    $path = $this->writeFile("CA_SCOPE=openid\n");

    $this->writer->upsert($path, 'CA_SCOPE', '');

    self::assertSame("CA_SCOPE=\n", file_get_contents($path));
  }

  /** Writing reapplies the permission, so a loosened file tightens on next use. */
  public function testUpsertReappliesRestrictivePermissions(): void {
    self::requirePosixPermissions();

    $path = $this->writeFile("CA_CLIENT_ID=old\n", 0644);

    $this->writer->upsert($path, 'CA_CLIENT_ID', 'new');

    self::assertPermissions('600', $path);
  }

  /** Writing to a file that is not there points at the wrong problem; refuse first. */
  public function testUpsertRefusesAMissingFile(): void {
    $this->expectException(ConfigException::class);

    $this->writer->upsert($this->directory . '/ausente.env', 'CA_CLIENT_ID', 'x');
  }

  private function writeFile(string $contents, int $mode = 0600): string {
    $path = $this->trackFile($this->directory . '/.env');
    file_put_contents($path, $contents);
    chmod($path, $mode);

    return $path;
  }

  private function trackFile(string $path): string {
    $this->files[] = $path;

    return $path;
  }

  private function trackDirectory(string $path): string {
    $this->directories[] = $path;

    return $path;
  }
}
