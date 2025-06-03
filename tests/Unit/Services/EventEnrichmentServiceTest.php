<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Services\EventEnrichmentService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EventEnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventEnrichmentService $eventEnrichmentService;
    private UserService $mockUserService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockUserService = Mockery::mock(UserService::class);
        $this->eventEnrichmentService = new EventEnrichmentService($this->mockUserService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_enrich_event_with_user_details_success()
    {
        // Create test event
        $event = Event::factory()->create([
            'creator_user_id' => 'user-1',
            'title' => 'Test Event',
            'description' => 'Test Description'
        ]);

        // Add participants
        $event->participants()->attach('user-1', ['role' => 'organizer', 'status' => 'accepted']);
        $event->participants()->attach('user-2', ['role' => 'attendee', 'status' => 'pending']);
        $event->participants()->attach('user-3', ['role' => 'attendee', 'status' => 'accepted']);

        // Mock user service response
        $mockUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 'user-3', 'name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with(['user-1', 'user-2', 'user-3'])
            ->andReturn($mockUsers);

        $this->mockUserService
            ->shouldReceive('getUserById')
            ->once()
            ->with('user-1')
            ->andReturn($mockUsers[0]);

        $result = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals('Test Event', $result['title']);
        $this->assertEquals('Test Description', $result['description']);
        
        // Check creator details
        $this->assertEquals($mockUsers[0], $result['creator']);
        
        // Check participants
        $this->assertCount(3, $result['participants']);
        
        // Verify participant structure
        $participant1 = collect($result['participants'])->firstWhere('user.id', 'user-1');
        $this->assertEquals('John Doe', $participant1['user']['name']);
        $this->assertEquals('organizer', $participant1['role']);
        $this->assertEquals('accepted', $participant1['status']);
    }

    public function test_enrich_event_with_user_details_no_participants()
    {
        // Create test event without participants
        $event = Event::factory()->create([
            'creator_user_id' => 'user-1',
            'title' => 'Solo Event'
        ]);

        $mockCreator = ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with([])
            ->andReturn([]);

        $this->mockUserService
            ->shouldReceive('getUserById')
            ->once()
            ->with('user-1')
            ->andReturn($mockCreator);

        $result = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

        $this->assertEquals($mockCreator, $result['creator']);
        $this->assertEquals([], $result['participants']);
    }

    public function test_enrich_event_with_user_details_creator_not_found()
    {
        $event = Event::factory()->create([
            'creator_user_id' => 'non-existent-user',
            'title' => 'Orphaned Event'
        ]);

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with([])
            ->andReturn([]);

        $this->mockUserService
            ->shouldReceive('getUserById')
            ->once()
            ->with('non-existent-user')
            ->andReturn(null);

        $result = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

        $this->assertNull($result['creator']);
        $this->assertEquals([], $result['participants']);
    }

    public function test_enrich_events_with_user_details_multiple_events()
    {
        // Create multiple test events
        $event1 = Event::factory()->create(['creator_user_id' => 'user-1', 'title' => 'Event 1']);
        $event2 = Event::factory()->create(['creator_user_id' => 'user-2', 'title' => 'Event 2']);

        // Add participants
        $event1->participants()->attach('user-1', ['role' => 'organizer', 'status' => 'accepted']);
        $event1->participants()->attach('user-2', ['role' => 'attendee', 'status' => 'pending']);
        
        $event2->participants()->attach('user-2', ['role' => 'organizer', 'status' => 'accepted']);
        $event2->participants()->attach('user-3', ['role' => 'attendee', 'status' => 'accepted']);

        $events = collect([$event1, $event2]);

        // Mock user service responses
        $mockUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 'user-3', 'name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with(['user-1', 'user-2', 'user-3'])
            ->andReturn($mockUsers);

        $result = $this->eventEnrichmentService->enrichEventsWithUserDetails($events);

        $this->assertCount(2, $result);
        
        // Check first event
        $enrichedEvent1 = $result[0];
        $this->assertEquals('Event 1', $enrichedEvent1['title']);
        $this->assertEquals('John Doe', $enrichedEvent1['creator']['name']);
        $this->assertCount(2, $enrichedEvent1['participants']);
        
        // Check second event
        $enrichedEvent2 = $result[1];
        $this->assertEquals('Event 2', $enrichedEvent2['title']);
        $this->assertEquals('Jane Smith', $enrichedEvent2['creator']['name']);
        $this->assertCount(2, $enrichedEvent2['participants']);
    }

    public function test_enrich_events_with_user_details_empty_collection()
    {
        $events = collect([]);

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->eventEnrichmentService->enrichEventsWithUserDetails($events);

        $this->assertEquals([], $result);
    }

    public function test_get_events_for_user_success()
    {
        $userId = 'user-1';

        // Create events where user is creator
        $event1 = Event::factory()->create(['creator_user_id' => $userId, 'title' => 'Created Event']);
        
        // Create event where user is participant
        $event2 = Event::factory()->create(['creator_user_id' => 'user-2', 'title' => 'Participating Event']);
        $event2->participants()->attach($userId, ['role' => 'attendee', 'status' => 'accepted']);
        
        // Create event where user is not involved (should not be returned)
        Event::factory()->create(['creator_user_id' => 'user-3', 'title' => 'Other Event']);

        $mockUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->andReturn($mockUsers);

        $result = $this->eventEnrichmentService->getEventsForUser($userId);

        $this->assertCount(2, $result);
        
        $titles = collect($result)->pluck('title')->toArray();
        $this->assertContains('Created Event', $titles);
        $this->assertContains('Participating Event', $titles);
    }

    public function test_get_events_for_calendar_success()
    {
        $calendarId = 'calendar-1';

        // Create events for the calendar
        $event1 = Event::factory()->create(['calendar_id' => $calendarId, 'creator_user_id' => 'user-1', 'title' => 'Calendar Event 1']);
        $event2 = Event::factory()->create(['calendar_id' => $calendarId, 'creator_user_id' => 'user-2', 'title' => 'Calendar Event 2']);
        
        // Create event for different calendar (should not be returned)
        Event::factory()->create(['calendar_id' => 'calendar-2', 'creator_user_id' => 'user-3', 'title' => 'Other Calendar Event']);

        $event1->participants()->attach('user-1', ['role' => 'organizer', 'status' => 'accepted']);
        $event2->participants()->attach('user-2', ['role' => 'organizer', 'status' => 'accepted']);

        $mockUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->andReturn($mockUsers);

        $result = $this->eventEnrichmentService->getEventsForCalendar($calendarId);

        $this->assertCount(2, $result);
        
        $titles = collect($result)->pluck('title')->toArray();
        $this->assertContains('Calendar Event 1', $titles);
        $this->assertContains('Calendar Event 2', $titles);
    }

    public function test_get_events_for_calendar_empty_result()
    {
        $calendarId = 'empty-calendar';

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->eventEnrichmentService->getEventsForCalendar($calendarId);

        $this->assertEquals([], $result);
    }

    public function test_enrich_event_handles_partial_user_data()
    {
        $event = Event::factory()->create([
            'creator_user_id' => 'user-1',
            'title' => 'Test Event'
        ]);

        $event->participants()->attach('user-1', ['role' => 'organizer', 'status' => 'accepted']);
        $event->participants()->attach('user-2', ['role' => 'attendee', 'status' => 'pending']);

        // Mock user service to return only partial user data (missing user-2)
        $mockUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
        ];

        $this->mockUserService
            ->shouldReceive('getUsersByIds')
            ->once()
            ->with(['user-1', 'user-2'])
            ->andReturn($mockUsers);

        $this->mockUserService
            ->shouldReceive('getUserById')
            ->once()
            ->with('user-1')
            ->andReturn($mockUsers[0]);

        $result = $this->eventEnrichmentService->enrichEventWithUserDetails($event);

        $this->assertEquals($mockUsers[0], $result['creator']);
        $this->assertCount(2, $result['participants']);
        
        // Check that participant without user data still exists but with null user
        $participantWithoutUser = collect($result['participants'])->firstWhere('user', null);
        $this->assertNotNull($participantWithoutUser);
        $this->assertEquals('user-2', $participantWithoutUser['user_id']);
    }
}
