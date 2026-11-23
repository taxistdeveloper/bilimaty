<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Repositories\UserRepository;

final class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): Response
    {
        if (!Translator::isSupported($locale)) {
            throw new HttpException(404);
        }

        Session::set('locale', $locale);
        if (Auth::check()) {
            (new UserRepository())->updateLocale((int) Auth::id(), $locale);
        }

        return $this->back($request, '/dashboard');
    }
}
