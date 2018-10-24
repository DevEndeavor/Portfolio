<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;

class ContactController extends Controller
{
    use ValidatesRequests;

    public function contact(Request $request) {

        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required'
        ]);

        $name = $request->input('name');
        $email = $request->input('email');
        $message = $request->input('message');
        $registeredUser = "";

        if (Auth::check()) $registeredUser = "Registered (" . Auth::user()->email . ")";

        $emailMessage = $name . "\n" . $email . "\n" . $registeredUser . "\n\n" . $message;

        $headers = "Reply-to: $email";

        mail("my.dev.endeavor@gmail.com", "mityurl - user message", $emailMessage, $headers);

        return redirect('contact-us')->with(['name' => $name, 'email' => $email]);
    }



}