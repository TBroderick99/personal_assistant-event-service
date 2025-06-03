<?php

namespace Tests\Unit\Services;

use App\Services\CalendarService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    private CalendarService $calendarService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set the config value for testing
        $this->app['config']->set('services.calendar_service.base_url', 'http://calendar-service.test');
        
        $this->calendarService = new CalendarService();
    }

    protected function tearDown(): void
    {
        Http::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_validate_calendar_success()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response([
                'success' => true,
                'data' => ['valid' => true]
            ], 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        $this->assertTrue($result);

        Http::assertSent(function (Request $request) use ($userId) {
            return str_contains($request->url(), 'http://calendar-service.test/api/calendars/calendar-1/validate')
                && str_contains($request->url(), 'user_id=user-1')
                && $request->method() === 'GET';
        });
    }

    public function test_validate_calendar_invalid()
    {
        $calendarId = 'invalid-calendar';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/invalid-calendar/validate*' => Http::response([
                'success' => true,
                'data' => ['valid' => false]
            ], 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        $this->assertFalse($result);
    }

    public function test_validate_calendar_no_access()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-2';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response([
                'success' => true,
                'data' => ['valid' => false]
            ], 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        $this->assertFalse($result);
    }

    public function test_validate_calendar_api_failure()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // API failure means response is not successful, so should return false
        $this->assertFalse($result);
    }

    public function test_validate_calendar_network_failure()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response('', 500)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // Network failure means response is not successful, so should return false
        $this->assertFalse($result);
    }

    public function test_get_calendar_by_id_success()
    {
        $calendarId = 'calendar-1';
        $expectedCalendar = [
            'id' => 'calendar-1',
            'name' => 'Work Calendar',
            'description' => 'Calendar for work events',
            'owner_id' => 'user-1'
        ];

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1' => Http::response([
                'success' => true,
                'data' => $expectedCalendar
            ], 200)
        ]);

        $result = $this->calendarService->getCalendarById($calendarId);

        $this->assertEquals($expectedCalendar, $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://calendar-service.test/api/calendars/calendar-1'
                && $request->method() === 'GET';
        });
    }

    public function test_get_calendar_by_id_not_found()
    {
        $calendarId = 'non-existent-calendar';

        Http::fake([
            'calendar-service.test/api/calendars/non-existent-calendar' => Http::response([
                'success' => false,
                'message' => 'Calendar not found'
            ], 404)
        ]);

        $result = $this->calendarService->getCalendarById($calendarId);

        $this->assertNull($result);
    }

    public function test_get_calendar_by_id_api_failure()
    {
        $calendarId = 'calendar-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->calendarService->getCalendarById($calendarId);

        $this->assertNull($result);
    }

    public function test_get_user_calendars_success()
    {
        $userId = 'user-1';
        $expectedCalendars = [
            ['id' => 'calendar-1', 'name' => 'Work Calendar', 'owner_id' => 'user-1'],
            ['id' => 'calendar-2', 'name' => 'Personal Calendar', 'owner_id' => 'user-1'],
        ];

        Http::fake([
            'calendar-service.test/api/users/user-1/calendars' => Http::response([
                'success' => true,
                'data' => $expectedCalendars
            ], 200)
        ]);

        $result = $this->calendarService->getUserCalendars($userId);

        $this->assertEquals($expectedCalendars, $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://calendar-service.test/api/users/user-1/calendars'
                && $request->method() === 'GET';
        });
    }

    public function test_get_user_calendars_empty()
    {
        $userId = 'user-without-calendars';

        Http::fake([
            'calendar-service.test/api/users/user-without-calendars/calendars' => Http::response([
                'success' => true,
                'data' => []
            ], 200)
        ]);

        $result = $this->calendarService->getUserCalendars($userId);

        $this->assertEquals([], $result);
    }

    public function test_get_user_calendars_api_failure()
    {
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/users/user-1/calendars' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->calendarService->getUserCalendars($userId);

        $this->assertEquals([], $result);
    }

    public function test_get_user_calendars_network_failure()
    {
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/users/user-1/calendars' => Http::response('', 500)
        ]);

        $result = $this->calendarService->getUserCalendars($userId);

        $this->assertEquals([], $result);
    }

    public function test_validate_calendar_handles_malformed_response()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response('invalid json', 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // Malformed response should return false (data.valid defaults to false)
        $this->assertFalse($result);
    }

    public function test_get_calendar_by_id_handles_malformed_response()
    {
        $calendarId = 'calendar-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1' => Http::response('invalid json', 200)
        ]);

        $result = $this->calendarService->getCalendarById($calendarId);

        $this->assertNull($result);
    }

    public function test_get_user_calendars_handles_malformed_response()
    {
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/users/user-1/calendars' => Http::response('invalid json', 200)
        ]);

        $result = $this->calendarService->getUserCalendars($userId);

        $this->assertEquals([], $result);
    }

    public function test_validate_calendar_missing_data_fields()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response([
                'success' => true,
                'data' => [] // missing valid field
            ], 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // Missing valid field should default to false
        $this->assertFalse($result);
    }

    public function test_validate_calendar_missing_success_field()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => Http::response([
                'data' => ['valid' => true] // missing success field but response is successful
            ], 200)
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // Should still work because HTTP response is successful
        $this->assertTrue($result);
    }

    public function test_validate_calendar_exception_fails_open()
    {
        $calendarId = 'calendar-1';
        $userId = 'user-1';

        // Simulate an exception by making Http throw
        Http::fake([
            'calendar-service.test/api/calendars/calendar-1/validate*' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

        $result = $this->calendarService->validateCalendar($calendarId, $userId);

        // Should fail open (return true) when an exception occurs
        $this->assertTrue($result);
    }
}
