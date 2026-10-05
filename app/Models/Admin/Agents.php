<?php

namespace App\Models\Admin;
use App\Models\User;
use App\Base\Uuid\UuidModel;
use App\Models\Traits\HasActive;
use App\Models\Admin\ServiceLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payment\AgentWallet;
use App\Models\Payment\AgentWalletHistory;
use App\Models\Payment\AgentBankInfo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Agents extends Model
{
    use HasFactory,UuidModel;

    protected $table = 'agents';

    protected $fillable = [
        'first_name', 'last_name', 'address', 'country','state',
        'city','pincode','email','mobile','user_id','created_by','service_location_id','active','category_type'
   ];

    protected $appends = [
        'profile_picture','service_location_name','user_status'
    ];

   
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function serviceLocationDetail()
    {
        return $this->belongsTo(ServiceLocation::class, 'service_location_id', 'id')->withTrashed();
    }


    public function getUserNameAttribute()
    {
       if ($this->user()->exists()) {
            return $this->user->name;
        }
        return null;
    }
    public function getUserStatusAttribute()
    {
       if ($this->user()->exists()) {
            return $this->user->active;
        }
        return null;
    }
    /**
    * Get Service location's name
    *
    * @return string
    */
    public function getServiceLocationNameAttribute()
    {
       if ($this->serviceLocationDetail()->exists()) {
            return $this->serviceLocationDetail->name;
        }
        return null;
    }
    /**
    * Get profile picture
    *
    * @return string
    */
    public function getProfilePictureAttribute()
    {
        if (!$this->user()->exists()) {
            return null;
        }
        return $this->user->profile_picture;
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
        $timezone = auth()->user()->timezone?:config('app.timezone');
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
        $timezone = auth()->user()->timezone?:config('app.timezone');
        return Carbon::parse($this->updated_at)->setTimezone($timezone)->format('jS M h:i A');
    }    

     public function agentWalletHistory()
    {
        return $this->hasMany(AgentWalletHistory::class, 'user_id', 'id');
    }

    public function agentWallet()
    {
        return $this->hasOne(AgentWallet::class, 'user_id', 'id');
    }
     public function bankInfo()
    {
        return $this->hasMany(AgentBankInfo::class, 'agent_id', 'id');
    }
    
    public function withdrawalRequestsHistory()
    {
        return $this->hasMany(WalletWithdrawalRequest::class, 'agent_id', 'id');
    }
}
