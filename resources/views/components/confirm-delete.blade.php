@props(['action', 'label' => 'Supprimer', 'message' => 'Confirmer la suppression ?', 'method' => 'DELETE', 'class' => 'btn-ghost text-red-600'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($message))" class="inline">
    @csrf @method($method)
    <button type="submit" class="{{ $class }}">{{ $slot->isEmpty() ? $label : $slot }}</button>
</form>
