<?php
function extractFromCodiceFiscale($codiceFiscale) : array {
    $codiceFiscale = strtoupper($codiceFiscale);
    if (strlen($codiceFiscale) !== 16) {
        return [];
    }

    $cognome = substr($codiceFiscale, 0, 3);
    $nome    = substr($codiceFiscale, 3, 3);
    $anno    = substr($codiceFiscale, 6, 2);
    $mese    = substr($codiceFiscale, 8, 1);
    $giorno  = substr($codiceFiscale, 9, 2);
    $comune  = substr($codiceFiscale, 11, 4);
    $mesi = [
        "A" => 1, "B" => 2, "C" => 3, "D" => 4,
        "E" => 5, "H" => 6, "L" => 7, "M" => 8,
        "P" => 9, "R" => 10, "S" => 11, "T" => 12
    ];
    $meseNum = $mesi[$mese] ?? null;
    $giornoNum = intval($giorno);
    if ($giornoNum > 40) {
        $sesso = "F";
        $giornoNum -= 40;
    } else {
        $sesso = "M";
    }
    $annoInt = intval($anno);
    $annoCompleto = ($annoInt >= 0 && $annoInt <= intval(date("y")))
        ? 2000 + $annoInt
        : 1900 + $annoInt;

    return [
        "cognome" => $cognome,
        "nome"    => $nome,
        "data_nascita" => sprintf("%04d-%02d-%02d", $annoCompleto, $meseNum, $giornoNum),
        "sesso"        => $sesso,
        "comune_nascita" => $comune
    ];
}
