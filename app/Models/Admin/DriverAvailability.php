<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DriverAvailability extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_availabilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['driver_id','is_online','online_at','offline_at','duration'];

    /**
     * Always expose a non-negative duration.
     *
     * @param  mixed  $value
     * @return float
     */
    public function getDurationAttribute($value)
    {
        return max(0, round((float) $value, 2));
    }


    /**
     * The relationships that can be loaded with query string filtering includes.
     *
     * @var array
     */
    public $includes = [
        'driver'
    ];

    /**
    * The driver that the uploaded data belongs to.
    * @tested
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id')->withTrashed();
    }

    public function getConvertedOnlineAtAttribute()
    {
        if ($this->online_at==null||!auth()->user()) {
            return null;
        }
        $timezone = auth()->user()->timezone?:config('app.timezone');
        return Carbon::parse($this->online_at)->setTimezone($timezone)->format('jS M h:i A');
    }
    public function getConvertedOfflineAtAttribute()
    {
        if ($this->offline_at==null||!auth()->user()) {
            return null;
        }
        $timezone = auth()->user()->timezone?:config('app.timezone');
        return Carbon::parse($this->offline_at)->setTimezone($timezone)->format('jS M h:i A');
    }
    public function getConvertedDurationAtAttribute()
    {
        $duration = (int) round($this->duration);
        $hours = intdiv($duration, 60). 'hr'." ".($duration % 60)."mins";
        

        return $hours;
    }
}
