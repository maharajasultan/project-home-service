<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name', 'address', 'description', 'email',
        'instagram', 'facebook', 'tiktok', 'whatsapp',
    ];
}