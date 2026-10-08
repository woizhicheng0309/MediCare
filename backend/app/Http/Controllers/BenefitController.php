<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Visit;

class BenefitController extends Controller
{
    public function index()
    {
    $year = now()->year;
    $visits = Visit::orderBy('visited_on', 'desc')->get();
    $annualLimit = 12000;
    $usedAmountCents = Visit::where('status', 'approved')->whereYear("visited_on",$year)->sum('covered_amount_cents');
    return view("benefits",["annualLimit" => $annualLimit,
    "usedAmount" => $usedAmountCents / 100, 
    "remainingBalance" => $annualLimit - ($usedAmountCents / 100),
    "visits" => $visits,]);
    }
}
