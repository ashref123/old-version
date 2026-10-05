<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PopularCity extends Model
{
    protected $table = 'popular_cities';

    protected $fillable = [
        'zone_id',
        'ride_type',
        'city_search',
        'lat',
        'lng',
        'image',
        'status',
        'shortcode',
        'description',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    public function uploadPath()
    {
        return config('base.cities.upload.images.path');
    }

    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url('storage/uploads/cities/images/' . basename($this->image));
    }
}
