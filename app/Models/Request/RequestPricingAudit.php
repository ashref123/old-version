<?php

namespace App\Models\Request;

use App\Base\Uuid\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RequestPricingAudit extends Model
{
    use UuidModel;

    protected $table = 'request_pricing_audits';

    protected $fillable = [
        'request_id',
        'phase',
        'audit',
    ];

    protected $casts = [
        'audit' => 'array',
    ];

    public function requestDetail()
    {
        return $this->belongsTo(Request::class, 'request_id', 'id');
    }
}
