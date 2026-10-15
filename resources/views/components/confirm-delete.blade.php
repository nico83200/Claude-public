@props(['action', 'label' => 'Supprimer', 'message' => 'Confirmer la suppression ?', 'method' => 'DELETE', 'class' => 'btn-ghost text-red-600', 'check' => null, 'checkLabel' => null])
<form method="POST" action="{{ $action }}" data-confirm="{{ $message }}" data-confirm-ok="{{ $label }}" @if ($check) data-confirm-check="{{ $check }}" data-confirm-check-label="{{ $checkLabel }}" @endif class="inline">
    @csrf @method($method)
    <button type="submit" class="{{ $class }}">{{ $slot->isEmpty() ? $label : $slot }}</button>
</form>
