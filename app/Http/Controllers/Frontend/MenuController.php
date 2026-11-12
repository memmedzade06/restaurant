<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;



use App\Http\Services\Frontend\Contracts\MenuServiceInterface;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Session;

class MenuController extends Controller
{
    private const path = 'frontend.menu.';


    public function __construct(private readonly MenuServiceInterface $menuService)
    {

    }

    /**
     * @return View
     */
    public function index(): View
    {

        $items = $this->menuService->items();


        return view(self::path . 'index',compact('items'));
    }


    public function get_items_by_category(Request $request)
    {

        $data=Item::where('category_id',$request->data)->orderBy('price')->get();


        return json_encode($data);


    }



}
