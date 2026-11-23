<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;

final class HomeController extends Controller
{
    /** "/" sends guests to the login page and users to their dashboard. */
    public function index(): Response
    {
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }
}
