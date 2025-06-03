<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class EventParticipantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-06-03 12:00:00');
    }

    /** @test */
    public function it_can_create_an_event_participant()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $participantData = [
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'assigned_calendar_id' => '550e8400-e29b-41d4-a716-446655440004',
            'role' => 'attendee'
        ];

        $participant = EventParticipant::create($participantData);

        $this->assertInstanceOf(EventParticipant::class, $participant);
        $this->assertEquals($participantData['event_id'], $participant->event_id);
        $this->assertEquals($participantData['user_id'], $participant->user_id);
        $this->assertEquals($participantData['status'], $participant->status);
        $this->assertEquals($participantData['role'], $participant->role);
        $this->assertDatabaseHas('event_participants', $participantData);
    }

    /** @test */
    public function it_has_composite_primary_key()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $participant = new EventParticipant();
        
        $this->assertEquals(['event_id', 'user_id'], $participant->getKeyName());
        $this->assertFalse($participant->getIncrementing());
        $this->assertEquals('string', $participant->getKeyType());
    }

    /** @test */
    public function it_belongs_to_event()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $participant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $this->assertInstanceOf(Event::class, $participant->event);
        $this->assertEquals($event->id, $participant->event->id);
    }

    /** @test */
    public function it_can_scope_accepted_participants()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

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

        $acceptedParticipants = EventParticipant::accepted()->get();
        
        $this->assertCount(2, $acceptedParticipants);
        $acceptedParticipants->each(function ($participant) {
            $this->assertEquals('accepted', $participant->status);
        });
    }

    /** @test */
    public function it_can_scope_pending_participants()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

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

        $pendingParticipants = EventParticipant::pending()->get();
        
        $this->assertCount(1, $pendingParticipants);
        $this->assertEquals('pending', $pendingParticipants->first()->status);
    }

    /** @test */
    public function it_can_scope_organizers()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'organizer'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $organizers = EventParticipant::organizers()->get();
        
        $this->assertCount(1, $organizers);
        $this->assertEquals('organizer', $organizers->first()->role);
    }

    /** @test */
    public function it_can_scope_attendees()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'organizer'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440005',
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        $attendees = EventParticipant::attendees()->get();
        
        $this->assertCount(2, $attendees);
        $attendees->each(function ($participant) {
            $this->assertEquals('attendee', $participant->role);
        });
    }

    /** @test */
    public function it_can_check_if_participant_has_accepted()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $acceptedParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $pendingParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        $this->assertTrue($acceptedParticipant->hasAccepted());
        $this->assertFalse($pendingParticipant->hasAccepted());
    }

    /** @test */
    public function it_can_check_if_participant_has_declined()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $declinedParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'declined',
            'role' => 'attendee'
        ]);

        $acceptedParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $this->assertTrue($declinedParticipant->hasDeclined());
        $this->assertFalse($acceptedParticipant->hasDeclined());
    }

    /** @test */
    public function it_can_check_if_invitation_is_pending()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $pendingParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'pending',
            'role' => 'attendee'
        ]);

        $acceptedParticipant = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $this->assertTrue($pendingParticipant->isPending());
        $this->assertFalse($acceptedParticipant->isPending());
    }

    /** @test */
    public function it_can_check_if_participant_is_organizer()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $organizer = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'organizer'
        ]);

        $attendee = EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440004',
            'status' => 'accepted',
            'role' => 'attendee'
        ]);

        $this->assertTrue($organizer->isOrganizer());
        $this->assertFalse($attendee->isOrganizer());
    }

    /** @test */
    public function it_cannot_create_duplicate_participants()
    {
        $event = Event::create([
            'calendar_id' => '550e8400-e29b-41d4-a716-446655440001',
            'title' => 'Test Event',
            'start_datetime' => '2025-06-10 10:00:00',
            'end_datetime' => '2025-06-10 11:00:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'creator_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $participantData = [
            'event_id' => $event->id,
            'user_id' => '550e8400-e29b-41d4-a716-446655440003',
            'status' => 'accepted',
            'role' => 'attendee'
        ];

        // Create first participant
        EventParticipant::create($participantData);

        // Attempt to create duplicate should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        EventParticipant::create($participantData);
    }
}
