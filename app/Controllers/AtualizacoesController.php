<?php

namespace App\Controllers;

use App\Services\UpdateChecker;

class AtualizacoesController extends BaseController
{
    public function index(): void
    {
        $status = UpdateChecker::status();
        $this->render('atualizacoes/index', compact('status'));
    }
}
