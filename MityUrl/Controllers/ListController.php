<?php

namespace App\Http\Controllers;

use App\User;
use App\Listpref;
use App\Url;
use App\Customlist;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;


class ListController extends Controller
{
    use ValidatesRequests;

    public function getListView($alias) {

        if (!($list = Customlist::where('alias', $alias)->first()))
            return view('notfound', ['item' => "list"]);

        $listArray = getListAsArray($alias, true, true);
        $linkArray = $listArray[0];
        $threatArray = $listArray[1];

        $listpref = $list->listpref ?? null;
        $private = $listpref->private_list ?? false;
        if ($private && Auth::guest()) return view('notfound', ['item' => "private", 'threatArray' => $threatArray]);

        if ($linkArray == "error")
            return view('notfound', ['problem' => 'retrieval']);


        $clicks = clickCustomProcessor($list, false);

        if (isset($list->user_id) && $list->user_id == Auth::id()) { $owner = true; } else { $owner = false; }

        $viewParamArray = [
            'list' => $list->urls()->orderBy('customlist_url.id', 'asc')->get(),
            'linkArray' => $linkArray,
            'threatArray' => $threatArray,
            'listurl' => "mityurl.com/l/" . $alias,
            'alias' => $alias,
            'owner' => $owner,
            'listpref' => $listpref,
            'idInfo' => "Custom List",
            'created' => "Created: " . date('M d, Y', strtotime($list->created_at)),
            'updated' => "Updated: " . date('M d, Y', strtotime($list->updated_at)),
            'clicks_today' => $clicks['today'],
            'clicks_yesterday' => $clicks['yesterday'],
            'clicks_total' => $clicks['total']
        ];


        return view('customview', $viewParamArray);

    }


