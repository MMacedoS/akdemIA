<?php

return [
    'required' => 'O campo :attribute e obrigatorio.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'O campo :attribute deve ser um endereco de e-mail valido.',
    'confirmed' => 'A confirmacao de :attribute nao confere.',
    'unique' => 'O valor informado para :attribute ja esta em uso.',

    'min' => [
        'string' => 'O campo :attribute deve ter no minimo :min caracteres.',
    ],

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmacao de senha',
    ],
];
