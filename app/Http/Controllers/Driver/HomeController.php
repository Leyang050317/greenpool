<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return view('driver.home');
    }
}