    public function postCreateList(Request $request)
    {

        $this->validate($request, [
            'list' => 'required',
            'listalias' => 'min:4|max:120|unique:customlists,alias,NULL,id,deleted_at,NULL|alpha_dash'
        ], [
            'list.required' => 'The list area is required.',
            'listalias.min' => 'The alias must be at least 4 characters.',
            'listalias.max' => 'The alias may not be greater than 120 characters.',
            'listalias.unique' => 'The alias has already been taken.',
            'listalias.alpha_dash' => 'The alias may only contain letters, numbers, and dashes.',
        ]);


        $listReq = $request->input('list');
        $listArray = explode("\n" , $listReq);
        $listArray = array_unique($listArray);
        $listArrayWithScheme = array();


        foreach ($listArray as $url) {

            $possibleErrorUrl = $url;

            if (preg_match("/^(http)?s?(:\/\/)?(www.)?(mityurl.com|mity.io|mightyurl.com).*/", $url, $output_array)) {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('customError', "ERROR: Lists cannot contain mity urls. 
                    Use the original links and you'll be able to shorten them later within the list.");
            }

            $url = validateUrl($url);

            if ($url == "error") {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('customError', "ERROR: There's a problem with your link: " . $possibleErrorUrl);
            }
            if ($url != "empty") {
                array_push($listArrayWithScheme, $url);
            }
        }

        $listArrayWithScheme = array_unique($listArrayWithScheme);

        if (count($listArrayWithScheme) < 2) {
            return redirect()->back()
                ->withInput($request->input())
                ->with('customError', "ERROR: A list must contain at least 2 unique links.");
        }
        if (Auth::guest() && count($listArrayWithScheme) > 50) {
            return redirect()->back()
                ->withInput($request->input())
                ->with('customError', "ERROR: The limit is 50 links. Sign in to bump it up to 300.");
        }
        if (Auth::check() && count($listArrayWithScheme) > 300) {
            if ($listSize = User::where('id', Auth::id())->first()->list_size) {
                if (count($listArrayWithScheme) > $listSize) {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('customError', "ERROR: Your list cannot contain more than $listSize links.");
                }
            } else {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('customError', "ERROR: A list cannot contain more than 300 links.");
            }
        }



        $list = new Customlist();
        if ($user_id = Auth::id()) { $list->user_id = $user_id; }
        if (($alias = $request->input('listalias')) && Auth::check()) {
            $list->alias = $alias;
            try {
                $list->save();
            } catch ( \Illuminate\Database\QueryException $e) {
                $soft_deleted_list = Customlist::onlyTrashed()->where('alias', $alias)->first();
                if ($soft_deleted_list->user_id == Auth::id()) {
                    $soft_deleted_list->forceDelete();
                    $list->save();
                } else {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('customError', "The alias has already been taken.");
                }
            }
        } else {
            $alias = createAliasAndSave(4, $list);
        }


        foreach ($listArrayWithScheme as $url) {

            $Url = Url::firstOrCreate(['url' => $url]);

            $list->urls()->attach($Url->id);    //not efficient
        }

        $list_url = "/l/" . $alias;

        $listcode = $list->id . 'L' . substr(strtotime($list->created_at), -5);

        return redirect($list_url)->with('listcode', $listcode);
    }



    public function postDeleteList(Request $request)
    {
        $alias = $request->input('alias');

        if (!($list = Customlist::where('alias', $alias)->first())) return redirect('dashboard');

        if ($list->user_id == Auth::id()) {

            foreach ($list->urls as $url) {
                if (!count($url->links) && count($url->customlists) <= 1 && !isset($url->threat)) {     //if link not used by any other lists or short urls
                    $url->delete();
                }
            }

            $list->urls()->detach();

            if ($clicksRecord = $list->customclicks->first()) {
                $clicksRecord->delete();
            }

            if ($listpref = $list->listpref) {
                $listpref->delete();
            }

            $list->delete();
        }

        return redirect('dashboard');
    }


    public function postDeleteLink(Request $request)
    {
        $alias = $request->input('alias');
        $link_id = $request->input('linkid');

        if (!($list = Customlist::where('alias', $alias)->first())) return redirect()->back();

        if ($list->user_id != Auth::id()) return redirect()->back();

        if (!$list->urls->find($link_id)) return redirect()->back();

        $url = Url::find($link_id);

        if (!count($url->links) && count($url->customlists) <= 1 && !isset($url->threat)) {     //if link not used by any other lists or short urls
            $url->delete();
        }

        $list->urls()->detach($link_id);


        return redirect()->back();

    }



    public function postUpdateList(Request $request)
    {

        $this->validate($request, ['list' => 'required',], ['list.required' => 'The list area is required.']);

        $alias = $request->input('alias');

        if (!($list = Customlist::where('alias', $alias)->first())) return redirect()->back();

        if ($list->user_id != Auth::id()) return redirect()->back();

        $listArrayOld = unserialize($request->input('listarray'));  // 'id' => 'url'
        $listArrayOldFlipped = array_flip($listArrayOld);   // 'url' => 'id'
        $listReq = $request->input('list');
        $listArray = explode("\n" , $listReq);
        $listArray = array_unique($listArray);
        $listArrayWithScheme = array();
        $link_ids = array();


        foreach ($listArray as $url) {

            if (isset($listArrayOldFlipped[$url])) {

                array_push($link_ids, $listArrayOldFlipped[$url]);

            } else {
                $possibleErrorUrl = $url;

                if (preg_match("/^(http)?s?(:\/\/)?(www.)?(mityurl.com|mity.io|mightyurl.com).*/", $url, $output_array)) {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('customError', "ERROR: Lists cannot contain mity urls.");
                }

                $url = validateUrl($url);

                if ($url == "error") {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('customError', "ERROR: There's a problem with your link: " . $possibleErrorUrl);
                }
                if ($url != "empty") {
                    array_push($listArrayWithScheme, $url);


                    $Url = Url::firstOrCreate(['url' => $url]);

                    array_push($link_ids, $Url->id);
                }


            }
        }

        $link_ids = array_unique($link_ids);
        $listArrayWithScheme = array_unique($listArrayWithScheme);

        if (count($listArrayWithScheme) < 2) {
            return redirect()->back()
                ->withInput($request->input())
                ->with('customError', "ERROR: A list must contain at least 2 unique links.");
        }
        if (count($listArrayWithScheme) > 300) {
            if ($listSize = User::where('id', Auth::id())->first()->list_size) {
                if (count($listArrayWithScheme) > $listSize) {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('customError', "ERROR: Your list cannot contain more than $listSize links.");
                }
            } else {
                return redirect()->back()
                    ->withInput($request->input())
                    ->with('customError', "ERROR: A list cannot contain more than 300 links.");
            }
        }



        $list->urls()->detach();
        $list->urls()->attach($link_ids);


        $detachedArray = array_intersect($listArrayOld, array_diff($listArrayOld, $listArrayWithScheme));

        foreach ($detachedArray as $detUrl) {
            $url = Url::find($listArrayOldFlipped[$detUrl]);
            if (!count($url->links) && !count($url->customlists) && !isset($url->threat)) {     //if link not used by any other lists or short urls
                $url->delete();
            }
        }


        return redirect()->back();

    }


    public function postListPref(Request $request)
    {
        $this->validate($request, ['title' => 'max:100'], ['title.max' => 'The title may not be greater than 100 characters.']);

        $alias = $request->input('alias');

        if (!($list = Customlist::where('alias', $alias)->first())) return redirect('dashboard');

        if ($list->user_id != Auth::id()) return redirect()->back();

        $title = $request->input('title');
        $privateList = $request->input('privatelist');
        $privateClicks = $request->input('privateclicks');

        $listpref = Listpref::firstOrNew(['customlist_id' => $list->id]);

        $listpref->title = $title;

        if (isset($privateList)) {
            $listpref->private_list = true;
            $listpref->private_clickstats = true;
        } else if (isset($privateClicks)) {
            $listpref->private_list = false;
            $listpref->private_clickstats = true;
        } else {
            $listpref->private_list = false;
            $listpref->private_clickstats = false;
        }


        $listpref->save();

        return redirect()->back();

    }


    public function getListLinkByID($alias, $link_id)
    {
        if (!($url = Url::find($link_id))) return view('notfound', ['item' => 'link']);

        if ($url->threat) return view('notfound', ['item' => 'link']);

        if (!($list = Customlist::where('alias', $alias)->first())) return view('notfound', ['item' => 'list']);

        if (!$list->user_id) return view('notfound', ['item' => 'link']);

        if (!$list->urls->find($link_id)) return view('notfound', ['item' => 'link']);

        clickCustomProcessor($list);

        return redirect($url->url);
    }





    /*public function postQuickDeleteList(Request $request)
    {



    }*/


}