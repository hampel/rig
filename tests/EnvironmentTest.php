<?php

declare(strict_types=1);

namespace Hampel\Rig\Tests;

use Hampel\Rig\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
class EnvironmentTest extends TestCase
{
    public function test_it_parses_a_pair(): void
    {
        $this->assertSame(['WEBHOOK_URL', 'https://example.test/hook'], Environment::parse('WEBHOOK_URL=https://example.test/hook'));
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        $this->assertSame(['KEY', 'value'], Environment::parse('  KEY = value  '));
    }

    public function test_it_strips_matching_quotes(): void
    {
        $this->assertSame(['KEY', 'a value'], Environment::parse('KEY="a value"'));
        $this->assertSame(['KEY', 'a value'], Environment::parse("KEY='a value'"));
    }

    public function test_it_keeps_mismatched_quotes(): void
    {
        $this->assertSame(['KEY', '"value'], Environment::parse('KEY="value'));
    }

    public function test_it_keeps_an_equals_sign_in_the_value(): void
    {
        $this->assertSame(['DSN', 'a=b'], Environment::parse('DSN=a=b'));
    }

    public function test_it_skips_comments_and_blanks_and_junk(): void
    {
        $this->assertNull(Environment::parse('# a comment'));
        $this->assertNull(Environment::parse(''));
        $this->assertNull(Environment::parse('   '));
        $this->assertNull(Environment::parse('no equals sign here'));
        $this->assertNull(Environment::parse('=orphaned'));
    }

    public function test_it_loads_a_file_into_the_environment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rig');
        $this->assertIsString($file);
        file_put_contents($file, "# comment\nRIG_TEST_LOADED=yes\n\nRIG_TEST_QUOTED='a b'\n");

        $loaded = Environment::load($file);
        unlink($file);

        $this->assertSame(['RIG_TEST_LOADED' => 'yes', 'RIG_TEST_QUOTED' => 'a b'], $loaded);
        $this->assertSame('yes', getenv('RIG_TEST_LOADED'));

        putenv('RIG_TEST_LOADED');
        putenv('RIG_TEST_QUOTED');
    }

    public function test_the_existing_environment_wins(): void
    {
        putenv('RIG_TEST_PRESET=from the shell');

        $file = tempnam(sys_get_temp_dir(), 'rig');
        $this->assertIsString($file);
        file_put_contents($file, "RIG_TEST_PRESET=from the file\n");

        $loaded = Environment::load($file);
        unlink($file);

        $this->assertSame([], $loaded);
        $this->assertSame('from the shell', getenv('RIG_TEST_PRESET'));

        putenv('RIG_TEST_PRESET');
    }

    public function test_a_missing_file_is_not_an_error(): void
    {
        $this->assertSame([], Environment::load('/nonexistent/.env'));
    }
}
