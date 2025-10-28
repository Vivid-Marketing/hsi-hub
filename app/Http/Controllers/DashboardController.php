<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // Middleware is now handled at the route level
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        return view('dashboard');
    }
}