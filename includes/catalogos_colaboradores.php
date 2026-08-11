<?php
/** Catálogos reutilizados por la administración y el autoservicio del colaborador. */

function colaboradores_teams_base(): array
{
    return [
        'G1 TEAM A', 'G2 TEAM B', 'G3 TEAM C', 'G4 TEAM D',
        'SIN ASIGNAR',
        'TEAM A', 'TEAM B', 'TEAM C', 'TEAM D',
        'T TEAM A', 'T TEAM B', 'T TEAM C', 'T TEAM D',
        'TALLY LIDER A', 'TALLY LIDER B', 'TALLY LIDER C', 'TALLY LIDER D',
        'SOMBRA TALLY',
    ];
}

function colaboradores_teams_disponibles($conn): array
{
    $teams = colaboradores_teams_base();
    $resultado = mysqli_query($conn, "SELECT DISTINCT TRIM(cuadrilla) AS team FROM colaboradores WHERE TRIM(COALESCE(cuadrilla, '')) <> '' ORDER BY team ASC");
    if ($resultado) {
        while ($fila = mysqli_fetch_assoc($resultado)) {
            if (!in_array($fila['team'], $teams, true)) {
                $teams[] = $fila['team'];
            }
        }
    }
    return $teams;
}

function colaborador_team_es_valido($conn, string $team): bool
{
    return in_array($team, colaboradores_teams_disponibles($conn), true);
}
