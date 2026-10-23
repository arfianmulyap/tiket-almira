<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel's base controller no longer includes this trait, but policies
    // are the project's authorisation mechanism, so $this->authorize() has
    // to be available to every controller.
    use AuthorizesRequests;
}
