<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Error\CliException;
use PHPUnit\Framework\TestCase;

final class CallbackServerTest extends TestCase
{
    /** @var list<resource> */
    private array $processes = [];


    protected function tearDown(): void {
        // Silenced: the spawned process may have already exited on its own by
        // the time cleanup runs, and terminating/closing an already-finished
        // process resource is not an error worth surfacing here.
        foreach ($this->processes as $process) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors
            @proc_terminate($process);
            // phpcs:ignore Generic.PHP.NoSilencedErrors
            @proc_close($process);
        }
        $this->processes = [];
    }


    public function testReturnsCodeFromCallbackRequest(): void {
        $port = $this->freePort();
        $this->spawnClient($port, ["GET /callback?code=abc123&state=st-1 HTTP/1.1\r\nHost: localhost\r\n\r\n"]);

        $server = new CallbackServer(port: $port, timeoutSeconds: 10);

        self::assertSame('abc123', $server->waitForCallback('st-1'));
    }


    /**
     * The regression that froze the browser mid-redirect: a browser opens
     * speculative sockets next to the navigation and may never send a byte on
     * them. Serving only the first connection meant the login stalled on the
     * silent one while the real callback waited unaccepted.
     */
    public function testIgnoresSilentConnectionAndServesTheRealCallback(): void {
        $port = $this->freePort();
        $this->spawnClient(
          $port, [
            NULL, // speculative socket: connects, stays open, says nothing
            "GET /callback?code=after-silence&state=st-2 HTTP/1.1\r\nHost: localhost\r\n\r\n",
          ]
        );

        $server = new CallbackServer(port: $port, timeoutSeconds: 20);
        $start  = microtime(TRUE);
        $code   = $server->waitForCallback('st-2');

        self::assertSame('after-silence', $code);
        self::assertLessThan(
          15.0,
          microtime(TRUE) - $start,
          'the silent connection must not hold the login window open',
        );
    }


    /** A real browser request runs well past a kilobyte of headers. */
    public function testHandlesRequestLargerThanASingleReadBuffer(): void {
        $port    = $this->freePort();
        $padding = str_repeat('X-Padding: '.str_repeat('a', 200)."\r\n", 40);
        $this->spawnClient(
          $port, [
            "GET /callback?code=big-headers&state=st-3 HTTP/1.1\r\nHost: localhost\r\n{$padding}\r\n",
          ]
        );

        $server = new CallbackServer(port: $port, timeoutSeconds: 10);

        self::assertSame('big-headers', $server->waitForCallback('st-3'));
    }


    public function testSkipsNonCallbackRequests(): void {
        $port = $this->freePort();
        $this->spawnClient(
          $port, [
            "GET /favicon.ico HTTP/1.1\r\nHost: localhost\r\n\r\n",
            "GET /callback?code=after-favicon&state=st-4 HTTP/1.1\r\nHost: localhost\r\n\r\n",
          ]
        );

        $server = new CallbackServer(port: $port, timeoutSeconds: 10);

        self::assertSame('after-favicon', $server->waitForCallback('st-4'));
    }


    public function testRejectsMismatchedState(): void {
        $port = $this->freePort();
        $this->spawnClient($port, ["GET /callback?code=abc&state=wrong HTTP/1.1\r\nHost: localhost\r\n\r\n"]);

        $server = new CallbackServer(port: $port, timeoutSeconds: 10);

        $this->expectException(CliException::class);
        $this->expectExceptionMessageMatches('/CSRF/');
        $server->waitForCallback('st-5');
    }


    public function testReportsProviderErrorWhenNoCodeIsReturned(): void {
        $port = $this->freePort();
        $this->spawnClient($port, ["GET /callback?error=access_denied&state=st-6 HTTP/1.1\r\nHost: localhost\r\n\r\n"]);

        $server = new CallbackServer(port: $port, timeoutSeconds: 10);

        $this->expectException(CliException::class);
        $this->expectExceptionMessageMatches('/access_denied/');
        $server->waitForCallback('st-6');
    }


    public function testTimesOutWhenNothingEverArrives(): void {
        $server = new CallbackServer(port: $this->freePort(), timeoutSeconds: 1);

        $this->expectException(CliException::class);
        $this->expectExceptionMessageMatches('/Timeout aguardando callback/');
        $server->waitForCallback('st-7');
    }


    private function freePort(): int {
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($probe, "não foi possível reservar uma porta livre: {$errstr}");
        $name = stream_socket_get_name($probe, FALSE);
        fclose($probe);
        self::assertIsString($name);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }


    /**
     * Drives the connections from a separate process, since waitForCallback
     * blocks the test process. Each entry is one connection: a string is sent
     * verbatim, null means "connect and stay silent".
     *
     * @param list<string|null> $connections
     */
    private function spawnClient(int $port, array $connections): void {
        $script = sprintf(
          '$conns = %s;
            $open = [];
            $deadline = microtime(true) + 10;
            foreach ($conns as $payload) {
                $sock = false;
                while ($sock === false && microtime(true) < $deadline) {
                    $sock = @stream_socket_client("tcp://127.0.0.1:%d", $e, $s, 1);
                    if ($sock === false) { usleep(20000); }
                }
                if ($sock === false) { exit(1); }
                $open[] = $sock;
                if ($payload !== null) { fwrite($sock, $payload); }
                usleep(150000);
            }
            sleep(5);',
          // Not a debugging leftover: this generates the PHP literal embedded in
          // the subprocess script below, and var_export() is the standard tool
          // for that.
          // phpcs:ignore ContaAzulCli.Functions.DebuggingFunctions
          var_export($connections, TRUE),
          $port,
        );

        $process = proc_open(
          [PHP_BINARY, '-r', $script],
          [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
          $pipes,
        );
        self::assertIsResource($process);
        $this->processes[] = $process;
    }


}
