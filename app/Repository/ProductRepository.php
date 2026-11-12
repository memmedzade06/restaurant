<?php


namespace App\Repository;

use App\Models\Product;
use App\Repository\IProductRepository;

class ProductRepository implements IProductRepository {

    public function getAllProducts(){

        return Product::all();

    }



}



?>
