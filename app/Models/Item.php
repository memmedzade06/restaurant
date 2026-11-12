<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table='items';

//    protected $fillable = [
//        'picture','title','price','description'
//    ];

//    public function comment(){
//        return $this->hasMany(Comment::class);
//    }
}
