<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LegalDocuments;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function terms(): View
    {
        return view('legal.show', [
            'document' => LegalDocuments::termsDocument(),
            'kicker' => 'Documento legal',
            'intro' => 'Estes Termos de Uso regulam o acesso e a utilizacao da plataforma ' . config('legal.company_name') . ', incluindo site, painel web e aplicacoes integradas.',
        ]);
    }

    public function privacy(): View
    {
        return view('legal.show', [
            'document' => LegalDocuments::privacyPolicyDocument(),
            'kicker' => 'LGPD',
            'intro' => 'Esta Politica de Privacidade descreve como ' . config('legal.company_name') . ' coleta, utiliza, armazena e protege dados pessoais em conformidade com a Lei Geral de Protecao de Dados Pessoais.',
        ]);
    }
}
