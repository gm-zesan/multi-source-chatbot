<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactForm;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ContactFormController extends Controller
{


    public function index(Request $request)
    {
        if($request->ajax()){
            $contactForms = ContactForm::get()->all();
            return DataTables::of($contactForms)
                ->addIndexColumn()
                ->addColumn('important_status', function ($row) {
                    return ['id' => $row->id, 'important_status' => $row->important_status];
                })
                ->addColumn('action-btn', function ($row) {
                    return ['id' => $row->id, 'read_status' => $row->read_status, 'message' => $row->message];
                })
                ->rawColumns(['action-btn'])
                ->make(true);
        }
        return view('admin.contact-messages.index');
    }


    function read(Request $request)
    {
        $contactForm = ContactForm::find($request->id);
        $contactForm->read_status = 1;
        $contactForm->save();
        return response()->json(['success' => 'Message read successfully']);
    }


    function important(Request $request)
    {
        $contactForm = ContactForm::find($request->id);
        $contactForm->important_status = $request->important_status;
        $contactForm->save();
        return response()->json([
            'success' => 'Message marked as important',
            'important_status' => $request->important_status
        ]);
    }

    /**
     * Store a newly created contact message from the public website.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        $contactForm = ContactForm::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? 'Website Inquiry',
            'message' => $validated['message'],
            'read_status' => 0,
            'important_status' => 0,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting Entrepreneurs Automation! Our team will get back to you shortly.',
                'id' => $contactForm->id
            ]);
        }

        return redirect()->back()->with('success', 'Thank you! Your message has been sent successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        $contactForm = ContactForm::find($id);
        $contactForm->delete();
        return redirect()->back()->with('success','Message deleted successfully');
    }
}
