<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ContactController extends Controller
{
    private const path = 'frontend.contact.';

    public function index(): View
    {

        return view(self::path. 'index');
    }


}
