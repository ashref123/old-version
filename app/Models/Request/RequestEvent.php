<?php

namespace App\Models\Request;

use App\Base\Uuid\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RequestEvent extends Model
{
    use UuidModel;

    protected $table = 'request_events';

    protected $fillable = [
        'request_id',
        'event',
        'actor_type',
        'actor_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function requestDetail()
    {
        return $this->belongsTo(Request::class, 'request_id', 'id');
    }
}
