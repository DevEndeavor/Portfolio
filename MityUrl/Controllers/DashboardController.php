<?php

namespace App\Http\Controllers;

use App\Ytuser;
use App\Ytplaylist;
use App\Ytlist;
use App\Customlist;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;

class DashboardController extends Controller
{
    public function getDashboard(Request $request) {

        $user = Auth::user();

        $tab = $request->query('tab');

        $viewParamArray = [
            'name' => $user->name,
            'email' => $user->email,
            'links' => $user->links()->orderBy('created_at', 'desc')->get(),
            'ytlists' => $user->ytlists()->orderBy('created_at', 'desc')->get(),
            'customlists' => $user->customlists()->orderBy('created_at', 'desc')->get(),
            'tab' => $tab
        ];


        return view('dashboard', $viewParamArray);

    }


    public function postRequestOwnership(Request $request)
    {
        $user_id = Auth::id();

        $code = $request->input('code');
        $code = preg_replace('/\s+/', '', $code);

        if (preg_match("/^[0-9]+(Y)[0-9]{5}$/", $code, $output_array)) {

            $code = explode('Y', $code);
            if (!($list = Ytlist::find($code[0]))) {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('claimError', "ERROR: That list code is invalid.");
            }


        } else if (preg_match("/^[0-9]+(L)[0-9]{5}$/", $code, $output_array)) {

            $code = explode('L', $code);
            if (!($list = Customlist::find($code[0]))) {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('claimError', "ERROR: That list code is invalid.");
            }

        } else {
            return redirect()->back()
                ->withInput($request->input())
                ->with('claimError', "ERROR: That list code is invalid.");
        }


        if (isset($list->user_id)) {
            if ($list->user_id == Auth::id() && $code[1] == (substr(strtotime($list->created_at), -5))) {   // redundant but necessary
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('claimSuccess', "You already own this list.");
            } else {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('claimError', "ERROR: That list code is invalid.");
            }
        }

        if($code[1] == (substr(strtotime($list->created_at), -5))) {
            $list->user_id = $user_id;
            $list->save();
        } else {
            return redirect()->back()
                ->withInput($request->input())
                ->with('claimError', "ERROR: That list code is invalid.");
        }


        {
            return redirect()->back()
                ->with('claimSuccess', "SUCCESS! Check your dashboard for your new list.");
        }
    }

}