<?php

namespace App\Http\Controllers;

use App\Listpref;
use App\Ytuser;
use App\Ytplaylist;
use App\Ytlist;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Validator;

class YoutubeListController extends Controller
{
    use ValidatesRequests;

    public function getYoutubeListView($alias) {

        if (!($list = Ytlist::where('alias', $alias)->first()))
            return view('notfound', ['item' => "list"]);


        $listpref = $list->listpref ?? null;
        $private = $listpref->private_list ?? false;
        if ($private && Auth::guest()) return view('notfound', ['item' => "private"]);

        $linkArray = getYoutubeListAsArray($alias, true);

        if ($linkArray == "error" /*|| !count($linkArray)*/)    // allow youtube lists to become empty
            return view('notfound', ['problem' => 'retrieval']);


        $list->updated_at = date('Y-m-d h:i:s', strtotime("now"));
        $list->save();

        if ($list->ytplaylist->playlist_type == 0) {
            $idInfo = "Youtube Channel";
            $list_sorted = $list->ytplaylist->ytlinks()->orderBy('ytlinks.published_at', 'desc')->orderBy('ytlink_ytplaylist.id', 'desc')->get();
        } else if ($list->ytplaylist->playlist_type == 1) {
            $idInfo = "Youtube Playlist";
            $list_sorted = $list->ytplaylist->ytlinks()->orderBy('ytlink_ytplaylist.id', 'desc')->get();
        } else { $idInfo = ""; $list_sorted;}

        $clicks = clickYoutubeProcessor($list, false);

        if (isset($list->user_id) && $list->user_id == Auth::id()) { $owner = true; } else { $owner = false; }

        $viewParamArray = [
            'list' => $list_sorted,
            'linkArray' => $linkArray,
            'listurl' => "mityurl.com/y/" . $alias,
            'alias' => $alias,
            'owner' => $owner,
            'listpref' => $listpref,
            'idInfo' => $idInfo,
            'id' => $list->ytplaylist->ytuser->channel_id,
            'username' => $list->ytplaylist->ytuser->channel_title,
            'playlistTitle' => $list->ytplaylist->playlist_title,
            'playlistId' => $list->ytplaylist->playlist_id,
            'thumbnail' => $list->ytplaylist->thumbnail,
            'created' => "Created: " . date('M d, Y', strtotime($list->created_at)),
            'updated' => "Updated: " . date('M d, Y', strtotime($list->updated_at)),
            'clicks_today' => $clicks['today'],
            'clicks_yesterday' => $clicks['yesterday'],
            'clicks_total' => $clicks['total']
        ];


        return view('youtubeview', $viewParamArray);

    }


