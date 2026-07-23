<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return view('passenger.home');
    }
}