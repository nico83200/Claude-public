<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

final class CustomersController extends BaseController
{
    public function index(): void
    {
        $customers = Database::all(
            "SELECT email, MAX(customer_name) AS name, COUNT(*) AS orders, SUM(total) AS spent, MAX(paid_at) AS last_order
             FROM orders WHERE paid_at IS NOT NULL AND email IS NOT NULL GROUP BY email ORDER BY last_order DESC LIMIT 500"
        );
        $this->render('customers', [
            'pageTitle'   => 'Clients & newsletter',
            'customers'   => $customers,
            'accounts'    => array_flip(array_column(Database::all('SELECT email FROM customers'), 'email')),
            'accountCount' => (int) Database::value('SELECT COUNT(*) FROM customers'),
            'latestAccounts' => Database::all('SELECT email, name, created_at, email_verified_at FROM customers ORDER BY id DESC LIMIT 20'),
            'subscribers' => Database::all('SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 100'),
            'subCount'    => (int) Database::value('SELECT COUNT(*) FROM newsletter_subscribers'),
        ]);
    }

    public function exportNewsletter(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['email', 'source', 'date_inscription'], ';');
        foreach (Database::all('SELECT email, source, created_at FROM newsletter_subscribers ORDER BY id') as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    public function messages(): void
    {
        $messages = Database::all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 200');
        Database::query('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        $this->render('messages', ['pageTitle' => 'Messages', 'messages' => $messages]);
    }
}
