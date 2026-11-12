<?php

namespace App\Http\Repositories\Frontend;

use App\Http\Repositories\Frontend\Contracts\MenuRepositoryInterface;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Item;

class MenuRepository implements MenuRepositoryInterface
{

    public function items(): Collection
    {

        return Item::query()->where(
            [
                'status' => 1
            ]
        )->get();

    }


//    public function findById(int $id): ?Model
//    {
//        return Member::query()->find($id);
//    }
}
