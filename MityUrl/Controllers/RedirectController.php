<?php

namespace App\Http\Controllers;

use App\Link;
use App\Click;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class RedirectController extends Controller
{
    public function getRedirect($alias)
    {
        if (!($link = Link::where('alias', $alias)->first()))
            return view('notfound', ['item' => "link"]);

        $threat = $link->url->threat;

        if (isset($threat) && (strtotime("now") - strtotime($link->url->updated_at) > 10*60)) {      // && last scanned > 10min ago
            $threat = SBscan($link->url->url);
        }
        if (isset($threat)) {
            return redirect("https://mityurl.com/" . $alias . "/preview");
        }

        clickProcessor($link);

        $url = $link->url->url;

        return redirect($url);

    }


    public function getPreview($alias)
    {
        if ($link = Link::where('alias', $alias)->first()) {

            $threat = $link->url->threat;

            if (isset($threat) && (strtotime("now") - strtotime($link->url->updated_at) > 10*60)) {      // && last scanned > 10min ago
                $threat = SBscan($link->url->url);
            }

            $clicks = clickProcessor($link, false);

            if (isset($link->user_id) && $link->user_id == Auth::id()) { $owner = true; } else { $owner = false; }

            $viewParamArray = [
                'oldurl' => $link->url->url,
                'newurl' => "mityurl.com/" . $alias,
                'alias' => $alias,
                'owner' => $owner,
                'threat' => $threat,
                'created' => "Created: " . date('M d, Y', strtotime($link->created_at)),
                'clicks_today' => $clicks['today'],
                'clicks_yesterday' => $clicks['yesterday'],
                'clicks_total' => $clicks['total']
            ];

            return view('view', $viewParamArray);
        }

        return view('notfound', ['item' => "link"]);

    }

}