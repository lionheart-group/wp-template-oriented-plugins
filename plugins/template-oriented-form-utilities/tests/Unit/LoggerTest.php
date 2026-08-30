<?php

namespace TofuPlugin\Tests\Unit;

use TofuPlugin\Logger;

/**
 * Pins the log line format.
 *
 * It is deliberately byte-compatible with the Monolog output this replaced,
 * so that log files spanning the change stay greppable with one expression
 * and an existing file can simply be appended to.
 *
 *     [2026-01-22T07:21:30.184085+00:00] tofu.INFO: Message {"a":1} []
 */
class LoggerTest extends BaseTestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // tests/bootstrap.php already ran Logger::init('test').
        $path = Logger::getLogFilePath();
        $this->assertIsString($path);
        $this->logFile = $path;

        @unlink($this->logFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);

        parent::tearDown();
    }

    private function lastLine(): string
    {
        $this->assertFileExists($this->logFile);
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($this->logFile))));

        return end($lines) ?: '';
    }

    public function testLineMatchesTheEstablishedFormat(): void
    {
        Logger::info('Plain message');

        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}\] tofu\.INFO: Plain message \[\] \[\]$/',
            $this->lastLine()
        );
    }

    public function testContextIsAppendedAsJson(): void
    {
        Logger::info('With context', ['form_key' => 'contact', 'n' => 3]);

        $this->assertStringEndsWith(': With context {"form_key":"contact","n":3} []', $this->lastLine());
    }

    /**
     * Unicode and slashes stay readable — an escaped log is a log nobody
     * greps successfully.
     */
    public function testContextKeepsUnicodeAndSlashesUnescaped(): void
    {
        Logger::error('Unicode ここ', ['url' => 'https://example.com/x']);

        $line = $this->lastLine();
        $this->assertStringContainsString('Unicode ここ', $line);
        $this->assertStringContainsString('"url":"https://example.com/x"', $line);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function levelProvider(): array
    {
        return [
            'info'      => ['info'],
            'warning'   => ['warning'],
            'error'     => ['error'],
            'critical'  => ['critical'],
            'alert'     => ['alert'],
            'emergency' => ['emergency'],
        ];
    }

    /**
     * @dataProvider levelProvider
     */
    public function testEveryLevelWritesItsOwnName(string $method): void
    {
        Logger::$method('Message at ' . $method);

        $this->assertStringContainsString(
            'tofu.' . strtoupper($method) . ': Message at ' . $method,
            $this->lastLine()
        );
    }

    public function testWritesAppendRatherThanTruncate(): void
    {
        Logger::info('First');
        Logger::info('Second');

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($this->logFile))));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('First', $lines[0]);
        $this->assertStringContainsString('Second', $lines[1]);
    }

    /**
     * Logging is a convenience; an unwritable path must not take a form
     * submission down with it.
     */
    public function testAnUnwritablePathIsSwallowed(): void
    {
        $property = new \ReflectionProperty(Logger::class, 'filePath');
        $property->setAccessible(true);
        $original = $property->getValue();

        try {
            $property->setValue(null, '/nonexistent-directory/tofu.log');

            Logger::error('This must not raise');

            $this->assertTrue(true, 'Writing to an unwritable path returned normally.');
        } finally {
            $property->setValue(null, $original);
        }
    }
}
