<?php

use App\Url;
use App\Link;
use App\Llist;
use App\Customlist;
use App\Click;
use App\Customclick;
use App\Ytclick;
use App\Ytuser;
use App\Ytlink;
use App\Ytlist;
use App\Ytplaylist;

function createAliasAndSave($length, $obj) {

    $caught = false;

    for ($i=0; $i<3; $i++) {

        $alias = str_random($length);
        $obj->alias = $alias;

        try {
            $obj->save();
        } catch ( \Illuminate\Database\QueryException $e) {
            $caught = true;
        }
        if (!$caught) {
            return $alias;
        }
    }

    $alias = createAliasAndSave($length+1, $obj);

    return $alias;
}

function validateUrl($url) {        

    if (ctype_space($url) || $url == '' || $url == "\n" || $url == "\r") return "empty";    

    $url = trim($url, " \t\n\r\0\x0B");
    $url = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $url);

    $url = filter_var($url, FILTER_SANITIZE_URL);

    while (substr($url, -1) == '/') $url = substr($url, 0, -1);    

    if (!($parseArray = parse_url($url))) return "error";

    if (!array_key_exists('scheme', $parseArray)) {     
        $url = "http://" . $url;
        $parseArray = parse_url($url);
    }

    $url = join_url($parseArray);   

    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
        return "error";
    }

    while (substr($url, -1) == '/') $url = substr($url, 0, -1);

    return $url;

}

function addNewYtlinkAndReturnId($url, $publishedAt=null) {        
    $link = new Ytlink();
    $link->url = $url;
    $link->published_at = $publishedAt;
    $link->save();
    return $link->id;
}

function getListAsArray($alias, $keyvalue=true, $threat2ndArray=false) {        

    if ($list = Customlist::where('alias', $alias)->first()) {

        $linkArray = array();
        $threatArray = array();

        $Urls = $list->urls()->orderBy('customlist_url.id', 'asc')->get();

        foreach ($Urls as $Url) {

            $threat = $Url->threat;

            if (isset($threat) && (strtotime("now") - strtotime($Url->updated_at) > 10*60)) {      
                $threat = SBscan($Url->url);    
            }

            if ($keyvalue && !$threat2ndArray) {
                $linkArray[$Url->id] = $Url->url;
            } else if (!$keyvalue && $threat2ndArray) {
                array_push($linkArray, $Url->url);
                if (isset($threat)) $threatArray[$Url->url] = $threat;
            } else if ($keyvalue && $threat2ndArray) {
                $linkArray[$Url->id] = $Url->url;
                if (isset($threat)) $threatArray[$Url->url] = $threat;
            } else {
                array_push($linkArray, $Url->url);
            }
        }

        if (!$keyvalue && $threat2ndArray) return array($linkArray, $threatArray);
        if ($keyvalue && $threat2ndArray) return array($linkArray, $threatArray);

        return $linkArray;
    }

    return "error";

}

function getYoutubeListAsArray($alias, $fullUrl=false) {        

    $ytlist = Ytlist::where('alias', $alias)->first();

    $ytplaylist = $ytlist->ytplaylist;

    $videoIdArray = youtubeListCreatorAndUpdater($ytplaylist);

    if ($videoIdArray == "error") return "error";

    if ($fullUrl) {
        $linkArray = array();
        foreach ($videoIdArray as $videoId) {
            array_push($linkArray, "https://www.youtube.com/watch?v=" . $videoId);
        }
        return array_reverse($linkArray);   

    }

    return array_reverse($videoIdArray);

}

