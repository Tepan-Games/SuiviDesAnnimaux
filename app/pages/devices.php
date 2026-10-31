<?php
declare(strict_types=1);

// ===== Espace client : appareils connectés et code de connexion d'un appareil =====

function page_devices(): void
{
    $user = require_login();
    $code = null;
    if (is_post()) {
        csrf_check();
        switch (input('action')) {
            case 'code':
                $code = create_device_code((int)$user['id']);
                break;
            case 'deconnecter':
                revoke_device((int)$user['id'], (int)input('device'))
                    ? flash('Appareil déconnecté : il devra se reconnecter pour synchroniser.')
                    : flash('Cet appareil n’est plus connecté.', 'error');
                redirect('/compte/appareils');
        }
    }
    view('account/devices', [
        'devices' => user_devices((int)$user['id']),
        'code' => $code,
        'organisations' => user_organisations((int)$user['id']),
    ], 'Appareils connectés');
}
