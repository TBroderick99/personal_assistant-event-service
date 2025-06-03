<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidCalendar;
use App\Services\CalendarService;
use Illuminate\Translation\PotentiallyTranslatedString;
use Mockery;
use Tests\TestCase;

class ValidCalendarTest extends TestCase
{
    private CalendarService $mockCalendarService;
    private ValidCalendar $rule;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockCalendarService = Mockery::mock(CalendarService::class);
        $this->rule = new ValidCalendar('user-1', $this->mockCalendarService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_passes_when_calendar_is_valid_and_user_has_access()
    {
        $calendarId = 'calendar-1';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-1')
            ->andReturn(true);

        $passes = $this->rule->passes('calendar_id', $calendarId);

        $this->assertTrue($passes);
    }

    public function test_fails_when_calendar_is_invalid()
    {
        $calendarId = 'invalid-calendar';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-1')
            ->andReturn(false);

        $passes = $this->rule->passes('calendar_id', $calendarId);

        $this->assertFalse($passes);
    }

    public function test_fails_when_user_has_no_access()
    {
        $calendarId = 'calendar-1';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-1')
            ->andReturn(false);

        $passes = $this->rule->passes('calendar_id', $calendarId);

        $this->assertFalse($passes);
    }

    public function test_fails_when_calendar_service_throws_exception()
    {
        $calendarId = 'calendar-1';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-1')
            ->andThrow(new \Exception('Service unavailable'));

        $passes = $this->rule->passes('calendar_id', $calendarId);

        $this->assertFalse($passes);
    }

    public function test_message_returns_correct_error_message()
    {
        $message = $this->rule->message();

        $this->assertEquals('The selected calendar is invalid or you do not have access to it.', $message);
    }

    public function test_handles_empty_calendar_id()
    {
        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with('', 'user-1')
            ->andReturn(false);

        $passes = $this->rule->passes('calendar_id', '');

        $this->assertFalse($passes);
    }

    public function test_handles_null_calendar_id()
    {
        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with(null, 'user-1')
            ->andReturn(false);

        $passes = $this->rule->passes('calendar_id', null);

        $this->assertFalse($passes);
    }

    public function test_handles_numeric_calendar_id()
    {
        $calendarId = 123;

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-1')
            ->andReturn(true);

        $passes = $this->rule->passes('calendar_id', $calendarId);

        $this->assertTrue($passes);
    }

    public function test_constructor_with_different_user_id()
    {
        $rule = new ValidCalendar('user-2', $this->mockCalendarService);
        $calendarId = 'calendar-1';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId, 'user-2')
            ->andReturn(true);

        $passes = $rule->passes('calendar_id', $calendarId);

        $this->assertTrue($passes);
    }

    public function test_multiple_validation_calls_with_same_instance()
    {
        $calendarId1 = 'calendar-1';
        $calendarId2 = 'calendar-2';

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId1, 'user-1')
            ->andReturn(true);

        $this->mockCalendarService
            ->shouldReceive('validateCalendar')
            ->once()
            ->with($calendarId2, 'user-1')
            ->andReturn(false);

        $passes1 = $this->rule->passes('calendar_id', $calendarId1);
        $passes2 = $this->rule->passes('calendar_id', $calendarId2);

        $this->assertTrue($passes1);
        $this->assertFalse($passes2);
    }
}
