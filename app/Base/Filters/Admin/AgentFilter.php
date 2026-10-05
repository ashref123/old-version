<?php

namespace App\Base\Filters\Admin;

use App\Base\Libraries\QueryFilter\FilterContract;

class AgentFilter implements FilterContract
{
    public function filters()
    {
        return [
            'status',
            'search',
        ];
    }

    public function defaultSort()
    {
        return '-created_at';
    }

    public function status($builder, $value = 0) 
    {
		$builder->where('active', $value);
    }  
    
    public function search($builder, $value=null) {
        $builder->where('first_name','like',"%".$value."%");
    }

}
