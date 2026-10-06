<?php
declare(strict_types=1);

/**
 * Validation des demandes par le responsable de centre (au-delà du seuil paramétré).
 */

function approvals_index(): void
{
    require_login();
    $ids = approval_center_ids();
    if (!$ids) {
        abort(403, 'Réservé aux responsables de centre.');
    }
    $requests = all("SELECT r.*, u.first_name, u.last_name, u.job, c.name AS center_name, c.color AS center_color
                     FROM requests r JOIN users u ON u.id = r.user_id JOIN centers c ON c.id = r.center_id
                     WHERE r.approval_status = 'pending' AND r.center_id IN " . in_list($ids) . ' ORDER BY r.urgent DESC, r.created_at', $ids);
    foreach ($requests as &$r) {
        $r['lines'] = all("SELECT rl.*, p.name, p.reference, p.unit, p.image, s.name AS supplier_name, s.color AS supplier_color
                           FROM request_lines rl JOIN products p ON p.id = rl.product_id JOIN suppliers s ON s.id = rl.supplier_id
                           WHERE rl.request_id = ? AND rl.status = 'awaiting' ORDER BY s.name, p.name", [$r['id']]);
        $r['total'] = array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $r['lines']));
        $r['budget'] = budget_status((int)$r['center_id']);
    }
    unset($r);
    $history = all("SELECT r.*, u.first_name, u.last_name, c.name AS center_name, a.first_name AS a_first, a.last_name AS a_last
                    FROM requests r JOIN users u ON u.id = r.user_id JOIN centers c ON c.id = r.center_id LEFT JOIN users a ON a.id = r.approved_by
                    WHERE r.approval_status IN ('approved','rejected') AND r.center_id IN " . in_list($ids) . ' ORDER BY r.approved_at DESC LIMIT 15', $ids);
    render('user/approvals', ['title' => 'Validations', 'requests' => $requests, 'history' => $history,
        'threshold' => (float)str_replace(',', '.', (string)setting('approval_threshold', '0'))]);
}

function approval_decide(): void
{
    $me = require_login();
    $r = one("SELECT r.*, c.name AS center_name FROM requests r JOIN centers c ON c.id = r.center_id WHERE r.id = ? AND r.approval_status = 'pending'", [input_int('id')]);
    if (!$r || !in_array((int)$r['center_id'], approval_center_ids(), true)) {
        abort(403);
    }
    $decision = input('decision') === 'reject' ? 'rejected' : 'approved';
    $note = mb_substr(trim((string)input('note', '')), 0, 255) ?: null;
    $kept = 0;
    tx(function () use ($r, $decision, $note, $me, &$kept) {
        foreach (all("SELECT * FROM request_lines WHERE request_id = ? AND status = 'awaiting'", [$r['id']]) as $l) {
            $qty = isset($_POST['qty'][$l['id']]) ? max(0, (int)$_POST['qty'][$l['id']]) : (int)$l['qty'];
            if ($decision === 'rejected' || $qty === 0) {
                update('request_lines', ['status' => 'cancelled', 'cancel_reason' => 'Non validée par le responsable' . ($note ? ' : ' . $note : '')], 'id = ?', [$l['id']]);
            } else {
                update('request_lines', ['status' => 'pending', 'qty' => $qty], 'id = ?', [$l['id']]);
                $kept++;
            }
        }
        update('requests', ['approval_status' => $kept ? 'approved' : 'rejected', 'approved_by' => $me['id'], 'approved_at' => now(), 'approval_note' => $note], 'id = ?', [$r['id']]);
    });
    audit($kept ? 'Demande validée' : 'Demande refusée', 'request', (int)$r['id'], $note);
    notify([(int)$r['user_id']], 'approval_done', ($kept ? 'Demande validée' : 'Demande non validée') . ' par votre responsable',
        'Demande n°' . $r['id'] . ' (' . $r['center_name'] . ') : ' . ($kept ? plural($kept, 'article transmis', 'articles transmis') . ' au service achats.' : 'aucun article retenu.')
            . ($note ? "\nMessage : " . $note : ''), url('requests'));
    if ($kept) {
        notify(admin_ids(), 'request_new', 'Nouvelle demande validée — ' . $r['center_name'],
            'Demande n°' . $r['id'] . ' validée par ' . $me['first_name'] . ' ' . $me['last_name'] . ' : ' . plural($kept, 'article', 'articles') . '.',
            url('admin/requests', ['center' => $r['center_id']]));
    }
    flash('success', $kept ? 'Demande validée : elle part au service achats.' : 'Demande refusée, le salarié a été prévenu.');
    redirect('approvals');
}
