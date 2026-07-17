<?php

namespace App\Enums;

enum WebhookEventStatus:string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
