<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Session\SessionIdGenerator;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class SessionIdGeneratorTest extends TestCase
{
    private SessionIdGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SessionIdGenerator();
    }

    public function test_generate_returns_string(): void
    {
        $sessionId = $this->generator->generate();

        $this->assertIsString($sessionId);
    }

    public function test_generate_returns_32_character_hex_string(): void
    {
        $sessionId = $this->generator->generate();

        $this->assertEquals(32, strlen($sessionId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $sessionId);
    }

    public function test_generate_returns_unique_ids(): void
    {
        $ids = [];

        // Generate multiple IDs and check they're all unique
        for ($i = 0; $i < 100; $i++) {
            $id = $this->generator->generate();
            $this->assertNotContains($id, $ids, 'Generated duplicate session ID');
            $ids[] = $id;
        }
    }

    public function test_generate_uses_cryptographically_secure_randomness(): void
    {
        // Generate two IDs and ensure they're different
        $id1 = $this->generator->generate();
        $id2 = $this->generator->generate();

        $this->assertNotEquals($id1, $id2);

        // Check that the IDs have good entropy (not all the same character)
        $this->assertNotEquals(str_repeat($id1[0], 32), $id1);
        $this->assertNotEquals(str_repeat($id2[0], 32), $id2);
    }

    public function test_multiple_generators_produce_unique_ids(): void
    {
        $generator1 = new SessionIdGenerator();
        $generator2 = new SessionIdGenerator();

        $id1 = $generator1->generate();
        $id2 = $generator2->generate();

        $this->assertNotEquals($id1, $id2);
    }

    public function test_generated_id_format_is_consistent(): void
    {
        $pattern = '/^[a-f0-9]{32}$/';

        for ($i = 0; $i < 10; $i++) {
            $sessionId = $this->generator->generate();
            $this->assertMatchesRegularExpression($pattern, $sessionId);
        }
    }

    public function test_generated_id_represents_16_bytes(): void
    {
        $sessionId = $this->generator->generate();

        // Convert hex string back to binary to verify it represents 16 bytes
        $binaryData = hex2bin($sessionId);
        $this->assertEquals(16, strlen($binaryData));
    }

    public function test_generated_ids_have_good_distribution(): void
    {
        $charCounts = array_fill_keys(str_split('0123456789abcdef'), 0);

        // Generate many IDs and count character frequency
        for ($i = 0; $i < 100; $i++) {
            $sessionId = $this->generator->generate();
            foreach (str_split($sessionId) as $char) {
                $charCounts[$char]++;
            }
        }

        // Check that all hex characters appear (good distribution)
        foreach ($charCounts as $char => $count) {
            $this->assertGreaterThan(0, $count, "Character '{$char}' never appeared in generated IDs");
        }

        // Check that no character dominates (rough distribution check)
        $totalChars = array_sum($charCounts);
        $expectedAverage = $totalChars / 16; // 16 hex characters

        foreach ($charCounts as $char => $count) {
            $this->assertLessThan($expectedAverage * 3, $count, "Character '{$char}' appears too frequently");
        }
    }
}