function validateYoutubeListID($id) {        

    if (ctype_space($id) || $id == '') {      
        return "empty";
    } 
    if (strlen($id) < 6) {
        return "error";
    }

    $id = trim($id, " \t\0\x0B");   

    $channel_user_pattern = '/^\s*(?:(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com)?\/?(?:user\/|channel\/|c\/)?([^\/\?]+)/i';
    $playlist_pattern = '~(?:http|https|)(?::\/\/|)(?:www.|)(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/ytscreeningroom\?v=|\/feeds\/api\/videos\/|\/user\S*[^\w\-\s]|\S*[^\w\-\s]))([\w\-]{12,})[a-z0-9;:@#?&%=+\/\$_.-]*~i';

    $listType = "inconclusive";

    if (strpos($id, 'list=') !== false) {
        $id = (preg_replace($playlist_pattern, '$1', $id));
        $listType = "playlist";
    } else if (strpos($id, 'channel/') !== false) {
        $id = (preg_replace($channel_user_pattern, '$1', $id));
        $listType = "channel";
    } else if (strpos($id, 'user/') !== false) {
        $id = (preg_replace($channel_user_pattern, '$1', $id));
        $listType = "user";
    } else {
        $id = (preg_replace($channel_user_pattern, '$1', $id));
    }

    if ($listType == "inconclusive") {
        if (substr($id, 0, 2 ) === "PL" || substr($id, 0, 2 ) === "UU" || substr($id, 0, 2 ) === "FL" || substr($id, 0, 2 ) === "LL") {
            $listType = "playlist";         
        } else if (substr($id, 0, 2 ) === "UC") {
            $listType = "channel";
        } else {
            $listType = "user";     

        }
    }

    $strArray = str_split($id);

    $id = "";

    foreach ($strArray as $char) {
        if ($char != '/' && $char != '&' && $char != '?' && $char != '#' ) {
            $id .= $char;
        } else {
            break;
        }
    }

    return array('id' => $id, 'listType' => $listType);
}

function youtubeChannelApiProcessor($listType, $id) {        

    $API_KEY = "AIzaSyBtf-KXJ-lff43O1yg1ubReQTuHw8EKeVQ";
    $channelId = "";
    $forUsername = "";

    if ($listType == "channel") {
        $channelId = "&id=" . $id;
    }
    if ($listType == "user") {
        $forUsername = "&forUsername=" . $id;
    }

    $json_string = "https://www.googleapis.com/youtube/v3/channels?part=snippet&key=" . $API_KEY . $channelId . $forUsername;

    if (!($jsondata = @file_get_contents($json_string))) {
        return "error";
    }
    $obj = json_decode($jsondata);
    if (!($itemsArray = $obj->items)) return "error";

    $channelId = $itemsArray[0]->id;
    $uploadsId = substr_replace($channelId, "UU", 0, 2);
    $channelTitle = $itemsArray[0]->snippet->title;
    $thumbnail = $itemsArray[0]->snippet->thumbnails->default->url;

    $ytuser = Ytuser::firstOrNew(['channel_id' => $channelId]);

    $ytuser->channel_title = $channelTitle;
    if (isset($itemsArray[0]->snippet->customUrl)) {
        $ytuser->custom_url = $itemsArray[0]->snippet->customUrl;
    }
    $ytuser->save();

    $ytplaylist = Ytplaylist::firstOrNew(['playlist_id' => $uploadsId]);     

    $ytplaylist->playlist_type = 0;
    $ytplaylist->thumbnail = $thumbnail;
    $ytplaylist->ytuser_id = $ytuser->id;
    $ytplaylist->save();

    return ($uploadsId);

}

function youtubePlaylistApiProcessor($id) {        

    $API_KEY = "AIzaSyBtf-KXJ-lff43O1yg1ubReQTuHw8EKeVQ";

    $json_string = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&key=" . $API_KEY . "&id=" . $id;

    if (!($jsondata = @file_get_contents($json_string))) {
        return "error";
    }
    $obj = json_decode($jsondata);
    if (!($itemsArray = $obj->items)) return "error";

    $channelId = $itemsArray[0]->snippet->channelId;
    $thumbnail = $itemsArray[0]->snippet->thumbnails->default->url;
    $playlistTitle = $itemsArray[0]->snippet->localized->title;

    if ($ytuser = Ytuser::where('channel_id', $channelId)->first()) {
        $ytuserId = $ytuser->id;
    } else {
        $uploadsId = youtubeChannelApiProcessor("channel", $channelId);     
        $ytuserId = Ytplaylist::where('playlist_id', $uploadsId)->first()->ytuser_id;
    }

    if (substr($id, 0, 2) != "UU") {    
        $playlist = Ytplaylist::firstOrNew(['playlist_id' => $id]);

        $playlist->playlist_type = 1;
        $playlist->playlist_title = $playlistTitle;
        $playlist->thumbnail = $thumbnail;
        $playlist->ytuser_id = $ytuserId;
        $playlist->save();
    }

    return ($id);

}

