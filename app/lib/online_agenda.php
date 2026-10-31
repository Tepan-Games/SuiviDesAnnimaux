<?php
declare(strict_types=1);

// ===== Interface en ligne : accueil (échéances), suivis demandés, prises de traitement, frais et entretiens =====
// Mêmes règles que le logiciel : DatabaseService.GetDashboardItems, FollowUpService, TreatmentService.RecordAdministration,
// RecurringTaskService.Complete / Skip.

/** Jours de l'intervalle d'un suivi demandé (FollowUp.IntervalDays). */
function online_follow_up_days(array $followUp): int
{
    $value = max(1, (int)($followUp['IntervalValue'] ?? 1));
    return ($followUp['IntervalUnit'] ?? '') === 'jour(s)' ? $value : $value * 7;
}

/** « chaque jour », « toutes les 2 semaines »… (FollowUp.IntervalText). */
function online_follow_up_interval(array $followUp): string
{
    $value = max(1, (int)($followUp['IntervalValue'] ?? 1));
    $days = ($followUp['IntervalUnit'] ?? '') === 'jour(s)';
    if ($value === 1) {
        return $days ? 'chaque jour' : 'chaque semaine';
    }
    return $days ? "tous les $value jours" : "toutes les $value semaines";
}

/** Jours où quelque chose du type du suivi a été noté pour l'animal (FollowUpService.RecordedDays). */
function online_follow_up_recorded(array $state, array $followUp): array
{
    $animalId = (string)($followUp['AnimalId'] ?? '');
    [$table, $column, $keep] = match ($followUp['Type'] ?? '') {
        'Pesée' => ['AnimalEvents', 'EventDate', fn($r) => (float)($r['WeightKg'] ?? 0) > 0],
        'Repas et eau' => ['FoodLogs', 'LogDate', fn($r) => true],
        'Photo' => ['AnimalPhotos', 'PhotoDate', fn($r) => true],
        default => ['AnimalEvents', 'EventDate', fn($r) => true],
    };
    $days = [];
    foreach (online_rows_of($state, $table, $animalId) as $row) {
        if ($keep($row)) {
            $days[substr((string)($row[$column] ?? ''), 0, 10)] = true;
        }
    }
    return $days;
}

/** Avancement d'un suivi : ['done', 'due', 'missed' => [dates], 'next' => date ou null, 'current_done', 'text'] (FollowUpService.Status). */
function online_follow_up_status(array $state, array $followUp, ?string $today = null): array
{
    $today ??= date('Y-m-d');
    $recorded = array_keys(online_follow_up_recorded($state, $followUp));
    $start = substr((string)($followUp['StartDate'] ?? $today), 0, 10);
    $end = substr((string)($followUp['EndDate'] ?? $today), 0, 10);
    $step = online_follow_up_days($followUp);
    $done = $due = 0;
    $missed = [];
    $next = null;
    $currentDone = false;
    $periods = [];
    for ($day = $start, $guard = 0; $day <= $end && $guard < 2000; $day = date('Y-m-d', strtotime("$day +$step days")), $guard++) {
        $last = date('Y-m-d', strtotime("$day +" . ($step - 1) . ' days'));
        $periods[] = [$day, min($last, $end)];
    }
    foreach ($periods as [$from, $to]) {
        $ok = (bool)array_filter($recorded, fn($d) => $d >= $from && $d <= $to);
        if ($from > $today) {
            $next ??= $from;
            break;
        }
        $due++;
        if ($ok) {
            $done++;
        }
        if ($to < $today && !$ok) {
            $missed[] = $from;
        }
        if ($from <= $today && $today <= $to) {
            $currentDone = $ok;
            $next = $ok ? null : $from;
        }
    }
    if ($next === null && $currentDone) {
        foreach ($periods as [$from]) {
            if ($from > $today) {
                $next = $from;
                break;
            }
        }
    }
    $parts = ["$done sur $due " . ($due > 1 ? 'faites' : 'faite')];
    if ($missed) {
        $parts[] = count($missed) . ' manquée' . (count($missed) > 1 ? 's' : '') . ' ('
            . implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_slice($missed, -3))) . ')';
    }
    if ($end < $today) {
        $parts[] = 'terminé';
    } elseif ($start > $today) {
        $parts[] = 'commence le ' . format_date($start);
    } elseif ($next !== null) {
        $parts[] = $next <= $today && !$currentDone ? 'à faire aujourd’hui' : 'prochaine le ' . date('d/m', strtotime($next));
    }
    return ['done' => $done, 'due' => $due, 'missed' => $missed, 'next' => $next, 'current_done' => $currentDone, 'text' => implode(' · ', $parts)];
}

