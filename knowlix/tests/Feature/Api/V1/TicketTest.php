<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->admin = User::factory()->for($this->workspace)->create(['role' => 'admin']);
        $this->member = User::factory()->for($this->workspace)->create(['role' => 'member']);

        \Illuminate\Support\Facades\Http::fake();
    }

    // --- CRUD: happy path ---

    public function test_member_can_create_ticket(): void
    {
        $response = $this->actingAs($this->member)->postJson('/api/v1/tickets', [
            'subject' => 'Login broken',
            'body' => 'Getting a 500 error on the login page.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'open');
        $response->assertJsonPath('data.requester.id', $this->member->id);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Login broken',
            'requester_id' => $this->member->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'open',
        ]);
    }

    public function test_member_can_view_ticket_list(): void
    {
        Ticket::factory()->count(3)->for($this->workspace)->create([
            'requester_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)->getJson('/api/v1/tickets');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_requester_can_update_own_open_ticket(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->member)->patchJson("/api/v1/tickets/{$ticket->id}", [
            'subject' => 'Updated subject',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'subject' => 'Updated subject']);
    }

    // --- CRUD: role-denial (403) ---

    public function test_requester_cannot_update_ticket_once_in_progress(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'status' => TicketStatus::Pending,
        ]);

        $response = $this->actingAs($this->member)->patchJson("/api/v1/tickets/{$ticket->id}", [
            'subject' => 'Trying to sneak an edit in',
        ]);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_assign_ticket(): void
    {
        $otherMember = User::factory()->for($this->workspace)->create(['role' => 'member']);
        $ticket = Ticket::factory()->for($this->workspace)->create();

        $response = $this->actingAs($this->member)->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assignee_id' => $otherMember->id,
        ]);

        $response->assertForbidden();
    }

    public function test_member_cannot_delete_ticket_once_in_progress(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'status' => TicketStatus::Pending,
        ]);

        $response = $this->actingAs($this->member)->deleteJson("/api/v1/tickets/{$ticket->id}");

        $response->assertForbidden();
    }

    // --- CRUD: validation (422) ---

    public function test_create_ticket_requires_subject_and_body(): void
    {
        $response = $this->actingAs($this->member)->postJson('/api/v1/tickets', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['subject', 'body']);
    }

    public function test_create_ticket_rejects_invalid_priority(): void
    {
        $response = $this->actingAs($this->member)->postJson('/api/v1/tickets', [
            'subject' => 'Test',
            'body' => 'Test body',
            'priority' => 'super-urgent',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['priority']);
    }

    // --- CRUD: tenant isolation (404) ---

    public function test_user_cannot_view_ticket_from_another_workspace(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherTicket = Ticket::factory()->for($otherWorkspace)->create();

        $response = $this->actingAs($this->member)->getJson("/api/v1/tickets/{$otherTicket->id}");

        $response->assertNotFound();
    }

    public function test_assign_rejects_assignee_from_another_workspace(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $outsider = User::factory()->for($otherWorkspace)->create();
        $ticket = Ticket::factory()->for($this->workspace)->create();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assignee_id' => $outsider->id,
        ]);

        $response->assertUnprocessable();
    }

    // --- Assignment ---

    public function test_admin_can_assign_ticket_to_workspace_agent(): void
    {
        $agent = User::factory()->for($this->workspace)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($this->workspace)->create([
                'requester_id' => $this->member->id,
            ]);

        echo "Assigning ticket {$ticket->id} to agent {$agent->id} in workspace {$this->workspace->id}\n";

        $response = $this->actingAs($this->admin)->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assignee_id' => $agent->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assignee.id', $agent->id);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assignee_id' => $agent->id]);
    }

    // --- Status transitions ---

    public function test_valid_status_transition_open_to_pending(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'assignee_id' => $this->admin->id,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'pending',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'assignee_id' => $this->admin->id,
            'status' => TicketStatus::Pending,
        ]);

        // pending -> closed skips resolved, should fail
        $response = $this->actingAs($this->admin)->patchJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'pending']);
    }

    public function test_reopen_transition_from_resolved_to_open(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'assignee_id' => $this->admin->id,
            'status' => TicketStatus::Resolved,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'open',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'open');
    }

    public function test_creating_ticket_fires_ticket_created_webhook_event(): void
    {
        $response = $this->actingAs($this->member)->postJson('/api/v1/tickets', [
            'subject' => 'Login broken',
            'body' => 'Getting a 500 error on the login page.',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('webhook_events', [
            'type' => 'ticket.created',
            'workspace_id' => $this->workspace->id,
        ]);
    }
    public function test_status_transition_fires_ticket_status_changed_webhook_event(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'assignee_id' => $this->admin->id,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'pending',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'type' => 'ticket.status_changed',
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_invalid_status_transition_does_not_create_webhook_event(): void
    {
        $ticket = Ticket::factory()->for($this->workspace)->create([
            'requester_id' => $this->member->id,
            'assignee_id' => $this->admin->id,
            'status' => TicketStatus::Pending,
        ]);

        $countBefore = \App\Models\WebhookEvent::count();

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/tickets/{$ticket->id}/status", [
            'status' => 'closed', // invalid: skips resolved
        ]);

        $response->assertUnprocessable();
        $this->assertEquals($countBefore, \App\Models\WebhookEvent::count());
    }
}