    public function postCreateYoutubeList(Request $request)
    {

        $this->validate($request, [
            'urlfield' => 'required',
            'ytalias' => 'min:4|max:120|unique:ytlists,alias,NULL,id,deleted_at,NULL|alpha_dash'
        ], [
            'urlfield.required' => 'The url field is required.',
            'ytalias.min' => 'The alias must be at least 4 characters.',
            'ytalias.max' => 'The alias may not be greater than 120 characters.',
            'ytalias.unique' => 'The alias has already been taken.',
            'ytalias.alpha_dash' => 'The alias may only contain letters, numbers, and dashes.',
        ]);



        $url = $request->input('urlfield');

        $idInfoArray = validateYoutubeListID($url);      // Returns yt list id and list type

        if ($idInfoArray == "error" || $idInfoArray == "empty") {
            return redirect()->back()
                ->withInput($request->input())
                ->with('ytError', "ERROR: Invalid channel or playlist url.");
        }

        $id = $idInfoArray['id'];
        $listType = $idInfoArray['listType'];


        if ($listType == "channel") {
            if ($Ytuser = Ytuser::where('channel_id', $id)->first()) {
                $playlist_id = $Ytuser->ytplaylists->first()->playlist_id;
            } else {
                $playlist_id = youtubeChannelApiProcessor($listType, $id);
            }
        } else if ($listType == "user") {
            if ($Ytuser = Ytuser::where('custom_url', $id)->first()) {
                $playlist_id = $Ytuser->ytplaylists->first()->playlist_id;
            } else {
                $playlist_id = youtubeChannelApiProcessor($listType, $id);
            }
        } else if ($listType == "playlist") {
            if ($playlist = Ytplaylist::where('playlist_id', $id)->first()) {
                $playlist_id = $id;
            } else {
                $playlist_id = youtubePlaylistApiProcessor($id);
            }
        } else {
            return redirect()->back()
                ->withInput($request->input())
                ->with('ytError', "ERROR: Invalid channel or playlist url.");
        }

        if ($playlist_id == "error" || $playlist_id == "") {
            return redirect()->back()
                ->withInput($request->input())
                ->with('ytError', "ERROR: Invalid channel or playlist url.");
        }


        $ytplaylist = Ytplaylist::where('playlist_id', $playlist_id)->first();

        $videoIdArray = youtubeListCreatorAndUpdater($ytplaylist, true);

        if ($videoIdArray == "error" || !count($videoIdArray)) {
            return redirect()->back()
                ->withInput($request->input())
                ->with('ytError', "ERROR: Problem finding or retrieving videos from this list.");
        }


        $list = new Ytlist();
        $list->ytplaylist_id = $ytplaylist->id;
        if ($user_id = Auth::id()) { $list->user_id = $user_id; }
        if (($alias = $request->input('ytalias')) && Auth::check()) {
            $list->alias = $alias;
            try {
                $list->save();
            } catch ( \Illuminate\Database\QueryException $e) {
                $soft_deleted_list = Ytlist::onlyTrashed()->where('alias', $alias)->first();
                if ($soft_deleted_list->user_id == Auth::id()) {
                    $soft_deleted_list->forceDelete();
                    $list->save();
                } else {
                    return redirect()->back()
                        ->withInput($request->input())
                        ->with('ytError', "The alias has already been taken.");
                }
            }
        } else {
            $alias = createAliasAndSave(4, $list);
        }


        $list_url = "/y/" . $alias;

        $listcode = $list->id . 'Y' . substr(strtotime($list->created_at), -5);
//        $quickdelete = (($list->id + 411)*substr(strtotime($list->created_at), -5)) + 1991;

        return redirect($list_url)->with('listcode', $listcode);

    }




    public function postDeleteYoutubeList(Request $request)
    {
        $alias = $request->input('alias');

        if (!($list = Ytlist::where('alias', $alias)->first())) return redirect('dashboard');

        if ($list->user_id == Auth::id()) {

            if (count($list->ytplaylist->ytlists) <= 1) {
                $list->ytplaylist->ytlinks()->delete();
                $list->ytplaylist->ytlinks()->detach();
            }

            $list->delete();

            if ($clicksRecord = $list->ytclicks->first()) {
                $clicksRecord->delete();
            }

            if ($listpref = $list->listpref) {
                $listpref->delete();
            }

        }

        return redirect('dashboard');
    }



    public function postYoutubeListPref(Request $request)
    {
        $this->validate($request, ['title' => 'max:100'], ['title.max' => 'The title may not be greater than 100 characters.']);

        $alias = $request->input('alias');

        if (!($list = Ytlist::where('alias', $alias)->first())) return redirect('dashboard');

        if ($list->user_id != Auth::id()) return redirect()->back();

        $title = $request->input('title');
        $privateList = $request->input('privatelist');
        $privateClicks = $request->input('privateclicks');

        $listpref = Listpref::firstOrNew(['ytlist_id' => $list->id]);

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


    /*    public function postQuickDeleteYoutubeList(Request $request)
    {

        $alias = $request->input('alias');

        if (!($list = Ytlist::where('alias', $alias)->first())) return redirect()->back();  // redirect somewhere else maybe

        $code = $request->input('quickdelete');

        $calclistid = (($code - 1991) / substr(strtotime($list->created_at), -5))-411;

        if ($calclistid == $list->id) {

            if (count($list->ytplaylist->ytlists) <= 1) {
                $list->ytplaylist->ytlinks()->delete();
                $list->ytplaylist->ytlinks()->detach();

                if ($clicksRecord = $list->ytclicks->first()) {
                    $clicksRecord->delete();
                }

                if ($listpref = $list->listpref) {
                    $listpref->delete();
                }
            }

            $list->delete();
        }

        return redirect()->back();
    }*/


}