/** Repas du jour pas encore notés pour un animal présent dont le régime est suivi chaque jour (FoodTracking.MissingToday). */
function online_food_missing_today(array $state, array $animal): bool
{
    if (empty($animal['DietDailyTracking']) || ($animal['Status'] ?? 'Présent') !== 'Présent') {
        return false;
    }
    $today = date('Y-m-d');
    foreach (online_rows_of($state, 'FoodLogs', (string)$animal['_id']) as $log) {
        if (substr((string)($log['LogDate'] ?? ''), 0, 10) === $today && ($log['Kind'] ?? '') !== 'Eau') {
            return false;
        }
    }
    return true;
}

/**
 * Échéances de l'accueil pour les animaux visibles : traitements, rappels, rendez-vous, frais et entretiens (une semaine à
 * l'avance au plus), assurance à envoyer, repas du jour, suivis demandés. Chaque élément : [date, catégorie, titre, détail,
 * animal (SyncId ou null), nom, table, id].
 */
function online_dashboard_items(array $state, array $animals, bool $finance, int $daysAhead = 30): array
{
    $today = date('Y-m-d');
    $limit = date('Y-m-d', strtotime("+$daysAhead days"));
    $recurringLimit = date('Y-m-d', strtotime('+' . min($daysAhead, 7) . ' days'));
    $items = [];
    $add = function (string $date, string $category, string $title, string $detail, ?string $animalId, string $table = '', string $id = '') use (&$items, $animals) {
        $items[] = ['date' => $date, 'category' => $category, 'title' => $title, 'detail' => $detail, 'animal' => $animalId,
            'name' => $animalId !== null ? (string)($animals[$animalId]['Name'] ?? '') : '', 'table' => $table, 'id' => $id];
    };
    foreach ($state['rows']['Treatments'] ?? [] as $id => $t) {
        $animalId = (string)($t['AnimalId'] ?? '');
        $next = (string)($t['NextAdministration'] ?? '');
        if (isset($animals[$animalId]) && ($t['Status'] ?? '') === 'En cours' && $next !== '' && substr($next, 0, 10) <= $limit) {
            $add($next, 'Traitement', (string)($t['MedicationName'] ?? ''),
                trim((string)($t['Dosage'] ?? '') . (($t['TimesOfDay'] ?? '') !== '' ? ' · ' . $t['TimesOfDay'] : '')), $animalId, 'Treatments', (string)$id);
        }
    }
    foreach ($state['rows']['AnimalEvents'] ?? [] as $id => $e) {
        $animalId = (string)($e['AnimalId'] ?? '');
        if (!isset($animals[$animalId])) {
            continue;
        }
        $due = substr((string)($e['NextDueDate'] ?? ''), 0, 10);
        if ($due !== '' && $due <= $limit) {
            $add($due, (string)($e['Type'] ?? ''), (string)($e['Title'] ?? ''), (string)($e['Description'] ?? ''), $animalId, 'AnimalEvents', (string)$id);
        }
        $date = substr((string)($e['EventDate'] ?? ''), 0, 10);
        if ($date > $today && $date <= $limit) {
            $add($date, 'Rendez-vous', (string)($e['Title'] ?? ''),
                (string)($e['Type'] ?? '') . (($e['Veterinarian'] ?? '') !== '' ? ' · ' . $e['Veterinarian'] : ''), $animalId, 'AnimalEvents', (string)$id);
        }
    }
    foreach ($state['rows']['RecurringTasks'] ?? [] as $id => $r) {
        $animalId = ($r['AnimalId'] ?? null) !== null ? (string)$r['AnimalId'] : null;
        $due = substr((string)($r['NextDueDate'] ?? ''), 0, 10);
        if (empty($r['Active']) || $due === '' || $due > $recurringLimit || ($animalId !== null && !isset($animals[$animalId]))
            || (!$finance && ($r['Kind'] ?? '') === 'Frais') || ($animalId === null && count($animals) < count($state['rows']['Animals'] ?? []))) {
            continue; // famille d'accueil seule : pas les entretiens généraux de la structure
        }
        $detail = ($r['Amount'] ?? null) !== null && $finance ? online_money($r['Amount']) : (string)($r['Equipment'] ?? '');
        $add($due, (string)($r['Kind'] ?? 'Entretien'), (string)($r['Title'] ?? ''), $detail, $animalId, 'RecurringTasks', (string)$id);
        if ($animalId === null) {
            $items[count($items) - 1]['name'] = ($r['Equipment'] ?? '') !== '' ? (string)$r['Equipment'] : 'Entretien';
        }
    }
    if ($finance) {
        foreach ($state['rows']['AnimalExpenses'] ?? [] as $id => $x) {
            $animalId = (string)($x['AnimalId'] ?? '');
            if (isset($animals[$animalId]) && ($x['InsuranceStatus'] ?? '') === 'À envoyer') {
                $add($today, 'Assurance', 'À envoyer à l’assurance : ' . ($x['Description'] ?? ''),
                    online_money($x['Amount'] ?? 0) . ' · dépense du ' . format_date((string)($x['ExpenseDate'] ?? '')), $animalId, 'AnimalExpenses', (string)$id);
            }
        }
    }
    foreach ($animals as $animalId => $animal) {
        if (online_food_missing_today($state, $animal)) {
            $add($today, 'Alimentation', 'Repas et eau du jour à noter',
                trim(($animal['DietBrand'] ?? '') . ' ' . ($animal['DietName'] ?? '')), (string)$animalId, 'FoodLogs');
        }
    }
    foreach ($state['rows']['FollowUps'] ?? [] as $id => $f) {
        $animalId = (string)($f['AnimalId'] ?? '');
        if (!isset($animals[$animalId]) || substr((string)($f['EndDate'] ?? ''), 0, 10) < $today) {
            continue;
        }
        $status = online_follow_up_status($state, $f, $today);
        if ($status['next'] === null || $status['next'] > $limit) {
            continue;
        }
        $detail = online_follow_up_interval($f) . ' jusqu’au ' . date('d/m', strtotime((string)$f['EndDate']));
        $add($status['next'], 'Suivi demandé', (string)($f['Type'] ?? ''), $detail . (($f['RequestedBy'] ?? '') !== '' ? ' · ' . $f['RequestedBy'] : ''),
            $animalId, 'FollowUps', (string)$id);
    }
    usort($items, fn($a, $b) => [substr($a['date'], 0, 16), mb_strtolower($a['name'])] <=> [substr($b['date'], 0, 16), mb_strtolower($b['name'])]);
    return $items;
}

