<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PassengerBookingController extends Controller
{
    public function index()
    {
        return view('passenger.booking.index');
    }
}