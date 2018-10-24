<?php

namespace App\Http\Controllers;

use App\Click;
use Illuminate\Support\Facades\Auth;
use App\Url;
use App\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

class ShortenerController extends Controller
{

    use ValidatesRequests;

    public function postShortenUrl(Request $request)
    {

        $this->validate($request, [
            'shortener' => 'bail|required',
            'alias' => 'min:4|max:120|unique:links|alpha_dash'
        ]);

        $url = $request->input('shortener');

        if (preg_match("/^(http)?s?(:\/\/)?(www.)?(mityurl.com|mity.io|mightyurl.com).*/", $url, $output_array)) {
            return redirect()->back()
                ->withInput($request->input())
                ->with('shortenerError', "ERROR: That's already a mity url.");
        }

        $url = validateUrl($url);   // app/Http/helpers.php

        if ($url == "error" || $url == "empty") {
            return redirect()->back()
                ->withInput($request->input())
                ->with('shortenerError', "ERROR: There's a problem with your link.");
        }


        $Url = Url::firstOrCreate(['url' => $url]);

        $link = new Link();

        if ($user_id = Auth::id()) $link->user_id = $user_id;

        if($alias = $request->input('alias')) {
            $link->alias = $alias;
        } else {
            $alias = createAliasAndSave(4, $link);
        }

        $link->url_id = $Url->id;
        $link->save();


        $preview = $alias . "/preview";

        return redirect($preview);

    }


    public function postDeleteShortUrl(Request $request)
    {
        $alias = $request->input('alias');

        if (!($link = Link::where('alias', $alias)->first())) return redirect('dashboard');

        if ($link->user_id != Auth::id()) return redirect()->back();


        $Url = $link->url;

        if (count($Url->links) <= 1 && !count($Url->customlists) && !isset($Url->threat)) {     //if link not used by any other short urls or lists
            $Url->delete();
        }

        $link->delete();


        return redirect('dashboard');

    }


}