<?php

namespace App\Http\Services\Frontend;

use App\Http\Repositories\Frontend\Contracts\MenuRepositoryInterface;
use App\Http\Services\Frontend\Contracts\MenuServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class MenuService implements MenuServiceInterface
{

    public function __construct(private readonly MenuRepositoryInterface $menuRepository)
    {
    }

    /**
     * @return Collection
     */
    public function items(): Collection
    {
        return $this->menuRepository->items();
    }


//    public function childCategories(int $categoryId): Collection
//    {
////        return $this->categoryRepository->childCategories($categoryId);
//    }
}
