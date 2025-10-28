<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PdfToolsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:view-pdf-tools');
    }

    /**
     * Display a listing of PDF tools.
     */
    public function index()
    {
        return view('pdf-tools.index');
    }

    /**
     * Show the form for creating a new PDF.
     */
    public function create()
    {
        $this->middleware('permission:create-pdf');
        return view('pdf-tools.create');
    }

    /**
     * Generate a PDF.
     */
    public function generate(Request $request)
    {
        $this->middleware('permission:create-pdf');
        // TODO: Implement PDF generation logic
        return redirect()->route('pdf-tools.index')->with('success', 'PDF generated successfully.');
    }
}