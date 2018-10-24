<?php

namespace App\Http\Controllers;

use App\Url;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;

class SBController extends Controller
{
    public function cron() {

        mail("my.dev.endeavor@gmail.com", "CUSTOM CRON!!", time());

    }

    /*
     * Using only scan5min & scan1hr each new url will be scanned 4 times in 72hrs. This system can only handle 2500 new urls per day.
     * Adding scan1day yields 6 scans per month per new url, dropping the allowance to a measly 1666 new urls per day.
     * */

    public function scan5min() {

        $urlsToScan = array();

        $last2hrs = date('Y-m-d H:i:s', time()-(2*60*60));
        $last3hrs = date('Y-m-d H:i:s', time()-(3*60*60));

        if(!($Url = Url::whereBetween('created_at', [$last3hrs, $last2hrs])->orWhereRaw('created_at = updated_at')->get())) return;

        foreach ($Url as $url) {
            $created_at = date_create($url->created_at);
            $updated_at = date_create($url->updated_at);
            $scanInterval = $updated_at->diff($created_at)->format('%i');   // scanInterval is in minutes

            if ($scanInterval == 0) {  // first scan
                array_push($urlsToScan, $url->url);
            } else if ($created_at <= $last2hrs && $scanInterval < 2*60) {
                array_push($urlsToScan, $url->url);
            }
        }

        if (!count($urlsToScan)) return;

        $threatUrls = safeBrowsingScanner($urlsToScan);

        foreach ($urlsToScan as $testUrl) {

            $url = Url::where('url', $testUrl)->whereBetween('created_at', [$last3hrs, $last2hrs])->orWhereRaw('created_at = updated_at')->first();

            if (array_key_exists($testUrl, $threatUrls)) {
                $url->threat = $threatUrls[$testUrl];
            } else {
                $url->threat = null;
            }

            $url->updated_at = date('Y-m-d H:i:s', time());
            $url->save();
        }

        mail("my.dev.endeavor@gmail.com", "mityurl scan5min", json_encode($threatUrls));

    }

    public function scan1hr() {

        $urlsToScan = array();

        $last24hrs = date('Y-m-d H:i:s', time()-(24*60*60));
        $last28hrs = date('Y-m-d H:i:s', time()-(28*60*60));
        $last72hrs = date('Y-m-d H:i:s', time()-(72*60*60));
        $last76hrs = date('Y-m-d H:i:s', time()-(76*60*60));

        if(!($Url = Url::whereBetween('created_at', [$last28hrs, $last24hrs])->orWhereBetween('created_at', [$last76hrs, $last72hrs])->get())) return;

        foreach ($Url as $url) {
            $created_at = date_create($url->created_at);
            $updated_at = date_create($url->updated_at);
            $scanInterval = $updated_at->diff($created_at)->format('%i');

            if ($created_at <= $last24hrs && $scanInterval < 24*60) {
                array_push($urlsToScan, $url->url);
            } else if ($created_at <= $last72hrs && $scanInterval < 72*60) {
                array_push($urlsToScan, $url->url);
            }
        }

        if (!count($urlsToScan)) return;

        $threatUrls = safeBrowsingScanner($urlsToScan);

        foreach ($urlsToScan as $testUrl) {

            $url = Url::where('url', $testUrl)->whereBetween('created_at', [$last28hrs, $last24hrs])->orWhereBetween('created_at', [$last76hrs, $last72hrs])->first();

            if (array_key_exists($testUrl, $threatUrls)) {
                $url->threat = $threatUrls[$testUrl];
            } else {
                $url->threat = null;
            }

            $url->updated_at = date('Y-m-d H:i:s', time());
            $url->save();
        }

        mail("my.dev.endeavor@gmail.com", "mityurl scan1hr", json_encode($threatUrls));

    }

    public function scan1day() {    // API quota too limiting to run this

        $urlsToScan = array();

        $last7days = date('Y-m-d H:i:s', time()-(7*24*60*60));
        $last9days = date('Y-m-d H:i:s', time()-(9*24*60*60));
        $last28days = date('Y-m-d H:i:s', time()-(28*24*60*60));
        $last30days = date('Y-m-d H:i:s', time()-(30*24*60*60));

        if(!($Url = Url::whereBetween('created_at', [$last9days, $last7days])->orWhereBetween('created_at', [$last30days, $last28days])->get())) return;

        foreach ($Url as $url) {
            $created_at = date_create($url->created_at);
            $updated_at = date_create($url->updated_at);
            $scanInterval = $updated_at->diff($created_at)->format('%i');

            if ($created_at <= $last7days && $scanInterval < 7*24*60) {
                array_push($urlsToScan, $url->url);
            } else if ($created_at <= $last28days && $scanInterval < 28*24*60) {
                array_push($urlsToScan, $url->url);
            }
        }

        if (!count($urlsToScan)) return;

        $threatUrls = safeBrowsingScanner($urlsToScan);

        foreach ($urlsToScan as $testUrl) {

            $url = Url::where('url', $testUrl)->whereBetween('created_at', [$last9days, $last7days])->orWhereBetween('created_at', [$last30days, $last28days])->first();

            if (array_key_exists($testUrl, $threatUrls)) {
                $url->threat = $threatUrls[$testUrl];
            } else {
                $url->threat = null;
            }

            $url->updated_at = date('Y-m-d H:i:s', time());
            $url->save();
        }

        mail("my.dev.endeavor@gmail.com", "mityurl scan1day", json_encode($threatUrls));

    }


}