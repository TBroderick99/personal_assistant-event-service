<?php

namespace Tests\Unit\Rules;

use App\Rules\HexColor;
use Tests\TestCase;

class HexColorTest extends TestCase
{
    private HexColor $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new HexColor();
    }

    /** @test */
    public function it_passes_for_valid_6_character_hex_color()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#FF0000', $fail);

        $this->assertTrue($passes);
    }

    /** @test */
    public function it_passes_for_valid_8_character_hex_color_with_alpha()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#FF0000FF', $fail);

        $this->assertTrue($passes);
    }

    /** @test */
    public function it_passes_for_lowercase_hex_color()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#ff0000', $fail);

        $this->assertTrue($passes);
    }

    /** @test */
    public function it_passes_for_mixed_case_hex_color()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#Ff00aA', $fail);

        $this->assertTrue($passes);
    }

    /** @test */
    public function it_fails_for_hex_color_without_hash()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', 'FF0000', $fail);

        $this->assertFalse($passes);
    }

    /** @test */
    public function it_fails_for_invalid_hex_characters()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#GG0000', $fail);

        $this->assertFalse($passes);
    }

    /** @test */
    public function it_fails_for_wrong_length_hex_color()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#FF00', $fail);

        $this->assertFalse($passes);
    }

    /** @test */
    public function it_fails_for_too_long_hex_color()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '#FF0000FFAA', $fail);

        $this->assertFalse($passes);
    }

    /** @test */
    public function it_fails_for_non_string_input()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', 123456, $fail);

        $this->assertFalse($passes);
    }

    /** @test */
    public function it_fails_for_empty_string()
    {
        $passes = true;
        $fail = function ($message) use (&$passes) {
            $passes = false;
        };

        $this->rule->validate('color', '', $fail);

        $this->assertFalse($passes);
    }
}
