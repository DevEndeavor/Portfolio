<?php


function youtubeVideoApiProcessor($id) {        //READY FOR V2

    $API_KEY = "xxxxxxxxx";
    $itemsArray = array();
    $nextPageToken = "";
    $pages = 1;


    for ($i=0; $i<$pages; $i++) {

        $json_string = "https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&key=" . $API_KEY . "&playlistId=" . $id . "&pageToken=" . $nextPageToken . "&maxResults=50";

        if (!($jsondata = @file_get_contents($json_string))) return "error";

        $obj = json_decode($jsondata);
        if (!isset($obj->items)) return "error";

        $itemsArray = array_merge($itemsArray, $obj->items);


        if (isset($obj->nextPageToken)) { $nextPageToken = $obj->nextPageToken; } else { break; }   // loop will break after last page has been processed

        if ($i == 0) {
            $pages = ceil($obj->pageInfo->totalResults / 50);   // sets number of pages to loop through.
        }

        if ($pages > ceil($obj->pageInfo->totalResults / 50)) {
            $pages = ceil($obj->pageInfo->totalResults / 50);
        }

    }

    $itemsArray = array_reverse($itemsArray);   // this is to reverse store order in DB later

    return $itemsArray;

}