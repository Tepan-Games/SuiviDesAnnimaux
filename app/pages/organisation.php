<?php
declare(strict_types=1);

// ===== Structures : création, membres, invitations, licence (côté client) =====

function page_organisation_new(): void
{
    $user = require_login();
    $errors = [];
    $values = ['name' => '', 'kind' => 'Association'];
    if (is_post()) {
        csrf_check();
        $values = ['name' => input('name'), 'kind' => input('kind')];
        if (mb_strlen($values['name']) < 2) {
            $errors[] = 'Indiquez le nom de la structure.';
        }
        if (!$errors) {
            $id = create_organisation((int)$user['id'], $values['name'], $values['kind']);
            flash('Structure créée avec la formule Découverte (jusqu’à 5 animaux).');
            redirect("/compte/structure?id=$id");
        }
    }
    view('account/organisation_new', ['errors' => $errors, 'values' => $values], 'Nouvelle structure');
}

function page_organisation(): void
{
    $user = require_login();
    $organisation = organisation_for_member((int)input('id'), (int)$user['id']);
    if (!$organisation) {
        not_found();
    }
    $manage = can_manage($organisation['roles']);
    $id = (int)$organisation['id'];
    $newLink = null;
    if (is_post()) {
        csrf_check();
        if (!$manage) {
            http_response_code(403);
            exit('Seul un responsable peut gérer les membres.');
        }
        $error = null;
        switch (input('action')) {
            case 'inviter':
                $email = mb_strtolower(input('email'));
                $roles = posted_roles();
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $roles === '') {
                    $error = 'Indiquez une adresse e-mail valide et au moins un rôle.';
                } else {
                    $newLink = create_invitation($organisation, $email, $roles, $user);
                    flash("Invitation envoyée à $email.");
                }
                break;
            case 'annuler':
                revoke_invitation($id, (int)input('invitation'));
                flash('Invitation annulée.');
                break;
            case 'retirer':
                $error = remove_member($id, (int)input('member'));
                $error ?? flash('Membre retiré.');
                break;
            case 'roles':
                $error = set_member_roles($id, (int)input('member'), posted_roles());
                $error ?? flash('Rôles enregistrés.');
                break;
            case 'infos':
                q('UPDATE organisations SET name = ?, contact_email = ?, phone = ?, address = ?, siret = ? WHERE id = ?',
                    [input('name') ?: $organisation['name'], input('contact_email'), input('phone'), input('address'), input('siret'), $id]);
                // Nouvelle adresse : nouvelle position pour la recherche « À proximité ».
                if (input('address') !== $organisation['address']) {
                    $error = save_directory_settings(['latitude' => null] + $organisation, ['address' => input('address')] + $organisation);
                }
                flash('Informations enregistrées.');
                break;
            case 'annuaire':
                $error = save_directory_settings($organisation, [
                    'directory_listed' => input('directory_listed') === '1', 'accepts_questions' => input('accepts_questions') === '1',
                    'description' => input('description'), 'species' => input('species'), 'website' => input('website'),
                ]);
                flash('Annuaire et questions enregistrés.');
                break;
        }
        if ($error) {
            flash($error, 'error');
        }
        if ($newLink === null) {
            redirect("/compte/structure?id=$id");
        }
        $organisation = organisation_for_member($id, (int)$user['id']);
    }
    view('account/organisation', [
        'organisation' => $organisation,
        'manage' => $manage,
        'members' => organisation_members($id),
        'invitations' => $manage ? pending_invitations($id) : [],
        'licence' => organisation_licence($id),
        'newLink' => $newLink,
        'user' => $user,
    ], $organisation['name']);
}

/** Lien d'invitation : accepter (connecté), sinon créer un compte ou se connecter puis revenir ici. */
function page_invitation(): void
{
    $token = input('c');
    $invitation = find_invitation($token);
    if (!$invitation) {
        view('error', ['message' => 'Cette invitation n’est plus valable (déjà utilisée, annulée ou expirée). Demandez-en une nouvelle à la structure.'], 'Invitation');
        return;
    }
    $user = current_user();
    if (!$user) {
        $_SESSION['pending_invitation'] = $token;
        $_SESSION['after_login'] = '/invitation?c=' . $token;
        view('account/invitation', ['invitation' => $invitation, 'user' => null], 'Invitation');
        return;
    }
    if (is_post()) {
        csrf_check();
        accept_invitation($invitation, $user);
        unset($_SESSION['pending_invitation']);
        flash("Vous avez rejoint « {$invitation['organisation_name']} ».");
        redirect('/compte/structure?id=' . $invitation['organisation_id']);
    }
    view('account/invitation', ['invitation' => $invitation, 'user' => $user], 'Invitation');
}
