<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceTransaction extends Model
{
    protected $fillable = [
        'order_id', 'type', 'amount', 'description', 'transaction_date', 'created_by',
    ];

    protected $casts = ['transaction_date' => 'date'];
}