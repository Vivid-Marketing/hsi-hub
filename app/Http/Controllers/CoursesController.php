<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CoursesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:view-courses');
    }

    /**
     * Display a listing of courses.
     */
    public function index()
    {
        return view('courses.index');
    }

    /**
     * Show the form for creating a new course.
     */
    public function create()
    {
        $this->middleware('permission:create-courses');
        return view('courses.create');
    }

    /**
     * Store a newly created course.
     */
    public function store(Request $request)
    {
        $this->middleware('permission:create-courses');
        // TODO: Implement course creation logic
        return redirect()->route('courses.index')->with('success', 'Course created successfully.');
    }

    /**
     * Display the specified course.
     */
    public function show($id)
    {
        return view('courses.show', compact('id'));
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit($id)
    {
        $this->middleware('permission:edit-courses');
        return view('courses.edit', compact('id'));
    }

    /**
     * Update the specified course.
     */
    public function update(Request $request, $id)
    {
        $this->middleware('permission:edit-courses');
        // TODO: Implement course update logic
        return redirect()->route('courses.index')->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified course.
     */
    public function destroy($id)
    {
        $this->middleware('permission:delete-courses');
        // TODO: Implement course deletion logic
        return redirect()->route('courses.index')->with('success', 'Course deleted successfully.');
    }
}