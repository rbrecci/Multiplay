<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// O build do Vue (app/dist) e copiado pra public/ com index.html renomeado pra spa.html,
// pra o DirectoryIndex do Apache nao servir o HTML antes do index.php. Qualquer rota que
// nao seja da API cai aqui e recebe o SPA; o Vue Router resolve o resto no cliente.
Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Rota nao encontrada.'], 404);
    }

    $spa = public_path('spa.html');

    abort_unless(file_exists($spa), 404);

    return response()->file($spa, ['Cache-Control' => 'no-cache']);
});
