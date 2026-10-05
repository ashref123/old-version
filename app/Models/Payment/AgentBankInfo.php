<?php

namespace App\Models\Payment;

use Carbon\Carbon;
use App\Models\Admin\Agents;
use App\Models\Method;
use App\Models\Payment\BankInfo;
use App\Models\Traits\HasActive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Uuid\UuidModel;

class AgentBankInfo extends Model
{
    use HasActive, UuidModel;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'agents_bank_info';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'agent_id', 'method_id','field_id','value'
    ];

    protected $appends = [
    ];

    public $includes = [
        'agent'
    ];

    /**
     * Relationship: A document belongs to a driver.
     */
    public function agent()
    {
        return $this->belongsTo(Agents::class, 'agent_id', 'id')->withTrashed();
    }



    /**
    * The Document that the bankInfo belongs to.
    * @tested
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsTo
    */
    public function method()
    {
        return $this->belongsTo(Method::class, 'method_id', 'id');
    }

    /**
    * Get formated and converted timezone of user's created at.
    *
    * @param string $value
    * @return string
    */
    public function getConvertedCreatedAtAttribute()
    {
        if ($this->created_at==null||!auth()->user()) {
            return null;
        }
        if(auth()->user()){
            $timezone = auth()->user()->timezone?:config('app.timezone');
        }else{
            $timezone = config('app.timezone');
        }
        return Carbon::parse($this->created_at)->setTimezone($timezone)->format('jS M h:i A');
    }
    /**
    * Get formated and converted timezone of user's created at.
    *
    * @param string $value
    * @return string
    */
    public function getConvertedUpdatedAtAttribute()
    {
        if ($this->updated_at==null||!auth()->user()) {
            return null;
        }
       if(auth()->user()){
            $timezone = auth()->user()->timezone?:config('app.timezone');
        }else{
            $timezone = config('app.timezone');
        }
        return Carbon::parse($this->updated_at)->setTimezone($timezone)->format('jS M h:i A');
    }
}
