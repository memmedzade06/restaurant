<?php

namespace App\Http\Services\Frontend\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface MenuServiceInterface
{
    /**
     * @return Collection
     */
    public function items(): Collection;


//    public function childCategories(int $categoryId): Collection;
}
