<?php

namespace App\Http\Controllers;

use App\Customlist;
use App\Ytlist;
use Illuminate\Routing\Controller;

class FilterController extends Controller
{

    public function getListFilter($l_or_y, $alias, $filter, $embed="") {

        if (!preg_match("/^(([1-9][0-9]*$)|r|n|p|o)$/", $filter, $output_array))
            return view('notfound', ['problem' => 'filter']);


        if ($l_or_y == 'y' && $list = Ytlist::where('alias', $alias)->first()) {
            $linkArray = getYoutubeListAsArray($alias, true);
            if ($linkArray == "error") return view('notfound', ['problem' => 'retrieval']);
            clickYoutubeProcessor($list);
        } else if ($l_or_y == 'l' && $list = Customlist::where('alias', $alias)->first()) {
            $listArray = getListAsArray($alias, false, true);
            if (count($listArray[1]) > 0) return redirect('https://mityurl.com/l/' . $alias);   // checking threatArray
            $linkArray = $listArray[0];
            if ($linkArray == "error") return view('notfound', ['problem' => 'retrieval']);
            clickCustomProcessor($list);
        } else {
            return view('notfound', ['item' => 'list']);
        }


        if (is_numeric($filter)) {      // is_int() is too specific for this.

            if ($filter < 1 || $filter > count($linkArray)) {
                return view('notfound', ['problem' => 'index']);
            }

            $url = $linkArray[$filter - 1];

            if ($l_or_y == 'y' && $embed == "embed") {
                return view('embed', ['url' => str_replace("watch?v=","embed/",$url)]);
            }

            return redirect($url);

        }

        switch ($filter) {

            case "r":

                $random_keys = array_rand($linkArray);

                $url = $linkArray[$random_keys];

                break;

            case "n":

                $url = $linkArray[0];

                break;

            case "p":

                $url = $linkArray[1];

                break;

            case "o":

                $url = end($linkArray);

                break;

            default:

                return view('notfound', ['problem' => 'filter']);

        }

        if ($l_or_y == 'y' && $embed == "embed") {
            return view('embed', ['url' => str_replace("watch?v=","embed/",$url)]);
        }

        return redirect($url);

    }


    public function getListMultiFilter($l_or_y, $alias, $filter1, $filter2, $filter3, $embed="") {

        if (!preg_match("/^(r|n|o)$/", $filter1, $output_array)) return view('notfound', ['problem' => 'filter']);
        if (!preg_match("/^(([1-9][0-9]*$)|n|o)$/", $filter2, $output_array)) return view('notfound', ['problem' => 'filter']);
        if (!preg_match("/^([1-9][0-9]*)$/", $filter3, $output_array)) return view('notfound', ['problem' => 'filter']);

        if ($l_or_y == 'y' && $list = Ytlist::where('alias', $alias)->first()) {
            $linkArray = getYoutubeListAsArray($alias, true);
            if ($linkArray == "error") return view('notfound', ['problem' => 'retrieval']);
            clickYoutubeProcessor($list);
        } else if ($l_or_y == 'l' && $list = Customlist::where('alias', $alias)->first()) {
            $listArray = getListAsArray($alias, false, true);
            if (count($listArray[1]) > 0) return redirect('https://mityurl.com/l/' . $alias);   // checking threatArray
            $linkArray = $listArray[0];
            if ($linkArray == "error") return view('notfound', ['problem' => 'retrieval']);
            clickCustomProcessor($list);
        } else {
            return view('notfound', ['item' => 'list']);
        }
        
        $url = "";

        if ($filter3 < 1 || $filter3 > count($linkArray)) {
            return view('notfound', ['problem' => 'index']);
        }


        if (is_numeric($filter2)) {

            if ($filter2 < 1 || $filter2 > count($linkArray))
                return view('notfound', ['problem' => 'index']);
            if ($filter2 > $filter3)
                return view('notfound', ['problem' => 'filter', 'customMessage' => 'The second parameter must be smaller than the third.']);


            switch ($filter1) {

                case "r":

                    $slicedLinkArray = array_slice($linkArray, $filter2 - 1, $filter3 - $filter2 + 1);

                    $random_keys = array_rand($slicedLinkArray);

                    $url = $slicedLinkArray[$random_keys];

                    break;

                case "n":

                    $url = $linkArray[$filter2 - 1];

                    break;

                case "o":

                    $url = $linkArray[$filter3 - 1];

                    break;

                default:

                    return view('notfound', ['problem' => 'filter']);

            }

        }

        if ($filter2 == 'n') {

            switch ($filter1) {

                case "r":

                    $slicedLinkArray = array_slice($linkArray, 0, $filter3);

                    $random_keys = array_rand($slicedLinkArray);

                    $url = $slicedLinkArray[$random_keys];

                    break;

                case "n":

                    $url = $linkArray[0];

                    break;

                case "o":

                    $url = $linkArray[$filter3 - 1];

                    break;

                default:

                    return view('notfound', ['problem' => 'filter']);

            }

        }

        if ($filter2 == 'o') {

            switch ($filter1) {

                case "r":

                    $slicedLinkArray = array_slice($linkArray, count($linkArray) - $filter3, $filter3);

                    $random_keys = array_rand($slicedLinkArray);

                    $url = $slicedLinkArray[$random_keys];

                    break;

                case "n":

                    $url = $linkArray[count($linkArray) - $filter3];

                    break;

                case "o":

                    $url = $linkArray[count($linkArray) - 1];

                    break;

                default:

                    return view('notfound', ['problem' => 'filter']);

            }

        }

        if ($l_or_y == 'y' && $embed == "embed") {
            return view('embed', ['url' => str_replace("watch?v=","embed/",$url)]);
        }

        return redirect($url);

    }




}

