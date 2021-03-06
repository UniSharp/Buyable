<?php

namespace UniSharp\Buyable\Models;

use Illuminate\Database\Eloquent\Model;
use UniSharp\Buyable\Contracts\ProductUnitContract;

class Spec extends Model implements ProductUnitContract
{
    protected $guarded = [];
    protected $casts = [
        'price' => 'integer'
    ];

    public function buyable()
    {
        return $this->morphTo('buyable');
    }
}
