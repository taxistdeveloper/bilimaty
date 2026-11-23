<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DemoRoleSwitch;

/** Demo mode: "view as role" switcher in the top bar (super admin, not in production). */
final class DemoRoleController extends Controller
{
    public function switch(Request $request): Response
    {
        if (!DemoRoleSwitch::enabled()) {
            throw new HttpException(403);
        }
        if (!DemoRoleSwitch::switchTo((string) $request->input('role', ''))) {
            Session::flash('error', __('demo.no_user'));

            return $this->redirect('/dashboard');
        }

        return $this->redirect('/dashboard');
    }

    public function restore(): Response
    {
        DemoRoleSwitch::restore();

        return $this->redirect('/dashboard');
    }
}