/** Articles du stock sous leur seuil d'alerte ou périmés (ou périmant dans le mois). */
function online_stock_alerts(array $state): array
{
    $soon = date('Y-m-d', strtotime('+30 days'));
    $alerts = [];
    foreach ($state['rows']['StockItems'] ?? [] as $id => $item) {
        $low = (float)($item['MinimumQuantity'] ?? 0) > 0 && (float)($item['Quantity'] ?? 0) <= (float)$item['MinimumQuantity'];
        $expiry = substr((string)($item['ExpiryDate'] ?? ''), 0, 10);
        if ($low || ($expiry !== '' && $expiry <= $soon)) {
            $alerts[$id] = $item + ['_low' => $low, '_expiry' => $expiry !== '' && $expiry <= $soon ? $expiry : null];
        }
    }
    return $alerts;
}

/**
 * Prise d'un traitement « Donné » ou « Oublié » (TreatmentService.RecordAdministration) : une prise donnée décompte la
 * dose du produit lié du stock ; la prochaine prise avance selon la fréquence. Renvoie le message pour la personne.
 */
function online_record_administration(int $organisationId, array $state, string $treatmentId, string $status): string
{
    $treatment = $state['rows']['Treatments'][$treatmentId];
    $changes = [];
    $consumed = null;
    $message = $status === 'Donné' ? 'Prise notée.' : 'Prise oubliée notée.';
    $stockId = (string)($treatment['StockItemId'] ?? '');
    $dose = (float)($treatment['DoseQuantity'] ?? 0);
    if ($status === 'Donné' && $stockId !== '' && $dose > 0 && isset($state['rows']['StockItems'][$stockId])) {
        $item = $state['rows']['StockItems'][$stockId];
        $quantity = (float)($item['Quantity'] ?? 0);
        $consumed = min($dose, $quantity);
        $remaining = max(0, $quantity - $dose);
        $changes[] = ['StockItems', $stockId, online_row_data($item, ['Quantity' => $remaining, 'UpdatedAt' => now()])];
        $message .= ' Stock de ' . ($item['Name'] ?? '') . ' : ' . online_number($remaining) . ' ' . ($item['Unit'] ?? '')
            . ($remaining <= (float)($item['MinimumQuantity'] ?? 0) ? ' (à racheter)' : '') . '.';
    }
    $scheduled = (string)($treatment['NextAdministration'] ?? '') !== '' ? (string)$treatment['NextAdministration'] : now();
    $changes[] = ['MedicationAdministrations', online_new_id(), [
        'AnimalId' => $treatment['AnimalId'], 'TreatmentId' => $treatmentId, 'ScheduledAt' => $scheduled, 'RecordedAt' => now(),
        'Status' => $status, 'Notes' => '', 'StockQuantity' => $consumed,
    ]];
    $next = online_advance(strlen($scheduled) > 10 ? $scheduled : "$scheduled 00:00:00", (int)($treatment['FrequencyValue'] ?? 1),
        (string)($treatment['FrequencyUnit'] ?? 'jour(s)'));
    array_unshift($changes, ['Treatments', $treatmentId, online_row_data($treatment, ['NextAdministration' => $next])]);
    online_write_changes($organisationId, $changes);
    return $message;
}

