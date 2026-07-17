<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'workspace_id' => $this->workspace_id,
            'workspace_name' => $this->whenLoaded('workspace', fn () => $this->workspace->name),
        ];
    }
}