function youtubeVideoApiProcessor($id, $pages=1) {        

    $API_KEY = "AIzaSyBtf-KXJ-lff43O1yg1ubReQTuHw8EKeVQ";
    $itemsArray = array();
    $nextPageToken = "";

    for ($i=0; $i<$pages; $i++) {

        $json_string = "https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&key=" . $API_KEY . "&playlistId=" . $id . "&pageToken=" . $nextPageToken . "&maxResults=50";

        if (!($jsondata = @file_get_contents($json_string))) return "error";

        $obj = json_decode($jsondata);
        if (!isset($obj->items)) return "error";

        $itemsArray = array_merge($itemsArray, $obj->items);

        if (isset($obj->nextPageToken)) { $nextPageToken = $obj->nextPageToken; } else { break; }

        if ($pages > ceil($obj->pageInfo->totalResults / 50)) {
            $pages = ceil($obj->pageInfo->totalResults / 50);
        }

    }

    $itemsArray = array_reverse($itemsArray);   

    return $itemsArray;

}

function youtubeListCreatorAndUpdater($ytplaylist, $justCreated=false) {        

    if ( !$justCreated && (strtotime("now") - strtotime($ytplaylist->updated_at) < 20*60) ) {      
        $videoIdArray = array();
        if ($ytplaylist->playlist_type == 1) {
            $links = $ytplaylist->ytlinks()->orderBy('ytlink_ytplaylist.id', 'asc')->get();     
        } else {
            $links = $ytplaylist->ytlinks()->orderBy('ytlinks.published_at', 'asc')->orderBy('ytlink_ytplaylist.id', 'asc')->get();     
        }
        foreach ($links as $link) {
            $videoId = $link->url;
            array_push($videoIdArray, $videoId);
        }
        return $videoIdArray;
    }

    if ($ytplaylist->pages != $ytplaylist->pages_compare) {      
        $ytplaylist->ytlinks()->delete();
        $ytplaylist->pages_compare = $ytplaylist->pages;
    }

    $ytplaylist->updated_at = date('Y-m-d H:i:s', time());
    $ytplaylist->save();

    $itemsArray = youtubeVideoApiProcessor($ytplaylist->playlist_id, $ytplaylist->pages);

    if ($itemsArray == "error") return "error";

    $videoIdArray = array();
    $link_id_Array = array();

    foreach ($itemsArray as $items) {
        if (isset($items->contentDetails->videoId) && isset($items->contentDetails->videoPublishedAt)) {      

            $videoId = $items->contentDetails->videoId;
            $publishedAt = $items->contentDetails->videoPublishedAt;

            if ($link = $ytplaylist->ytlinks->where('url', $videoId)->first()) {
                $link_id = $link->id;
                if (!isset($link->published_at)) {
                    $link->published_at = date('Y-m-d H:i:s',strtotime($publishedAt));
                    $link->save();
                }
            } else {
                $link_id = addNewYtlinkAndReturnId($videoId, date('Y-m-d H:i:s',strtotime($publishedAt)));
            }

            array_push($videoIdArray, $videoId);
            array_push($link_id_Array, $link_id);
        }
    }

    $attachedDetached = $ytplaylist->ytlinks()->sync($link_id_Array);

    $detachedArray = $attachedDetached['detached'];

    if (count($detachedArray) > 0) {
        purgeNonRelatedLinkBaggage($detachedArray);   
    }

    return $videoIdArray;

}

