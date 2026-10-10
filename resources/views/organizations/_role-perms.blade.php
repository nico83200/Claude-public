<div class="grid gap-x-4 sm:grid-cols-2">
    @foreach (\App\Support\Perm::horseLabels() + array_diff_key(\App\Support\Perm::orgLabels(), ['billing.manage' => 1]) as $key => $label)
        <label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected)) class="rounded"> {{ $label }}</label>
    @endforeach
</div>
