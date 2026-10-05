<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Nicolaslopezj\Searchable\SearchableTrait;

class RequestEnquiry extends Model
{

    use SearchableTrait;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'request_enquiries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id','pick_lat','pick_lng','drop_lat','drop_lng','pick_address','drop_address','converted_as_ride','is_cancelled','dispatcher_id'];

    /**
     * The relationships that can be loaded with query string filtering includes.
     *
     * @var array
     */
    public $includes = [
        'userDetail'

    ];

     protected $searchable = [
        /**
         * Columns and their priority in search results.
         * Columns with higher values are more important.
         * Columns with equal values have equal importance.
         *
         * @var array
         */
        'columns' => [
            'users.name' => 20,
             'users.mobile' => 20,
        ],'joins' => [
            'users' => ['request_enquiries.user_id','users.id'],
        ]

    ];

    /**
     * The request that the meta belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function userDetail()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }
}
