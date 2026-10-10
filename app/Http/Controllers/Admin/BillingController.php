<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRecord;
use App\Models\StripeEvent;
use App\Services\Audit;
use App\Services\Billing\WebhookProcessor;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.billing.index', [
            'events' => StripeEvent::when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', 'like', "%$t%"))->latest()->paginate(50, ['id', 'stripe_event_id', 'type', 'status', 'attempts', 'last_error', 'created_at', 'processed_at'])->withQueryString(),
            'payments' => PaymentRecord::with('organization')->when($request->query('payment_status'), fn ($q, $s) => $q->where('status', $s))->latest()->limit(50)->get(),
        ]);
    }

    /** Retraitement contrôlé d'un événement en échec. */
    public function reprocess(StripeEvent $event, WebhookProcessor $processor)
    {
        abort_unless($event->status === 'failed', 422, 'Seuls les événements en échec peuvent être retraités.');
        $ok = $processor->process($event);
        Audit::log('admin.stripe_event_reprocessed', $event, ['ok' => $ok]);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Événement retraité.' : 'Le retraitement a échoué : '.$event->fresh()->last_error);
    }
}
