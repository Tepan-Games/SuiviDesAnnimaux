<?php
declare(strict_types=1);

// ===== Assistance (côté client) =====

function page_support(): void
{
    $user = require_login();
    $organisations = user_organisations((int)$user['id']);
    $errors = [];
    if (is_post()) {
        csrf_check();
        $subject = input('subject');
        $body = input('body');
        $organisationId = (int)input('organisation') ?: null;
        if ($organisationId !== null && !organisation_for_member($organisationId, (int)$user['id'])) {
            $organisationId = null;
        }
        if (mb_strlen($subject) < 4 || mb_strlen($body) < 10) {
            $errors[] = 'Indiquez un objet et décrivez le problème en quelques mots.';
        } else {
            $id = create_ticket($user, $organisationId, mb_substr($subject, 0, 200), input('category'), $body);
            flash("Demande n°$id envoyée. Nous vous répondons par e-mail et sur cette page.");
            redirect("/compte/demande?id=$id");
        }
    }
    view('account/support', [
        'organisations' => $organisations,
        'tickets' => q('SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC', [$user['id']])->fetchAll(),
        'errors' => $errors,
    ], 'Assistance');
}

function page_ticket(): void
{
    $user = require_login();
    $ticket = find_ticket((int)input('id'), (int)$user['id']);
    if (!$ticket) {
        not_found();
    }
    if (is_post()) {
        csrf_check();
        if (input('action') === 'resolu') {
            q('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?', ['resolu', now(), $ticket['id']]);
            flash('Demande marquée comme résolue. Merci !');
        } elseif (mb_strlen(input('body')) >= 2) {
            reply_ticket($ticket, $user, input('body'), false);
            flash('Réponse envoyée.');
        }
        redirect("/compte/demande?id={$ticket['id']}");
    }
    view('account/ticket', ['ticket' => $ticket, 'messages' => ticket_messages((int)$ticket['id'])], "Demande n°{$ticket['id']}");
}
