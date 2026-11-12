<?php

namespace App\Http\Repositories\Frontend\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface MenuRepositoryInterface
{

    public function items(): Collection;

    /**
     * @param int $id
     * @return Model|null
     */
//    public function findById(int $id): ?Model;
}
