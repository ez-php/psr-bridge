<?php

declare(strict_types=1);

namespace Tests\Http;

use Composer\Autoload\ClassLoader;
use EzPhp\Http\Request;
use EzPhp\Http\UploadedFile;
use EzPhp\PsrBridge\BridgeException;
use EzPhp\PsrBridge\Http\RequestConverter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionClass;
use Tests\Support\PsrBridgeForeignRequest;
use Tests\TestCase;

/**
 * Class PsrBridgeRequestConverterTest
 *
 * @package Tests\Http
 */
#[CoversClass(RequestConverter::class)]
#[UsesClass(BridgeException::class)]
final class PsrBridgeRequestConverterTest extends TestCase
{
    private Psr17Factory $factory;

    private RequestConverter $converter;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
        $this->converter = new RequestConverter($this->factory, $this->factory, $this->factory);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ezpsrtest');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testToPsrCopiesTheWholeRequest(): void
    {
        $upload = new UploadedFile('a.txt', 'text/plain', 5, $this->tempFile('hello'), UPLOAD_ERR_OK);
        $failed = new UploadedFile('b.txt', 'text/plain', 0, '', UPLOAD_ERR_NO_FILE);
        $request = new Request(
            method: 'POST',
            uri: '/orders/7?x=1',
            query: ['x' => '1'],
            body: ['name' => 'Ada'],
            headers: ['Host' => 'shop.test', 'X-Tags' => ['a', 'b'], 'X-Count' => 3, 'X-Object' => new \stdClass()],
            cookies: ['sid' => 's1'],
            server: ['HTTPS' => 'on', 'REMOTE_ADDR' => '10.0.0.1'],
            rawBody: 'name=Ada',
            params: ['id' => '7'],
            files: ['upload' => $upload, 'failed' => $failed],
        );

        $psr = $this->converter->toPsr($request);

        $this->assertSame('POST', $psr->getMethod());
        $this->assertSame('https://shop.test/orders/7?x=1', (string) $psr->getUri());
        $this->assertSame(['x' => '1'], $psr->getQueryParams());
        $this->assertSame(['name' => 'Ada'], $psr->getParsedBody());
        $this->assertSame(['sid' => 's1'], $psr->getCookieParams());
        $this->assertSame('10.0.0.1', $psr->getServerParams()['REMOTE_ADDR'] ?? null);
        $this->assertSame('name=Ada', (string) $psr->getBody());
        $this->assertSame(['a', 'b'], $psr->getHeader('x-tags'));
        $this->assertSame(['3'], $psr->getHeader('x-count'));
        $this->assertFalse($psr->hasHeader('x-object'));
        $this->assertSame('7', $psr->getAttribute('id'));

        $files = $psr->getUploadedFiles();
        $this->assertSame('hello', (string) $files['upload']->getStream());
        $this->assertSame('a.txt', $files['upload']->getClientFilename());
        $this->assertSame(UPLOAD_ERR_NO_FILE, $files['failed']->getError());
    }

    public function testToPsrKeepsRelativeUriWithoutHostAndUsesHttpByDefault(): void
    {
        $this->assertSame('/a?b=c', (string) $this->converter->toPsr(new Request('GET', '/a?b=c'))->getUri());
        $this->assertSame('http://h/a', (string) $this->converter->toPsr(new Request('GET', '/a', headers: ['host' => 'h'], server: ['HTTPS' => 'off']))->getUri());
        $this->assertNull($this->converter->toPsr(new Request('GET', '/'))->getParsedBody());
    }

    public function testToPsrRejectsOtherRequestImplementations(): void
    {
        $this->expectException(BridgeException::class);

        $this->converter->toPsr(new PsrBridgeForeignRequest());
    }

