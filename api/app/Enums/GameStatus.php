<?php

namespace App\Enums;

enum GameStatus: string
{
    case Jogado = 'jogado';
    case Interesse = 'interesse';
    case Zerado = 'zerado';
    case QueroJogar = 'quero_jogar';
}
