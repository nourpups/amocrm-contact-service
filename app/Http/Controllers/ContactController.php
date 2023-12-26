<?php

namespace App\Http\Controllers;

use App\Enums\Genders;
use Illuminate\Http\Request;

class ContactController extends Controller
{

    public function create()
    {
        $genders = Genders::cases();

        return view('contacts.create', compact('genders'));
    }

}