    public function testFromPsrCopiesTheWholeRequest(): void
    {
        $fileStream = $this->factory->createStreamFromFile($this->tempFile('on disk'));
        $psr = $this->factory->createServerRequest('PUT', 'https://shop.test/orders/7?x=1', ['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeader('X-Tags', ['a', 'b'])
            ->withQueryParams(['x' => '1'])
            ->withParsedBody(['name' => 'Ada'])
            ->withCookieParams(['sid' => 's2'])
            ->withBody($this->factory->createStream('raw'))
            ->withAttribute('id', '7')
            ->withUploadedFiles([
                'memory' => $this->factory->createUploadedFile($this->factory->createStream('in memory'), 9, UPLOAD_ERR_OK, 'm.txt', 'text/plain'),
                'disk' => $this->factory->createUploadedFile($fileStream, 7, UPLOAD_ERR_OK, 'd.txt'),
                'failed' => $this->factory->createUploadedFile($this->factory->createStream(''), 0, UPLOAD_ERR_PARTIAL),
                'nested' => [$this->factory->createUploadedFile($this->factory->createStream('x'))],
            ]);

        $request = $this->converter->fromPsr($psr);

        $this->assertSame('PUT', $request->method());
        $this->assertSame('/orders/7?x=1', $request->uri());
        $this->assertSame('a, b', $request->header('x-tags'));
        $this->assertSame('shop.test', $request->header('host'));
        $this->assertSame(['x' => '1'], $request->queryParams());
        $this->assertSame(['name' => 'Ada'], $request->bodyParams());
        $this->assertSame(['sid' => 's2'], $request->cookies());
        $this->assertSame('10.0.0.2', $request->server('REMOTE_ADDR'));
        $this->assertSame('raw', $request->rawBody());
        $this->assertSame('7', $request->param('id'));

        $files = $request->files();
        $this->assertSame(['memory', 'disk', 'failed'], array_keys($files));
        $this->tempFiles[] = $files['memory']->tmpName();
        $this->assertSame('in memory', file_get_contents($files['memory']->tmpName()));
        $this->assertSame('m.txt', $files['memory']->originalName());
        $this->assertSame('on disk', file_get_contents($files['disk']->tmpName()));
        $this->assertSame('', $files['failed']->tmpName());
        $this->assertSame(UPLOAD_ERR_PARTIAL, $files['failed']->error());
    }

    public function testBridgedUploadsCanBeMoved(): void
    {
        $diskSource = $this->tempFile('on disk');
        $psr = $this->factory->createServerRequest('POST', '/')->withUploadedFiles([
            'memory' => $this->factory->createUploadedFile($this->factory->createStream('in memory'), 9, UPLOAD_ERR_OK, 'm.txt'),
            'disk' => $this->factory->createUploadedFile($this->factory->createStreamFromFile($diskSource), 7, UPLOAD_ERR_OK, 'd.txt'),
        ]);

        $files = $this->converter->fromPsr($psr)->files();
        $memorySource = $files['memory']->tmpName();
        $this->tempFiles[] = $memorySource;

        foreach (['memory' => 'in memory', 'disk' => 'on disk'] as $key => $contents) {
            $destination = sys_get_temp_dir() . '/ezpsrmoved_' . $key . '_' . bin2hex(random_bytes(4));
            $this->tempFiles[] = $destination;

            $files[$key]->moveTo($destination);

            $this->assertSame($contents, file_get_contents($destination));
        }

        $this->assertFileDoesNotExist($memorySource);
        $this->assertFileDoesNotExist($diskSource);
    }

    public function testInMemoryUploadCopyIsRemovedWhenTheProcessExits(): void
    {
        // A shutdown hook only fires when the process ends, so convert in a child process.
        $loader = (string) (new ReflectionClass(ClassLoader::class))->getFileName();
        $code = sprintf(
            'require %s; $f = new \\Nyholm\\Psr7\\Factory\\Psr17Factory();'
            . ' $c = new \\EzPhp\\PsrBridge\\Http\\RequestConverter($f, $f, $f);'
            . ' $r = $c->fromPsr($f->createServerRequest(\'POST\', \'/\')->withUploadedFiles(['
            . ' \'a\' => $f->createUploadedFile($f->createStream(\'bytes\'), 5, UPLOAD_ERR_OK, \'a.txt\')]));'
            . ' $p = $r->files()[\'a\']->tmpName(); echo $p, "\\n", is_file($p) ? \'exists\' : \'missing\';',
            var_export(dirname($loader, 2) . '/autoload.php', true),
        );
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code), $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
        [$path, $state] = $output + ['', ''];
        $this->tempFiles[] = $path;
        $this->assertSame('exists', $state); // usable while the process runs
        $this->assertFileDoesNotExist($path); // gone once it exits
    }

    public function testFromPsrHandlesObjectBodyAndEmptyPath(): void
    {
        $body = new \stdClass();
        $body->a = 1;
        $psr = $this->factory->createServerRequest('POST', 'https://shop.test')->withParsedBody($body);

        $request = $this->converter->fromPsr($psr);

        $this->assertSame('/', $request->uri());
        $this->assertSame(['a' => 1], $request->bodyParams());
        $this->assertSame([], $this->converter->fromPsr($this->factory->createServerRequest('GET', '/'))->bodyParams());
    }

    public function testRoundTripPreservesTheRequest(): void
    {
        $original = new Request('PATCH', '/x?y=1', query: ['y' => '1'], body: ['z' => 2], headers: ['x-a' => 'b'], params: ['p' => 'q'], rawBody: 'z=2');

        $copy = $this->converter->fromPsr($this->converter->toPsr($original));

        $this->assertSame($original->method(), $copy->method());
        $this->assertSame($original->uri(), $copy->uri());
        $this->assertSame($original->queryParams(), $copy->queryParams());
        $this->assertSame($original->bodyParams(), $copy->bodyParams());
        $this->assertSame($original->headers(), $copy->headers());
        $this->assertSame($original->params(), $copy->params());
        $this->assertSame($original->rawBody(), $copy->rawBody());
    }
}