/** Échéance suivante d'une tâche, sans jamais rester dans le passé par rapport à la date de réalisation (RecurringTaskService.Advance). */
function online_task_next_due(array $task, string $reference): string
{
    $value = (int)($task['IntervalValue'] ?? 1);
    $unit = (string)($task['IntervalUnit'] ?? 'mois');
    $next = online_advance(substr((string)$task['NextDueDate'], 0, 10), $value, $unit);
    $today = date('Y-m-d');
    for ($guard = 0; $next <= $reference && $next < $today && $guard < 1000; $guard++) {
        $next = online_advance($next, $value, $unit);
    }
    return $next;
}

/**
 * Frais ou entretien fait (RecurringTaskService.Complete) : des frais liés à un animal deviennent une dépense ; l'échéance
 * avance. « Passer » ($skip) avance seulement l'échéance.
 */
function online_complete_task(int $organisationId, array $state, string $taskId, bool $skip, ?float $amount = null): void
{
    $task = $state['rows']['RecurringTasks'][$taskId];
    $today = date('Y-m-d');
    $changes = [];
    if ($skip) {
        $values = ['NextDueDate' => online_task_next_due($task, substr((string)$task['NextDueDate'], 0, 10))];
    } else {
        $values = ['LastDoneDate' => $today, 'NextDueDate' => online_task_next_due($task, $today)];
        $animalId = $task['AnimalId'] ?? null;
        if (($task['Kind'] ?? '') === 'Frais' && $animalId !== null && isset($state['rows']['Animals'][(string)$animalId])) {
            $contact = (string)($state['rows']['Contacts'][(string)($task['ContactId'] ?? '')]['Name'] ?? '');
            $changes[] = ['AnimalExpenses', online_new_id(), [
                'AnimalId' => $animalId, 'ExpenseDate' => $today, 'Category' => (string)($task['Category'] ?? 'Autre'),
                'Description' => $contact === '' ? (string)$task['Title'] : $task['Title'] . ' — ' . $contact,
                'Amount' => $amount ?? (float)($task['Amount'] ?? 0), 'ReimbursedAmount' => 0, 'PaidBy' => (string)($task['PaidBy'] ?? 'Association'),
                'Notes' => 'Échéance du ' . format_date(substr((string)$task['NextDueDate'], 0, 10)), 'CreatedAt' => now(),
            ]];
        }
    }
    array_unshift($changes, ['RecurringTasks', $taskId, online_row_data($task, $values)]);
    online_write_changes($organisationId, $changes);
}
