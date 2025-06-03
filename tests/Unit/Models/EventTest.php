<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set a consistent timezone for testing
        Carbon::setTestNow('2025-06-03 12:00:00');
    }

    /** @test */
    public function it_can_create_an_event()
    {
        $eventData = [
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'description' => 'A test event description',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'is_all_day' => false,
            'timezone' => 'America/Argentina/Buenos_Aires',
            'location' => 'Conference Room A',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'status' => 'confirmed'
        ];

        $event = Event::create($eventData);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($eventData['title'], $event->title);
        $this->assertEquals($eventData['calendar_id'], $event->calendar_id);
        $this->assertEquals($eventData['creator_user_id'], $event->creator_user_id);
        $this->assertEquals($eventData['status'], $event->status);
        $this->assertFalse($event->is_all_day);
        $this->assertDatabaseHas('events', ['title' => 'Test Event']);
    }

    /** @test */
    public function it_casts_datetime_fields_properly()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $this->assertInstanceOf(Carbon::class, $event->start_datetime);
        $this->assertInstanceOf(Carbon::class, $event->end_datetime);
        $this->assertInstanceOf(Carbon::class, $event->created_at);
        $this->assertInstanceOf(Carbon::class, $event->updated_at);
    }

    /** @test */
    public function it_casts_is_all_day_to_boolean()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'is_all_day' => 1
        ]);

        $this->assertTrue($event->is_all_day);
        $this->assertIsBool($event->is_all_day);
    }

    /** @test */
    public function it_has_participants_relationship()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        // Create a participant
        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $participants = $event->participants;
        
        $this->assertCount(1, $participants);
        $this->assertInstanceOf(EventParticipant::class, $participants->first());
    }

    /** @test */
    public function it_can_get_accepted_participants()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        // Create participants with different statuses
        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440005',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $acceptedParticipants = $event->acceptedParticipants;
        
        $this->assertCount(2, $acceptedParticipants);
        $acceptedParticipants->each(function ($participant) {
            $this->assertEquals('accepted', $participant->status);
        });
    }

    /** @test */
    public function it_can_get_pending_participants()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        // Create participants with different statuses
        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $pendingParticipants = $event->pendingParticipants;
        
        $this->assertCount(1, $pendingParticipants);
        $this->assertEquals('pending', $pendingParticipants->first()->status);
    }

    /** @test */
    public function it_can_scope_for_calendar()
    {
        $calendarId1 = '550e8400-e29b-41d4-a716-446655440001';
        $calendarId2 = '550e8400-e29b-41d4-a716-446655440002';

        Event::create([
            'calendar_id' => $calendarId1,
            'title' => 'Event 1',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440003',
        ]);

        Event::create([
            'calendar_id' => $calendarId2,
            'title' => 'Event 2',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440003',
        ]);

        $eventsForCalendar1 = Event::forCalendar($calendarId1)->get();
        
        $this->assertCount(1, $eventsForCalendar1);
        $this->assertEquals($calendarId1, $eventsForCalendar1->first()->calendar_id);
    }

    /** @test */
    public function it_can_scope_for_user()
    {
        $userId1 = '550e8400-e29b-41d4-a716-446655440001';
        $userId2 = '550e8400-e29b-41d4-a716-446655440002';

        // Event created by user1
        $event1 = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440010',
            'title' => 'Event 1',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => $userId1,
        ]);

        // Event created by user2 but user1 is a participant
        $event2 = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440011',
            'title' => 'Event 2',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => $userId2,
        ]);

        // Add user1 as accepted participant to event2
        EventParticipant::create([
            'event_id' => $event2->id,
            'user_id' => $userId1,
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        // Event created by user2, user1 not involved
        Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440012',
            'title' => 'Event 3',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => $userId2,
        ]);

        $eventsForUser1 = Event::forUser($userId1)->get();
        
        $this->assertCount(2, $eventsForUser1);
        $this->assertTrue($eventsForUser1->contains('id', $event1->id));
        $this->assertTrue($eventsForUser1->contains('id', $event2->id));
    }

    /** @test */
    public function it_can_scope_in_date_range()
    {
        // Event within range
        Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Event 1',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        // Event outside range
        Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Event 2',
            'start_datetime' => '2025-06-20 10:00:00',
            'end_datetime' => '2025-06-20 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        // Event overlapping range
        Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Event 3',
            'start_datetime' => '2025-06-08 10:00:00',
            'end_datetime' => '2025-06-12 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $startDate = '2025-06-09 00:00:00';
        $endDate = '2025-06-15 23:59:59';
        
        $eventsInRange = Event::inDateRange($startDate, $endDate)->get();
        
        $this->assertCount(2, $eventsInRange);
        $this->assertTrue($eventsInRange->contains('title', 'Event 1'));
        $this->assertTrue($eventsInRange->contains('title', 'Event 3'));
    }

    /** @test */
    public function it_can_get_participant_user_ids()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $userId1 = '550e8400-e29b-41d4-a716-446655440003';
        $userId2 = '550e8400-e29b-41d4-a716-446655440004';

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userId1,
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userId2,
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        $userIds = $event->getParticipantUserIds();
        
        $this->assertCount(2, $userIds);
        $this->assertContains($userId1, $userIds);
        $this->assertContains($userId2, $userIds);
    }
}
