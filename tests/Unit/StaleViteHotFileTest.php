<?php

namespace Tests\Unit;

use App\Support\StaleViteHotFile;
use PHPUnit\Framework\TestCase;

class StaleViteHotFileTest extends TestCase
{
    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotFile = tempnam(sys_get_temp_dir(), 'hot');
    }

    protected function tearDown(): void
    {
        if (is_file($this->hotFile)) {
            unlink($this->hotFile);
        }

        parent::tearDown();
    }

    public function test_a_missing_hot_file_is_not_stale(): void
    {
        unlink($this->hotFile);

        $this->assertFalse(StaleViteHotFile::isStale($this->hotFile));
    }

    public function test_a_hot_file_pointing_at_a_running_server_is_not_stale(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);

        file_put_contents($this->hotFile, "http://{$address}");

        $this->assertFalse(StaleViteHotFile::isStale($this->hotFile));

        fclose($server);
    }

    public function test_a_hot_file_pointing_at_a_dead_server_is_stale(): void
    {
        // Grab a free port, then close it so nothing is listening there.
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        fclose($server);

        file_put_contents($this->hotFile, "http://{$address}");

        $this->assertTrue(StaleViteHotFile::isStale($this->hotFile));
    }

    public function test_a_garbage_hot_file_is_stale(): void
    {
        file_put_contents($this->hotFile, 'not a url');

        $this->assertTrue(StaleViteHotFile::isStale($this->hotFile));
    }
}