function purgeNonRelatedLinkBaggage($detachedArray) {        

    foreach ($detachedArray as $link_id) {
        if ($link = Ytlink::where('id', $link_id)->first()) {
            if (!count($link->ytplaylists->first())) {    
                $link->delete();
            }
        }
    }

    return;
}

function purgeOldLinkListBaggage() {        

    $allLists = Llist::all();

    foreach ($allLists as $list) {
        if (strtotime("now") - strtotime($list->last_used) > 31536000) {

            $list->Link()->detach();

            $list->delete();
        }
    }

    $allLinks = Link::all();

    foreach ($allLinks as $link) {
        if (strtotime("now") - strtotime($link->last_used) > 31536000) {
            if (!count($link->llist->first())) {        
                $link->delete();
            }
        }
    }

}

function clickProcessor($link, $clicked=true) {        

    if (!$clicked) {
        if ($click = Click::where('link_id', $link->id)->first()) {
            $current_date = new DateTime(date('Y-m-d', time()));
            $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
            $date_diff = $updated_at->diff($current_date)->format("%d");

            if ($date_diff == 1) {
                $click->clicks_yesterday = $click->clicks_today;
                $click->clicks_today = 0;
                $click->save();
            } else if ($date_diff > 1) {
                $click->clicks_yesterday = 0;
                $click->clicks_today = 0;
                $click->save();
            }
            return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
        }

        return array('today' => 0, 'yesterday' => 0, 'total' => 0);
    }

    if (!($click = Click::where('link_id', $link->id)->first())) {
        $click = new Click();
        $click->link_id = $link->id;
        $click->save();
    }

    $current_date = new DateTime(date('Y-m-d', time()));
    $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
    $date_diff = $updated_at->diff($current_date)->format("%d");

    if ($date_diff == 0) {
        $click->clicks_today += 1;
    } else if ($date_diff == 1) {
        $click->clicks_yesterday = $click->clicks_today;
        $click->clicks_today = 1;
    } else {
        $click->clicks_yesterday = 0;
        $click->clicks_today = 1;
    }
    $click->clicks_total += 1;
    $click->save();

    return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
}

function clickCustomProcessor($list, $clicked=true) {        

    if (!$clicked) {
        if ($click = Customclick::where('customlist_id', $list->id)->first()) {
            $current_date = new DateTime(date('Y-m-d', time()));
            $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
            $date_diff = $updated_at->diff($current_date)->format("%d");

            if ($date_diff == 1) {
                $click->clicks_yesterday = $click->clicks_today;
                $click->clicks_today = 0;
                $click->save();
            } else if ($date_diff > 1) {
                $click->clicks_yesterday = 0;
                $click->clicks_today = 0;
                $click->save();
            }
            return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
        }

        return array('today' => 0, 'yesterday' => 0, 'total' => 0);
    }

    if (!($click = Customclick::where('customlist_id', $list->id)->first())) {
        $click = new Customclick();
        $click->customlist_id = $list->id;
        $click->save();
    }

    $current_date = new DateTime(date('Y-m-d', time()));
    $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
    $date_diff = $updated_at->diff($current_date)->format("%d");

    if ($date_diff == 0) {
        $click->clicks_today += 1;
    } else if ($date_diff == 1) {
        $click->clicks_yesterday = $click->clicks_today;
        $click->clicks_today = 1;
    } else {
        $click->clicks_yesterday = 0;
        $click->clicks_today = 1;
    }
    $click->clicks_total += 1;
    $click->save();

    return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
}

