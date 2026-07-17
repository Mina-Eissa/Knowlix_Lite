<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookEventResource;
use App\Models\WebhookEvent;

class WebhookEventController extends Controller
{
    public function index()
    {
        return WebhookEventResource::collection(
            WebhookEvent::latest()->paginate(20)
        );
    }
}
