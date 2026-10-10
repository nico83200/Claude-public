<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\AccountDeletionService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DataRequestController extends Controller
{
    public function index()
    {
        return view('admin.data-requests', ['requests' => DataRequest::with(['user', 'handler'])->latest()->paginate(40)]);
    }

    public function update(Request $request, DataRequest $dataRequest, AccountDeletionService $deletion)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'done', 'rejected'])], 'execute_deletion' => ['nullable', 'boolean']]);
        if ($request->boolean('execute_deletion') && $dataRequest->type === 'deletion' && $dataRequest->user) {
            abort_if($dataRequest->user->is_super_admin, 422, 'Retirez d\'abord le rôle de super-administrateur.');
            $deletion->anonymize($dataRequest->user);
            $data['status'] = 'done';
        }
        unset($data['execute_deletion']);
        $dataRequest->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        Audit::log('admin.gdpr_request_updated', $dataRequest, $data);

        return back()->with('success', 'Demande mise à jour.');
    }
}