function clickYoutubeProcessor($list, $clicked=true) {        

    if (!$clicked) {
        if ($click = Ytclick::where('ytlist_id', $list->id)->first()) {
            $current_date = new DateTime(date('Y-m-d', time()));
            $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
            $date_diff = $updated_at->diff($current_date)->format("%d");

            if ($date_diff == 1) {
                $click->clicks_yesterday = $click->clicks_today;
                $click->clicks_today = 0;
                $click->save();
            } else if ($date_diff > 1) {
                $click->clicks_yesterday = 0;
                $click->clicks_today = 0;
                $click->save();
            }
            return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
        }

        return array('today' => 0, 'yesterday' => 0, 'total' => 0);
    }

    if (!($click = Ytclick::where('ytlist_id', $list->id)->first())) {
        $click = new Ytclick();
        $click->ytlist_id = $list->id;
        $click->save();
    }

    $current_date = new DateTime(date('Y-m-d', time()));
    $updated_at = new DateTime(date('Y-m-d', strtotime($click->updated_at)));
    $date_diff = $updated_at->diff($current_date)->format("%d");

    if ($date_diff == 0) {
        $click->clicks_today += 1;
    } else if ($date_diff == 1) {
        $click->clicks_yesterday = $click->clicks_today;
        $click->clicks_today = 1;
    } else {
        $click->clicks_yesterday = 0;
        $click->clicks_today = 1;
    }
    $click->clicks_total += 1;
    $click->save();

    return array('today' => $click->clicks_today, 'yesterday' => $click->clicks_yesterday, 'total' => $click->clicks_total);
}

function SBscan($url) {

    $threat = safeBrowsingScanner((array)$url);

    $Url = Url::where('url', $url)->first();

    if (array_key_exists($url, $threat)) {
        $Url->threat = $threat[$url];
    } else {
        $Url->threat = null;
    }

    $Url->updated_at = date('Y-m-d H:i:s', time());
    $Url->save();

    return $Url->threat;
}

function safeBrowsingScanner($urlsToScan) {

    $threatUrls = array();

    $urlArrayChunks = array_chunk($urlsToScan, 500);

    foreach ($urlArrayChunks as $urlChunk) {

        $dataArray = "{
        'client': {
          'clientId':      'DevEndeavor',
          'clientVersion': '1.5.2'
        },
        'threatInfo': {
          'threatTypes':      ['MALWARE', 'SOCIAL_ENGINEERING'],
          'platformTypes':    ['WINDOWS'],
          'threatEntryTypes': ['URL'],
          'threatEntries': [";

        foreach ($urlChunk as $url) {
            $dataArray .= "{'url': '" . $url . "'},";
        }
        $dataArray .= "
          ]
        }
        }";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://safebrowsing.googleapis.com/v4/threatMatches:find?key=AIzaSyBiznD6ml8iFNc4Fsc7qFpUNN0LLBe8BC4");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataArray);

        $output = curl_exec($ch);
        curl_close($ch);

        $output = json_decode($output);

        if (!isset($output->matches)) return $threatUrls;   

        foreach ($output->matches as $match) {
            $threatUrls[$match->threat->url] = $match->threatType;
        }

    }

    return $threatUrls;

}

function join_url( $parts, $encode=TRUE )   
{

    $url = '';
    if ( !empty( $parts['scheme'] ) )
        $url .= strtolower($parts['scheme']) . ':';
    if ( isset( $parts['host'] ) )
    {
        $url .= '//';
        if ( isset( $parts['user'] ) )
        {
            $url .= $parts['user'];
            if ( isset( $parts['pass'] ) )
                $url .= ':' . $parts['pass'];
            $url .= '@';
        }
        if ( preg_match( '!^[\da-f]*:[\da-f.:]+$!ui', $parts['host'] ) )
            $url .= '[' . strtolower($parts['host']) . ']'; 
        else
            $url .= strtolower($parts['host']);             
        if ( isset( $parts['port'] ) )
            $url .= ':' . $parts['port'];
        if ( !empty( $parts['path'] ) && $parts['path'][0] != '/' )
            $url .= '/';
    }
    if ( !empty( $parts['path'] ) )
        $url .= $parts['path'];
    if ( isset( $parts['query'] ) )
        $url .= '?' . $parts['query'];
    if ( isset( $parts['fragment'] ) )
        $url .= '#' . $parts['fragment'];
    return $url;
}