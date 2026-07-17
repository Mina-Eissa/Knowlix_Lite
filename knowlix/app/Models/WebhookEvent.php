<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookEvent extends Model
{
    use BelongsToWorkspace,HasFactory;

    protected $fillable = ['workspace_id', 'event_id', 'type', 'payload', 'status', 'attempts', 'next_attempt_at', 'delivered_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', WebhookEventStatus::Pending);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', WebhookEventStatus::Failed);
    }
}
