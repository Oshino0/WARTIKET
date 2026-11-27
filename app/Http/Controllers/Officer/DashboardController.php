<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('officer.dashboard');
    }
}
