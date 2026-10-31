<?php
declare(strict_types=1);

// ===== Lettre d'information : rédaction et envoi (administration), désinscription (public) =====

function page_admin_newsletters(): void
{
    $admin = require_admin();
    if (is_post()) {
        csrf_check();
        q('INSERT INTO newsletters (subject, body, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            ['Les nouveautés de Suivi des animaux', NEWSLETTER_TEMPLATE, $admin['id'], now(), now()]);
        redirect('/admin/lettre?id=' . db()->lastInsertId());
    }
    $newsletters = q('SELECT n.*, COUNT(d.id) AS total, COUNT(d.sent_at) AS sent, COUNT(d.unsubscribed_at) AS unsubscribed
                      FROM newsletters n LEFT JOIN newsletter_deliveries d ON d.newsletter_id = n.id
                      GROUP BY n.id ORDER BY n.created_at DESC, n.id DESC')->fetchAll();
    view('admin/newsletters', ['newsletters' => $newsletters, 'subscribers' => newsletter_subscriber_count()], 'Lettres d’information');
}

function page_admin_newsletter(): void
{
    $admin = require_admin();
    $newsletter = find_newsletter((int)input('id'));
    if (!$newsletter) {
        not_found();
    }
    $id = (int)$newsletter['id'];
    $editable = $newsletter['queued_at'] === null;
    $errors = [];
    if (is_post()) {
        csrf_check();
        $action = input('action');
        if ($editable && in_array($action, ['enregistrer', 'essai', 'envoyer'], true)) {
            $subject = input('subject');
            $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
            if (mb_strlen($subject) < 3 || mb_strlen($subject) > 200) {
                $errors[] = 'L’objet doit faire entre 3 et 200 caractères.';
            }
            if (mb_strlen(trim($body)) < 20) {
                $errors[] = 'Le texte de la lettre est trop court.';
            }
            if (!$errors) {
                q('UPDATE newsletters SET subject = ?, body = ?, updated_at = ? WHERE id = ?', [$subject, $body, now(), $id]);
                $newsletter = find_newsletter($id);
            } else {
                $newsletter['subject'] = $subject;
                $newsletter['body'] = $body;
            }
        }
        if (!$errors) {
            switch ($action) {
                case 'enregistrer':
                    flash('Lettre enregistrée.');
                    break;
                case 'essai':
                    newsletter_send_to($newsletter, $admin, '');
                    flash("Essai envoyé à {$admin['email']}.");
                    break;
                case 'envoyer':
                    if (!$editable) {
                        break;
                    }
                    if (empty($_POST['confirmation'])) {
                        flash('Cochez la case de confirmation pour envoyer la lettre.', 'error');
                        break;
                    }
                    $total = newsletter_queue($newsletter);
                    $sent = $total > 0 ? newsletter_send_batch(find_newsletter($id)) : 0;
                    flash($total === 0 ? 'Aucun abonné : rien n’a été envoyé.' : "Envoi commencé : $sent e-mail(s) sur $total.");
                    break;
                case 'lot':
                    if (!$editable && $newsletter['finished_at'] === null) {
                        flash(newsletter_send_batch($newsletter) . ' e-mail(s) de plus envoyé(s).');
                    }
                    break;
                case 'supprimer':
                    if ($editable) {
                        q('DELETE FROM newsletters WHERE id = ?', [$id]);
                        flash('Brouillon supprimé.');
                        redirect('/admin/lettres');
                    }
                    break;
            }
            redirect("/admin/lettre?id=$id");
        }
    }
    view('admin/newsletter', [
        'newsletter' => $newsletter,
        'editable' => $editable,
        'errors' => $errors,
        'stats' => newsletter_stats($id),
        'subscribers' => newsletter_subscriber_count(),
        'preview' => newsletter_personalise($newsletter['body'], $admin),
        'batch' => max(1, (int)config('newsletter.batch', 50)),
    ], 'Lettre d’information');
}

/**
 * Lien « Ne plus recevoir la lettre ». La page demande une confirmation (les antivirus de messagerie ouvrent les liens) ;
 * l'envoi du bouton, comme le désabonnement en un clic de Gmail ou Outlook (POST List-Unsubscribe=One-Click), n'a pas de
 * jeton CSRF : c'est le jeton du lien, propre au destinataire, qui l'autorise.
 */
function page_unsubscribe(): void
{
    $token = input('t');
    $delivery = newsletter_delivery_by_token($token);
    $done = false;
    if ($delivery && is_post()) {
        newsletter_unsubscribe($delivery);
        $done = true;
        if (input('List-Unsubscribe') === 'One-Click') {
            http_response_code(200);
            exit('Désinscription enregistrée.');
        }
    }
    if (!$delivery) {
        http_response_code(404);
    }
    view('unsubscribe', [
        'delivery' => $delivery,
        'token' => $token,
        'done' => $done || ($delivery && $delivery['newsletter_at'] === null),
    ], 'Lettre d’information');
}
